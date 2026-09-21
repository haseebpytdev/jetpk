# Public SEO, Sitemap, Robots, and Structured Data (JP-FE-13)

## Metadata

- `publicSeoToMetadata()` maps Laravel `PublicSeo` to Next `Metadata` including canonical, Open Graph, and Twitter cards (titles use `absolute` to avoid `| Brand` duplication).
- Canonical URLs must be same-site; external canonical values are ignored.
- Private / utility routes use `noIndexMetadata()` with optional `path` + `follow` (lookup-booking, groups/search).

## Sitemap

- **Laravel authoritative inventory:** `GET /api/public/content/sitemap-routes` and `GET /sitemap.xml`
- **Next.js:** `app/sitemap.ts` consumes Laravel routes; falls back to core static paths if API unavailable
- Excludes `/contact`, `/flights`, `/lookup-booking`, `/groups/search`, dashboards, booking, payment
- Includes core marketing paths plus `/groups` and HTML `/sitemap` when eligible

## Robots

- **Next.js:** `app/robots.ts` — production allows public paths, disallows private/utility prefixes; non-production disallows all
- **Laravel static:** `public/robots.txt` kept in sync with Next production disallow list

## Structured data

- `SeoJsonLd` in public layout emits `TravelAgency` + `WebSite` JSON-LD from `PublicConfigService` / contact resolver
- `FaqJsonLd` on `/faq` emits `FAQPage` only when visible Q&A exist
- No fake reviews, ratings, or booking data

## Recovery audit

See `docs/closure/JETPAKISTAN_SEO_RECOVERY_AUDIT.md` and `docs/closure/SEO-AEO-GEO/`.
