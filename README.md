# Shortie

A production-oriented clone of [dub.co](https://dub.co): a modern **link attribution platform** — branded short links,
real-time click/lead/sale analytics, conversion tracking, webhooks and a REST API.

| | |
|---|---|
| **Backend** | Laravel 13 · PHP 8.3 · PostgreSQL 16 · Redis 7 · Sanctum |
| **Frontend** | Next.js 16 (App Router) · React 19 · Tailwind v4 · TanStack Query · Recharts |
| **Tests** | 309 PHPUnit tests (Redis-cache + Postgres, no mocks of the data layer) · 26 Vitest unit tests · 8 Playwright end-to-end tests |

## Documentation
1. [`docs/01-ANALYSIS.md`](docs/01-ANALYSIS.md) — page-by-page and feature analysis of dub.co (what we studied and the scope we chose)
2. [`docs/02-PLAN.md`](docs/02-PLAN.md) — architecture, data model, indexing strategy and delivery plan
3. [`docs/03-BACKEND-NOTES.md`](docs/03-BACKEND-NOTES.md) — what was built, performance decisions and **measured benchmarks on 1M events**
4. [`docs/04-OPERATIONS.md`](docs/04-OPERATIONS.md) — running it in production

## Features
- **Links** — create / edit / duplicate / archive / bulk-manage; custom domains with DNS verification; tags, folders, UTM templates; QR codes;
  password, expiry, geo + iOS/Android targeting, A/B tests (sticky per visitor), cloaking, custom social previews; upsert + bulk API.
- **Redirect engine** — Redis-cached (zero SQL on repeat hits), bot-aware, write-behind click buffer.
- **Analytics** — clicks / leads / sales; timeseries; 15 breakdown dimensions; include/exclude filters; keyset-paginated events stream; CSV export; password-protected public dashboards.
- **Conversions** — idempotent `track/lead` and `track/sale`, customers with first-touch attribution and lifetime value.
- **Platform** — workspaces, roles, invites, API keys (hashed), signed + retried webhooks (SSRF-guarded), plan quotas & per-plan rate limits.
- **Marketing site** — home, links, analytics, pricing; auth; full dashboard UI with settings.

## Quick start
```bash
docker compose up -d                     # Postgres 16 (+ test DB) and Redis 7

cd backend
cp .env.example .env && composer install && php artisan key:generate
php artisan migrate --seed --seeder=DemoSeeder   # demo@shortie.test / password
php artisan serve                                 # API + short-link runtime  → http://127.0.0.1:8000
php artisan queue:work &                          # webhooks
php artisan schedule:work &                       # click flush (10 s), partitions, usage reset

cd ../frontend
cp .env.example .env.local && npm install
npm run dev                                       # → http://localhost:3000
```
Short links are served by the backend on any domain pointed at it (`shortie.link` by default — e.g. `curl -H 'Host: shortie.link' http://127.0.0.1:8000/<key>`).

## Tests
```bash
cd backend  && php artisan test                          # 309 tests
cd frontend && npm run typecheck && npm run lint && npm test && npm run build
cd frontend && npm run e2e                               # needs both servers running (see playwright.config.ts)
```

## Repository layout
```
backend/    Laravel API + redirect runtime   (Actions/ Services/ Support/ thin Controllers)
frontend/   Next.js app                      (components/<domain>/  lib/{api,hooks,utils,validation}/)
docs/       analysis, plan, notes, operations
```

## Scope notes
Built: everything above. Deliberately **not** built (documented in `docs/01-ANALYSIS.md`): the Partners/affiliate-payout product,
SAML/SSO, Stripe billing (plans are set with `php artisan workspace:set-plan`), registered-domain purchase, Ask-AI, third-party integrations.
