# Shortie — Product Analysis of dub.co

Source: dub.co marketing site (home, /links, /analytics, /partners, /pricing), Dub API reference
(`POST /links` schema + error model), captured 2026-10-18. Dub's product has three pillars
(**Links**, **Analytics/Conversions**, **Partners**) plus platform concerns (API, webhooks, workspaces, billing).

## 1. Page inventory

### 1.1 Marketing site (public)
| Page | Purpose | Key sections |
|---|---|---|
| `/` Home | Positioning: "Turn clicks into revenue" | Hero + CTAs (Start free / Get a demo), pillar tabs (Partners / Analytics / Links), logo wall, link-builder + domains + QR mini-demos, analytics funnel demo, integrations strip, partners demo, "built to scale" counters, API/SDK code tabs, testimonials, changelog teaser, final CTA, footer |
| `/links` | Links product | Hero with inline "shorten link" input, branded links, custom link preview, custom domains, UTM builder/templates, deep links, folders+tags, geo/device targeting, QR, A/B tests, analytics teaser, API endpoint list, SDK tabs |
| `/analytics` | Analytics product | Real-time analytics, share dashboard, devices/browsers/countries, date range, CSV export, filters, "Ask AI", conversion funnel, customer insights, integrations (Stripe, GTM, Segment, Zapier, Shopify, Slack), real-time events stream, API + webhooks |
| `/partners` | Affiliate programs | Reward structures, dual-sided incentives, referral rewards, payouts, tax compliance, invoicing, AI landing page, embedded dashboard, bounties, partner discovery |
| `/pricing` | Plans | Links plans vs Partners plans toggle, monthly/yearly toggle, comparison table, FAQ |
| `/changelog`, `/blog`, `/customers`, `/about`, `/careers`, `/brand`, `/contact`, `/privacy`, `/terms`, `/help`, `/docs`, `/integrations`, `/enterprise`, `/startups`, `/compare/*` | Content/support | Static or CMS content |
| `/help`, `/docs` | Help center + API docs | Articles + OpenAPI reference |

### 1.2 Auth
Login (email+password, magic link, Google/GitHub/SSO), Register, Forgot/Reset password, Email verification, Onboarding (workspace name → slug → domain/free link → invite teammates).

### 1.3 Dashboard (`app.dub.co/[workspace]`)
| Area | Features |
|---|---|
| **Links** | Table/card list; search; filters (domain, tag, folder, user, archived, "show archived"); sort (date/clicks/saleAmount); pagination; bulk select → archive/delete/move/tag; link builder modal (destination, domain, key, tags, folder, comments, QR, custom preview, UTM, targeting, expiration, password, cloaking, deep links, A/B test, conversion tracking, indexing); duplicate; transfer; quick-create (`C` shortcut); import (CSV/Bitly/Short.io); export CSV; link-level stats page |
| **Analytics** | KPI tabs (Clicks / Leads / Sales); time-series chart (hourly/daily/monthly); date range presets (24h, 7d, 30d, 3m, 12m, YTD, all, custom); top lists: links, URLs, countries, cities, regions, continents, devices, browsers, OS, referrers, referrer URLs, UTM source/medium/campaign/term/content, top tags, folders, triggers (QR vs link); filter pills (`is`/`is not`, multi-value); compare; Ask-AI query; CSV export; public share dashboard (`/share/dash_xxx`, optional password, noindex toggle); unique-vs-total clicks |
| **Events** | Real-time stream of click/lead/sale events with same filters, pagination, CSV export |
| **Customers** | Customer list/search, detail (journey: click→lead→sale, LTV, UTM, country, payments timeline) |
| **Domains** | Add/verify (DNS A/CNAME), primary, placeholder (root redirect), not-found URL, expired URL, archive, delete, free `.link` domain claim, registered-domain purchase |
| **Settings → Tags** | CRUD tags with colors |
| **Folders** | CRUD, access levels (read/write/no-access) per user, default folder |
| **UTM templates** | CRUD reusable UTM presets |
| **Workspace settings** | General (name, slug, logo, delete), People (members, roles, invites), Billing (plan, usage, invoices, upgrade/cancel), Domains, Library (tags, UTM templates), Integrations (Slack, Segment, Zapier, Stripe, Shopify, GTM), Webhooks, API keys, OAuth apps, Security (SAML/SSO), Audit logs, Notifications |
| **Account settings** | Profile (name, email, avatar), password, API tokens, delete account |
| **Partners** (phase 2) | Program, partners, groups, rewards, payouts, commissions, applications, bounties, messages, campaigns, landing page |

