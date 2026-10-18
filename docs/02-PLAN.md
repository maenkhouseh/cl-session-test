# Shortie — Architecture & Delivery Plan

## 1. Stack
| Layer | Choice | Why |
|---|---|---|
| Backend | **Laravel 13** (PHP 8.3), Sanctum, PHPUnit, Pint | Latest LTS-line framework; first-party auth/queue/cache |
| Frontend | **Next.js 16** (App Router, React 19, TS strict), Tailwind v4, TanStack Query, Zod, Recharts | Server components for marketing, client islands for dashboard |
| Database | **PostgreSQL 16** (tests run on Postgres too — analytics SQL is dialect-specific) | `date_trunc`, partial/BRIN indexes, `jsonb`, partitioning |
| Cache/queue/session | **Redis 7** | Redirect cache, click buffer, rate limits, queues |
| Auth | Sanctum cookie SPA auth (dashboard) + hashed API keys (public API) | Same-origin via Next rewrites |
| Monorepo | `backend/`, `frontend/`, `docs/` | Single PR flow |

## 2. Backend architecture (thin controllers)
```
app/
  Actions/           one invokable class per use-case (CreateLink, UpdateLink, RecordClick, TrackSale…)
  Services/          stateful/IO collaborators (RedirectResolver, KeyGenerator, UrlNormalizer, Analytics\AnalyticsQuery, QrCodeService, WebhookDispatcher, PlanLimiter, UserAgentParser, GeoResolver)
  Support/           pure helpers (Url, Str, Money, DateRange, Cursor)
  Http/
    Controllers/Api/V1/…   thin: authorize → FormRequest → Action → Resource
    Controllers/Redirect/  RedirectController (hot path), PasswordController, StatsController
    Requests/ Resources/ Middleware/
  Models/  Policies/  Enums/  Jobs/  Events/ Listeners/
  Data/              typed DTOs (readonly) passed into Actions
tests/{Unit,Feature}/  Pest; factories for every model
```
Rules: controllers ≤ 15 lines/method; no query logic in controllers (Eloquent query objects/scopes);
Actions are the only place that mutates state; every Action has a feature test; every service a unit test.

## 3. Data model & DB optimisation (filtering + analytics first)
- IDs: **ULID** primary keys for core entities (sortable, safe to expose); `bigint` identity for append-only event rows.
- `links`: unique `(domain_id, key)` (case-insensitive via `citext`/lower index), indexes `(workspace_id, created_at desc)`,
  `(workspace_id, folder_id, created_at)`, partial `(workspace_id) where archived = false`, `(external_id, workspace_id)` unique-where-not-null.
  Counters (`clicks, leads, sales, sale_amount, last_clicked_at`) denormalised on the row — list sorting by clicks is an index scan, not an aggregate.
- Tags via pivot `link_tag(link_id, tag_id)` with covering index `(tag_id, link_id)`; list filtering uses `EXISTS`, and tag/folder/domain list pages eager-load in 3 queries total (no N+1; enforced by a test using `DB::listen` query-count assertions).
- Pagination: **keyset (cursor)** for events; link/customer lists use `limit+1` offset paging (no COUNT).
- Events — three append-only tables (`click_events`, `lead_events`, `sale_events`), each denormalising
  `workspace_id, link_id, occurred_at, country, city, region, continent, device, browser, os, referer, referer_url, utm_*, qr(trigger)`:
  - Composite indexes `(workspace_id, occurred_at desc)`, `(link_id, occurred_at desc)`; **BRIN** on `occurred_at`.
  - Monthly **range partitioning** on `occurred_at` (retention = drop partition).
  - A single `AnalyticsQuery` builder applies workspace/date/filter predicates once and is reused by timeseries, counts and each `top_*` grouping — one indexed scan per widget; dashboard fires widgets in parallel.
  - Link-level filters (tag/folder/domain) resolve to `link_id IN (subselect)` once; large sets use a temp CTE.
  - Roll-up table `analytics_daily(workspace_id, link_id, date, clicks, leads, sales, sale_amount)` maintained by an idempotent upsert job; used when range > 31 d and no event-level dimension filter.
- Click ingestion: redirect pushes a payload to a Redis list; `FlushClickBuffer` job bulk-`insert`s (≤1000 rows) and applies counter increments with one `UPDATE … FROM (VALUES …)`.
- Redirect cache: Redis hash per `domain:key` (TTL 1 h, busted on link/domain update) → zero DB hits on hot path.

## 4. Frontend architecture
```
frontend/src/
  app/(marketing)/…        home, links, analytics, pricing, changelog
  app/(auth)/…             login, register, forgot/reset
  app/(dashboard)/[workspace]/{links,analytics,events,customers,settings/*}
  app/share/[id]/          public dashboard
  components/{ui,layout,links,analytics,charts,forms,marketing}/   reusable, one folder per domain, barrel-free
  lib/{api,hooks,utils,validation,constants}/                       typed fetch client, query hooks, formatters
  types/                                                            API contracts mirrored from Laravel Resources
```
Rules: server components by default; `"use client"` only at interactive leaves; all API access via `lib/api` + TanStack Query hooks in `lib/hooks`; forms validated with the same Zod schemas mirroring FormRequests; no component > 200 lines.

## 5. API conventions
`/api/v1`, JSON, ULIDs, ISO-8601 UTC, Dub-compatible error envelope, `Idempotency-Key` on POST create, rate limit per plan via named limiter, `Link`/`Tag`/`Folder`/`Domain` resources match Dub field names so Dub SDK-style clients feel familiar.

## 6. Testing strategy (backend)
- **PHPUnit** feature tests for every endpoint: happy path, validation matrix, authz (cross-workspace isolation), plan limits, error envelope.
- Unit tests for services/helpers (KeyGenerator collisions, UrlNormalizer, UA parser, DateRange, AnalyticsQuery SQL against seeded events, PlanLimiter).
- Redirect matrix tests (expired, password, geo, device, ios/android, A/B distribution, bots, archived).
- Query-count assertions on list and analytics endpoints.
- CI: Pint, PHPUnit, `next build`, `tsc --noEmit`, ESLint.

## 7. Delivery phases (each ends with green tests + commit on `main`)
1. **Foundation** — Laravel 13 + Postgres + Pest; auth; workspaces/members; error envelope; API key auth.
2. **Links core** — domains, tags, folders, links CRUD/bulk/upsert, key generator, URL normaliser, QR, UTM templates, plan limits.
3. **Redirect engine** — resolver, cache, targeting, password, expiry, A/B, preview proxy, click buffer + flush.
4. **Analytics & conversions** — events, `AnalyticsQuery`, endpoints, CSV export, customers, track lead/sale, share dashboards.
5. **Platform** — webhooks (signed, retried), audit log, rate limiting, usage metering.
6. **Frontend** — design system, marketing pages, auth, dashboard (links, analytics, events, customers, settings), share page.
7. **Hardening** — seeders, docker-compose, CI workflow, docs. *(Partners = phase 2, schema-ready.)*
