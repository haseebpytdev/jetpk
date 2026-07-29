# Public SEO, Sitemap, Robots, and Structured Data (JP-FE-13)

## Metadata

- `publicSeoToMetadata()` maps Laravel `PublicSeo` to Next `Metadata` including canonical, Open Graph, and Twitter cards.
- Canonical URLs must be same-site; external canonical values are ignored.
- Private routes use `noIndexMetadata()` where applicable (dashboard, booking flows).

## Sitemap

- **Laravel authoritative inventory:** `GET /api/public/content/sitemap-routes` and `GET /sitemap.xml`
- **Next.js:** `app/sitemap.ts` consumes Laravel routes; falls back to core static paths if API unavailable
- Excludes dashboard, booking, payment, and authenticated routes

## Robots

- **Next.js:** `app/robots.ts` — production allows public paths, disallows `/customer`, `/agent`, `/booking`, etc.; non-production disallows all
- **Laravel static:** `public/robots.txt` updated with private path disallows and sitemap reference

## Structured data

- `SeoJsonLd` in public layout emits `TravelAgency` + `WebSite` JSON-LD from `PublicConfigService` / contact resolver
- No fake reviews, ratings, or booking data