### 1.4 Public / runtime surfaces
| Surface | Behaviour |
|---|---|
| Redirect `/{key}` on custom domain | Lookup → checks (disabled/archived, expired, password, geo, device, iOS/Android, A/B variant) → 302 (+ UTM/ref params, query passthrough) → record click async |
| Link preview/proxy page | Bots (Twitterbot, LinkedIn, Slackbot…) get an HTML page with custom OG title/description/image/video when `proxy=true` |
| Cloaked page | `rewrite=true` serves destination in an iframe/proxy |
| Password page | Form posting password, sets signed cookie |
| Expired / not-found / banned pages | Configurable per-domain fallback URL |
| Stats `/stats/{key}` | Public analytics when `publicStats=true` |
| Share dashboard `/share/{id}` | Read-only analytics |
| QR `/qr?url=` | Customisable SVG/PNG QR (logo, colors) |
| Tracking | `POST /track/lead`, `/track/sale`, `/track/open` + client JS snippet (`dub_id` cookie, click id) |

## 2. Domain model (derived)

`User`, `Workspace` (plan, usage counters, billing cycle), `WorkspaceUser` (role), `WorkspaceInvite`,
`Domain` (verified, primary, placeholder, notFoundUrl, expiredUrl), `Link` (see §3), `Tag` + `link_tag`,
`Folder` (+ `FolderUser` access), `UtmTemplate`, `ApiKey` (hashed, scopes, last used),
`Webhook` + `WebhookDelivery`, `Customer` (externalId, email, name, avatar, country, linkId, clickId),
**events**: `ClickEvent`, `LeadEvent`, `SaleEvent`, `ShareDashboard` (publicStats link), `AuditLog`.

## 3. Link object (from the official API)

Identity: `id, domain, key, url, shortLink, qrCode, externalId, tenantId, programId, partnerId`.
Behaviour: `archived, expiresAt, expiredUrl, disabledAt, password, proxy(custom preview), rewrite(cloaking), doIndex, trackConversion, publicStats`.
Preview: `title, description, image, video`. Targeting: `ios, android, geo{country:url}`.
UTM: `utm_source|medium|campaign|term|content, ref`. Organisation: `tags[], folderId, comments`.
A/B: `testVariants[{url,percentage}], testStartedAt, testCompletedAt`. Webhooks: `webhookIds[]`.
Counters: `clicks, leads, conversions, sales, saleAmount, lastClicked`. Meta: `userId, workspaceId, createdAt, updatedAt`.
Create-only helpers: `keyLength, prefix, tagIds, tagNames`.

**API surface**: links (create, update, upsert, delete, retrieve, list, count, bulk create/update/delete),
analytics (`groupBy` count/timeseries/top_*), events (list), folders, tags, domains, customers,
track (lead/sale), webhooks, workspaces. **Errors**: JSON `{error:{code,message,doc_url}}` with codes
`bad_request, unauthorized, forbidden, not_found, conflict, invite_expired, unprocessable_entity, rate_limit_exceeded, internal_server_error`.
**Rate limits** per plan (1,200/min Business, 3,000/min Advanced).

## 4. Plans & quotas (from /pricing) — modelled as config
| | Free (our addition) | Business | Advanced | Enterprise |
|---|---|---|---|---|
| New links / month | 25 | 10K | 50K | ∞ |
| Tracked events / month | 1K | 250K | 1M | ∞ |
| Retention | 30d | 3y | 5y | ∞ |
| Folders | 0 | 20 | 50 | ∞ |
| Custom domains | 3 | 100 | 250 | ∞ |
| Users | 1 | 10 | 20 | ∞ |
| API rate limit / min | 60 | 1,200 | 3,000 | custom |
Gated features: A/B testing, password, device/geo targeting, cloaking, expiration, deep links, custom previews, webhooks → Business+.

## 5. Non-functional requirements observed
- Redirect latency target <50 ms p95 (edge cache); click recording must never block redirect.
- Real-time analytics on millions of events with arbitrary filter combinations.
- Bot-filtered click counting, per-IP dedupe (unique clicks within 1 h window).
- Idempotent tracking (lead/sale by `eventName+customerExternalId` / `invoiceId`).
- Multi-tenant isolation on every query; audit logs; SOC2-style security posture.

## 6. Scope decision for Shortie
**In scope (v1, built here):** auth, workspaces/members/invites, domains, links (full feature set above), tags, folders, UTM templates, QR, redirect engine, click/lead/sale tracking, customers, analytics + events + CSV export + share dashboards, API keys + public REST API, webhooks, plan/quota enforcement, marketing site (home, links, analytics, pricing, login/register), dashboard UI.
**Deferred (documented, schema-ready):** Partners/affiliate payouts, SAML/SSO, Stripe billing integration, registered-domain purchase, Ask-AI, third-party integrations, OAuth apps, importers.
