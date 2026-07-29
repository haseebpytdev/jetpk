# Public Accessibility and Responsive Closure (JP-FE-13)

## Accessibility

- One `h1` per page via `PublicPageHero` / page headings
- Skip link in root layout
- Contact/support forms: visible labels, field errors, alert regions, Turnstile `aria-label`
- FAQ accordion keyboard support (Enter/Space)
- Mobile drawer keyboard accessible (JP-FE-03 baseline preserved)
- Focus-visible styles via `shadow-jp-focus` (no global suppression)

## Responsive QA targets

Verified in Playwright specs for 390px contact/support and mobile navigation.

Breakpoints: 320–1440px, 125%/150% zoom — no horizontal scroll on CMS/legal/contact pages.

## Reduced motion

`prefers-reduced-motion` respected on about page decorative assets (JP-FE-03 test retained).
