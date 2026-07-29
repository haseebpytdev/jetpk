# Homepage and Compact Search Visual Contract (JP-UI-03)

## Scope

Canonical homepage hierarchy and compact hero-integrated flight search for JetPakistan Next.js public frontend.

## Section order

1. Shared public header (JP-UI-02 `SiteHeader`)
2. Full-bleed hero (`PublicHero`)
3. Hero title/subtitle (CMS `hero.*`)
4. Compact search (`SearchModule layout="compact"`)
5. Trust/benefit strip (`BenefitStrip` from `trust_chips`)
6. Decorative flight path (`AnimatedFlightPath`)
7. Trending routes (`RoutesSection` from CMS `routes`)
8. Featured deals (`FeaturedOffersSection` from CMS `featured_deals`, hidden when empty)
9. Why JetPakistan (`WhyJetPakistanSection` from CMS `why_book`, hidden when empty)
10. Support banner (`PublicSupportBanner` from CMS `support_cta`, hidden when empty)
11. Shared footer (JP-UI-02 `SiteFooter`)

Travel inspiration is **not** rendered without an authoritative CMS collection (no production fixtures).

## Compact search

- Entry: `features/search/components/SearchModule.tsx` with `layout="compact"`
- Desktop ≥1280px: one-row primary controls for One Way and Return
- Tabs: One Way, Return, Multi-City, Group Ticketing (operational)
- Preserves validation, autocomplete, options, Laravel handoff
- `data-search-layout="compact"` for tests

## Content API

`GET /api/public/content/homepage` via `HomepageContentService`.

Production: no fixture fallback. Test/preview: `OTA_ALLOW_SESSION_FIXTURE` or `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES`.

## Hero media

- CMS `hero_background` asset when published
- Local fallback: `/images/home/hero-fallback.svg` via `ImageSlot`
- No mockup screenshot backgrounds
