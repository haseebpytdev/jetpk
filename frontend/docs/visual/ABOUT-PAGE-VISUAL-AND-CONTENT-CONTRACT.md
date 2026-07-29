# About Page Visual and Content Contract (JP-UI-03)

## Layout

- Breadcrumbs
- Two-column hero: CMS hero copy + decorative `AnimatedFlightPath`
- CMS sections via `ContentSection` / `ContentCardGrid`
- Optional contact card from Laravel contact resolver
- Gradient CTA band when CMS CTA configured

## Content source

`PublicPageService.getAboutPage()` → `GET /api/public/content/pages/about`

Fixtures only when `allowContentFixtures()` is true.

## Accessibility

- One `h1` from CMS hero title
- Logical section headings from CMS blocks
