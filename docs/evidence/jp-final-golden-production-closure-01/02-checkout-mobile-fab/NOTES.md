# 02 — Checkout / mobile FAB notes

Date: 2026-09-16  
Scope: FINAL GOLDEN CLOSURE — mobile FAB + sticky CTA clearance (no deploy)

## Gaps found

1. `frontend/app/globals.css` was missing the golden JP-ASK-AI-FAB-03 dock rules (`--base` / `--lift` bottoms with `env(safe-area-inset-bottom)`, hide dock while Ask panel open, panel open animation). Without `--base` bottom, `jp-public-fab-dock` had no vertical position on mobile.
2. `MobileStickyAction` lacked right/bottom safe-area padding, so sticky checkout Continue could sit under the Ask/public FAB stack (golden used `pr-[max(4.5rem,…)]` + `pb-[max(0.75rem,env(safe-area-inset-bottom))]`).
3. Homepage search card lacked `overflow-visible` / `min-w-0`, which can clip compact date/airport UI on narrow viewports.
4. `PublicHero` used section `overflow-hidden`, which can clip mobile heading / overlapping search card vertically.

## Fixes applied (scoped)

| File | Change |
|------|--------|
| `frontend/app/globals.css` | Restored FAB dock keyframes + `--base`/`--lift` + ask-open hide |
| `frontend/features/booking-layout/components/MobileOrderSummary.tsx` | FAB-safe sticky CTA padding |
| `frontend/features/search/components/SearchModule.tsx` | `min-w-0 max-w-full overflow-visible` |
| `frontend/features/public-visual/hero/PublicHero.tsx` | `overflow-x-hidden` on section; image layer keeps `overflow-hidden`; h1 `break-words` + slightly looser leading |
| `frontend/features/search/components/DateField.tsx` | `min-w-0 max-w-full box-border` on native date input |

Unchanged by design: `PairReturnCard` / `use-flight-results` / `FlightResultsPage`; Ask chat module CSS already used `--jp-ask-fab-bottom` + safe-area.

## Regression

```bash
node frontend/tests/regression/jp-favicon-fab-closure.test.mjs
```

Result: all PASS (favicon + FAB dock class/safe-area + sticky padding assertions).

## Residual gaps

- No live Playwright collision proof in this pass (static contract only). Re-run golden `jp-ask-fab-collision-03` / mobile visual against a running Next server when available.
- CMS `favicon_url` is not wired via `generateMetadata` yet (static `/favicon.ico` only); see `03-favicon-perf/FAVICON.md`.
- Search Flights CTA vs FAB overlap on the homepage is mostly vertical stack (search is mid-page); residual risk is only when the submit button is near the bottom of the viewport while scrolling — not re-verified in browser here.
