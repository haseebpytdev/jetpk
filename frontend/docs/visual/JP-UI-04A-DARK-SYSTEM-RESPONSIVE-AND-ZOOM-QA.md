# JP-UI-04A Dark, System, Responsive, and Zoom QA

Phase: JP-UI-04A | Command: `npm run audit:visual:jp-ui-04a`

## Theme matrix

All applicable families include explicit **light**, **dark**, **system-light**, **system-dark**. Theme verified via `data-theme`, `ThemeSwitch`, and `themeStorageValue()` init script.

## Viewport matrix

- Desktop: 1440, 1280, 1024
- Tablet: 768
- Mobile: 390, 375, 320

## Zoom matrix

- 125% and 150% at 1280 width (results, fare, passengers, review, payment, success families)

## Overflow / hydration / errors

- 120/120 scenarios: `overflowOk=true`, zero hydration warnings, zero unhandled page errors

## Reduced motion

Representative results route at 390px with `prefers-reduced-motion: reduce` — filter drawer functional, no horizontal overflow.

## Dark-theme QA

Semantic tokens used throughout; selected filters, fare cards, progress steps, payment cards, and status badges remain legible in dark captures.
