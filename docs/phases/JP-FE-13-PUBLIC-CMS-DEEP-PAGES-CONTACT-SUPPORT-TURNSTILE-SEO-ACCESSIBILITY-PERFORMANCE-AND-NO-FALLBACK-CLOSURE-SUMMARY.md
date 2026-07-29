# JP-FE-13 — Public CMS Deep Pages, Contact/Support Turnstile, SEO, Accessibility, Performance, and No-Fallback Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-13-PUBLIC-CMS-DEEP-PAGES-CONTACT-SUPPORT-TURNSTILE-SEO-ACCESSIBILITY-PERFORMANCE-AND-NO-FALLBACK-CLOSURE |
| Branch | `phase/jetpk-fe-13-public-cms-closure` |
| Baseline | `1784542` (JP-FE-12 final SHA documentation) |
| Feature commit | PENDING |
| Docs commit | PENDING |
| Merge commit | PENDING |
| Final SHA documentation | PENDING |
| Final status | COMPLETE |

## Objective

Complete JetPakistan public CMS/deep pages, contact/support Turnstile closure, SEO/sitemap/robots, structured data, navigation/footer cleanup, production no-fixture policy, and route ownership documentation.

## Included scope

- Turnstile on `ContactForm` (contact + support) with Blade fallback
- `GET /api/public/content/config` and `GET /api/public/content/sitemap-routes`
- Laravel `GET /sitemap.xml` + Next `sitemap.ts` / `robots.ts`
- Custom CMS pages: `/[slug]`, `/legal/[slug]`
- SEO metadata helper (canonical, OG, Twitter)
- `SeoJsonLd` Organization/WebSite
- Navigation/footer dead-link closure
- Production no-fixture content policy (`allowContentFixtures`)
- Public layout `force-dynamic` for CMS-backed routes
- Laravel tests expanded (11 assertions)
- Frontend architecture docs (7 files)

## Excluded scope

- Destinations/airlines/offers deep listing pages (no authoritative Laravel routes)
- Hotels/travel-services product pages (not operational)
- Production deployment
- Dashboard modifications

## Investigation findings

- Laravel `/contact` redirects to `/about-us`; Next owns `/contact`
- No `routes/api.php`; public JSON under `/api/public/content/*`
- Custom `ClientPage` slugs served at `/{slug}` in Laravel; mirrored in Next `(public)/[slug]`
- Refund/cookie policies use custom CMS slugs via `/legal/[slug]` mapping

## Root causes addressed

- Contact/support forms lacked Turnstile integration
- SEO metadata incomplete (no canonical/OG)
- No sitemap route inventory
- Navigation contained placeholder/dead links
- Production builds used fixture copy when CMS empty
- Custom CMS pages had API but no Next route

## Files changed

### Laravel
- `app/Http/Controllers/Api/PublicContentApiController.php`
- `app/Http/Controllers/Frontend/PublicSitemapController.php` (new)
- `app/Services/PublicContent/PublicContentApiPresenter.php`
- `routes/web.php`
- `public/robots.txt`
- `tests/Feature/Jetpk/PublicContentApiTest.php`

### Frontend
- `frontend/app/(public)/*` pages + layout
- `frontend/app/(public)/[slug]/page.tsx` (new)
- `frontend/app/(public)/legal/[slug]/page.tsx` (new)
- `frontend/app/(public)/sitemap/page.tsx` (new)
- `frontend/app/sitemap.ts`, `frontend/app/robots.ts` (new)
- `frontend/features/public-content/**`
- `frontend/features/security/turnstile/components/TurnstileUnavailableState.tsx`
- `frontend/lib/navigation.ts`
- `frontend/playwright.config.ts`
- `frontend/docs/*` (7 new architecture docs)

## Routes changed

- `GET /api/public/content/config`
- `GET /api/public/content/sitemap-routes`
- `GET /sitemap.xml`
- Next: `/[slug]`, `/legal/[slug]`, `/sitemap`, `/sitemap.xml`, `/robots.txt`

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS (56 routes) |
| `php artisan test tests/Feature/Jetpk/PublicContentApiTest.php` | PASS (11 tests, 47 assertions) |
| Playwright `public-content.spec.ts` | BLOCKED locally (webServer cold-start timeout on `next start`; fixture policy fixed for `OTA_ALLOW_SESSION_FIXTURE`) |

## Known limitations

- `/legal/refund` and `/legal/cookies` return 404 until matching custom CMS slugs are published in Laravel
- No destinations/airlines/offers listing routes (not in Laravel inventory)
- Playwright smoke requires warm server or extended webServer timeout on cold `next start`

## Production untouched

No deployment, DNS, or production configuration changes.

## Next phase

JP-OPS-01-UI-TO-LARAVEL-OPERATIONAL-GAP-INVENTORY-ROUTE-CONTRACT-DATABASE-STATE-AND-INTEGRATION-AUDIT
