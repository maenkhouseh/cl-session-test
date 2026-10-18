# Backend — implementation notes

## Stack as built
Laravel **13.37** · PHP 8.3 · PostgreSQL 16 · Redis 7 · Sanctum · PHPUnit 12 · Pint.
*(Pest and Larastan from the original plan could not be installed — the build environment's egress proxy blocks
GitHub package downloads — so the suite is PHPUnit; static analysis is Pint only.)*

## Architecture
```
app/Actions/<Domain>/*      one invokable class per use case (the only place state is mutated)
app/Services/*              collaborators with IO/state: KeyGenerator, LinkCache, ClickBuffer, UsageMeter, DomainRegistry, QrCodeService…
app/Services/Analytics/*    AnalyticsQuery (totals / timeseries / top-N), EventsQuery (keyset), DailyRollup
app/Support/*               pure helpers: UrlNormalizer, UtmParams, DestinationUrl, Continents, SafeUrl, Csv…
app/Http/Controllers/*      thin: validate (FormRequest) → Action/Service → Resource
routes/api.php              /api/v1 (Sanctum session or `Bearer shrt_…` API key)
routes/redirect.php         short-link runtime — registered with NO middleware group
```
Multi-tenancy: `ResolveWorkspace` middleware + workspace-scoped route-model binding, so another tenant's id is a 404 everywhere.

## Request/response contract
JSON, camelCase, ULIDs. Errors: `{"error": {"code", "message", "doc_url", "errors"?}}` with Dub's codes
(`bad_request`, `unauthorized`, `forbidden`, `not_found`, `conflict`, `invite_expired`, `unprocessable_entity`,
`rate_limit_exceeded`, `internal_server_error`) plus `exceeded_limit` for plan quotas.

## Data-flow decisions that matter for performance
| Concern | Decision |
|---|---|
| Redirect latency | Link resolved from Redis cache (negative results cached too) → **0 SQL queries** on repeat visits (asserted by a test) |
| Click ingestion | Redirect `RPUSH`es one JSON line; `FlushClickBuffer` (every 10 s) drains with a Lua script and bulk-inserts ≤1000 rows, updating counters with one `UPDATE … FROM (VALUES …)` |
| Event storage | `click_events`, `lead_events`, `sale_events` range-partitioned by month (default partition as safety net); `(workspace_id, occurred_at)`, `(link_id, occurred_at)` + BRIN indexes |
| Long-range analytics | `analytics_daily` pre-aggregate maintained on every write path; ranges are split into *roll-up for whole UTC days* + *raw events for the partial edge days*. Falls back to raw for dimension filters, non-UTC day buckets, unique-only. Equality with raw scans is asserted over 13 ranges × 5 scopes × 3 event types |
| Link lists | Denormalised counters on `links`; composite indexes per sort; `limit+1` instead of `COUNT(*)`; tags eager-loaded in one query (query-count asserted); pg_trgm for search |
| Events list | Keyset pagination `(occurred_at, id)` — constant cost at any depth |
| Idempotency | Partitioned tables can't hold unique indexes without the partition key → `event_dedupe` table for "one lead per customer+event" and "one sale per invoice" |
| Quotas | Atomic conditional `UPDATE … WHERE usage < limit` (race-free); events over quota are dropped at flush, links keep redirecting |

## Measured (PostgreSQL 16, 1,006,000 click events in one workspace, local container)
| Query | Raw scan | With roll-up |
|---|---|---|
| Totals (clicks+leads+sales), 24 h | 12.7 ms | 14.5 ms (raw: window < 1 day) |
| Totals, 30 d | 59.8 ms | **19.3 ms** |
| Totals, 90 d | 115.6 ms | **13.7 ms** |
| Timeseries, 30 d | 344 ms | **26.8 ms** |
| Timeseries, 90 d | 865 ms | **18.8 ms** |
| Totals, 90 d, single link | 148.8 ms | **5.0 ms** |
| Top countries / browsers / referers / cities, 90 d | 130–170 ms | same (needs event-level dimensions) |
| Top links / top URLs, 90 d | 155–162 ms | same |
| Events page (50 rows), first page / page 42 | 5.2 / 4.2 ms | same |

Partition pruning confirmed with `EXPLAIN ANALYZE` (`Subplans Removed: 6` for a 90-day window).
Next lever if dimension rankings need to get faster: per-dimension daily roll-ups (country, device, referer).

## Operations
```bash
php artisan migrate --force
php artisan schedule:work         # click flush (10 s), partition creation (daily), usage reset (hourly)
php artisan queue:work            # webhooks
php artisan rollup:rebuild [--from=Y-m-d --to=Y-m-d]   # backfill / repair the daily roll-up
php artisan db:seed --class=DemoSeeder                  # demo@shortie.test / password
```

## Not built (documented scope cuts)
Partners/affiliate payouts, SAML/SSO, Stripe billing (plans are set by an operator; quotas/limits are enforced),
registered-domain purchase, Ask-AI, third-party integrations, OAuth apps, importers, audit log UI.
