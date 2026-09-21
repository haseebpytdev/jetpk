# SEO / AEO / GEO — Public route inventory

**Source:** `SeoManagedPageCatalog`, `PublicContentApiPresenter::sitemapRoutes()`, `frontend/app/(public)/**`, `ReservedPublicPath`  
**Date:** 2026-09-21

| Path | Class | Index | Sitemap | Owner | Notes |
|---|---|---|---|---|---|
| `/` | SEO | yes | yes | managed home | CMS SEO authoritative |
| `/about-us` | SEO | yes | yes | managed about | Canonical about; `/contact` redirects here |
| `/support` | SEO | yes | yes | managed support | Contact form lives here |
| `/faq` | SEO | yes | yes | managed faq | Needs FAQPage JSON-LD |
| `/terms` | SEO | yes | yes | managed terms | |
| `/privacy` | SEO | yes | yes | managed privacy | |
| `/sitemap` | SEO | yes | no* | Next HTML hub | Indexable HTML; XML is `/sitemap.xml` (*not in Laravel route list today) |
| `/pages/{slug}` | SEO | yes† | yes† | CMS | †when eligible |
| `/{slug}` | SEO | yes† | yes† | custom client page | Reserved segments blocked |
| `/contact` | redirect | no | no | protected | 308 → `/about-us` KEEP |
| `/groups` | SEO / discovery | yes‡ | no‡ | groups landing | ‡add metadata; decide sitemap in WP4 |
| `/groups/search` | transactional | no | no | utility | noindex,follow |
| `/groups/{packageId}` | transactional | no | no | package detail | treat as app |
| `/groups/booking/*` | transactional | no | no | group checkout | |
| `/lookup-booking` | transactional | no | no | utility | noindex,follow |
| `/flights`, `/flights/*` | transactional | no | no | search/results | `/flights` → `/` |
| `/booking/*` | transactional | no | no | checkout | |
| `/guest/bookings/*/access/*` | share | no | no | guest token | long token; short share MISSING |
| `/customer/*`, `/agent/*`, `/admin/*`, `/staff/*` | private | no | no | portals | robots disallow |
| `/login`, `/forgot-password`, … | auth | no | no | auth | robots disallow |
| `/api/*`, `/dev/*`, `/ui/*`, `/laravel/*` | internal | no | no | infra | robots disallow |

## Non-indexable (must never appear in sitemap)

Traveler, review, checkout, payment, dashboards, private booking status, voucher tokens, internal search-session URLs, `/lookup-booking`, `/groups/search`, `/contact` alias.

## Short-URL target shapes (WP7 — not live)

| Class | Target shape | Status |
|---|---|---|
| SEO | short human paths (`/about`, `/faq`, corridors) | MISSING (today `/about-us`) |
| Transactional | `/flights/s/{ref}`, `/b/{ref}`, `/g/{ref}` | MISSING |
| Share | short signed/expiring refs | MISSING (`PublicShareLink` unmerged) |
