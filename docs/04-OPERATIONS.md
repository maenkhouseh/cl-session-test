# Operating Shortie

## Processes
| Process | Command | Purpose |
|---|---|---|
| Web | `php artisan serve` / php-fpm + nginx | API **and** the short-link runtime (`routes/redirect.php`) |
| Queue worker | `php artisan queue:work` | webhook deliveries (retries with backoff), click-buffer flush job |
| Scheduler | `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) | click flush every 10 s, daily partition creation, hourly usage reset |
| Frontend | `next start` | marketing site + dashboard; proxies `/api` and `/sanctum` to Laravel |

## Domains & DNS
- Point every short domain (`shortie.link`, customers' `go.acme.com` CNAMEs → `SHORTIE_DNS_TARGET`) at the **backend** web tier.
- The redirect route is host-agnostic: a request for `go.acme.com/abc` looks up `(domain, key)`.
- Geo targeting and analytics read the visitor country/city from CDN headers (`CF-IPCountry`, `CF-IPCity`, `CF-Region`, or Vercel's `x-vercel-ip-*`).
  Put the backend behind Cloudflare/Vercel (or add equivalent headers at your edge) to enable them.

## Cache
Use Redis for cache + sessions + queue. Only **plain arrays** may be cached — Laravel 13 will not unserialize arbitrary classes
(`config/cache.php → serializable_classes = false`). The test-suite runs on the Redis store specifically to catch violations.

## Data lifecycle
- Event tables are range-partitioned by month; `events:partitions` (daily) creates upcoming partitions. A DEFAULT partition catches anything else.
- Retention is enforced at read time by plan. To reclaim disk, `DROP TABLE click_events_YYYY_MM` for months older than your longest plan's retention
  (then `php artisan rollup:rebuild` is **not** needed: the daily roll-up keeps totals).
- `php artisan rollup:rebuild [--from --to]` recomputes `analytics_daily` from raw events (backfill/repair).
- `php artisan usage:reset` rolls monthly usage counters (scheduled hourly).

## Plans
There is no self-serve billing. Operators change plans with `php artisan workspace:set-plan <slug> <free|business|advanced|enterprise>`.
Quotas (links/month, events/month, domains, folders, users, rate limit) and feature gates apply immediately.

## Security checklist
API keys are stored as SHA-256 hashes and shown once; webhook secrets are encrypted at rest and requests are HMAC-signed
(`Shortie-Signature: t=…,v1=…`); webhook targets must be public https URLs (re-checked at send time, redirects not followed);
passwords on links are bcrypt-hashed with HMAC unlock cookies; CSV exports neutralise spreadsheet formulas; login is throttled;
every tenant-scoped query goes through workspace-scoped route binding (cross-tenant ids are 404s).
