# JP-UI-06 Geometry Measurement and Tolerance Contract

Phase: **JP-UI-06**  
Geometry source: `frontend/tests/visual-audit/jp-ui-06-blueprint-geometry.json`

## Measurement method

1. Normalize mockup to 1122×1330 (`normalize-jp-ui-06-references.mjs`).
2. Capture implementation at the same viewport (`jp-ui-06-blueprint.spec.ts`).
3. Record DOM bounding boxes for header, main, footer, search panel, progress, order summary (`jp-ui-06-helpers.ts`).
4. Compare DOM boxes against curated blueprint landmarks (`compare-jp-ui-06.mjs`).

## Default tolerances (§7)

| Severity | Width/height delta | X/Y delta | Gate |
|----------|-------------------|-----------|------|
| Critical | > tolerance + 2px on ≥4 landmarks | > tolerance + 4px on header/footer | `verify-jp-ui-06.mjs` fail |
| High | > tolerance on any primary landmark | > tolerance on sidebar/main split | Manual review |
| Medium | Within tolerance but diffRatio > 15% | — | Evidence index |
| Low | Cosmetic glyph/interior only | — | Informational |

Default landmark tolerance: **2px**. Header/footer/hero bands: **3px**.

## Landmark ownership

| Landmark | Measured selector | Pages |
|----------|-------------------|-------|
| header | `header` | All public + booking |
| search-panel | `[data-testid='search-module']` | homepage |
| progress | `[data-testid='booking-progress']` | booking journey |
| order-summary | `[data-testid='order-summary']` | checkout |
| sidebar ratio | filter panel / main column grid | flight-results, booking layout |

## Capability exception (seat family)

`seat-selection-capability-unavailable` is **not** scored against seat-map mockup pixels. Geometry gates apply only to shared checkout chrome (header, progress, passenger form shell). High pixel diff against the seat-map reference is expected and suppressed in verify.

## Operational substitution families

| Family | Mode | Substituted region |
|--------|------|-------------------|
| fare-selection | `exact_with_operational_substitution` | Progress step labels/order (exception A) |
| payment | `exact_with_operational_substitution` | Card-details region → AbhiPay handoff (exception C) |

Surrounding measured regions remain subject to exact geometry gates.
