# Public Route Ownership and No-Fallback Contract (JP-FE-13)

## Next.js owns (presentation)

`/`, `/about-us`, `/contact`, `/support`, `/faq`, `/terms`, `/privacy`, `/pages/[slug]`, `/[slug]` (custom CMS), `/legal/[slug]`, `/sitemap`, `/lookup-booking`, auth pages, booking/checkout flows, group ticketing public routes.

## Laravel owns (authoritative / mutations)

- All `/api/public/content/*` JSON
- `POST /support` (contact + support)
- `GET /sitemap.xml` (XML)
- Signed/guest booking access, payments, webhooks, downloads
- `/customer`, `/agent`, `/dashboard`, admin/staff routes

## Intentional Blade retention

- Operational Laravel routes for payments, guest tokens, supplier callbacks
- Blade fallback via `/laravel/*` rewrite when Next widget unavailable

## No accidental fallback

- Production does not inject fixture legal/copy when CMS empty
- Navigation dead links removed (hotels/offers/careers placeholders)
- `/manage-booking` → `/lookup-booking` in nav and content mapper

## Nginx intent (documented, not deployed)

- Next serves public marketing and app shell
- `/laravel/*` proxies to Laravel for forms, APIs, signed routes
- Laravel retains webhooks, payment callbacks, document downloads
