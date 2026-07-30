# JP-UI-04A — Booking Journey Dark/System/Responsive State Matrix and Final Visual Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-04A-BOOKING-JOURNEY-DARK-SYSTEM-RESPONSIVE-STATE-MATRIX-AND-FINAL-VISUAL-CLOSURE |
| Branch | `phase/jetpk-ui-04a-booking-state-matrix-closure` |
| Baseline | `f558844` (JP-UI-04 main HEAD) |
| Objective | Complete 120-scenario booking-journey visual/state matrix; fix defects exposed by matrix; finalize evidence |

## Evidence gap (JP-UI-04)

JP-UI-04 reported 28/28 visual captures. Required matrix: **120** scenarios across themes, viewports, zoom, loading/error/expiry/validation/payment/success states, focus, reduced-motion, overflow, hydration.

**Root cause:** hand-written `jp-ui-04-scenarios.ts` (28 entries); no JP-UI-03A-style registry; no manifest count gate; fare/payment/success operational variants omitted.

## Harness architecture

- `jp-ui-04a-scenarios.ts` — declarative 120-scenario registry
- `jp-ui-04a-fixtures.ts` — route mocks per family/state
- `jp-ui-04a-helpers.ts` — theme, zoom, overflow, manifest
- `jp-ui-04a-visual-matrix.spec.ts` — serial capture loop
- `capture-jp-ui-04a.mjs` / `verify-jp-ui-04a-manifest.mjs`
- `npm run audit:visual:jp-ui-04a`
- 8 targeted `jp-ui-04a-*.spec.ts` files (40 assertions)

## Visual matrix result

| Metric | Value |
|--------|------:|
| Registered | 120 |
| Executed | 120 |
| Passed | 120 |
| Failed | 0 |
| Skipped | 0 |
| Screenshots | 120 |
| Duration | 387s |
| Overflow | 0 |
| Hydration warnings | 0 |
| Page errors | 0 |

## Defects found and fixed

| Defect | Fix |
|--------|-----|
| Partial supplier failure crashed page | Fixture `warnings` must be `string[]` not objects |
| Layover popover action used wrong accessible name | Use `/layover in/i` aria-label |
| Fare drawer state scenarios waited before drawer open | Move post-drawer waits into `action` |
| Group search fixture missing `airline_name` | Complete group card fixture fields |
| Direct-only filter query param wrong | Use `stops=direct` not `direct_only=1` |
| Confirmation page missing `noindex` | Add `metadata.robots` on confirmation page |

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run audit:visual:jp-ui-04a` | PASS (120/120) |
| JP-UI-04A targeted specs (40) + regressions (43) | PASS (83/83) |
| Laravel tests | Not run — no Laravel changes |

## Production

Production untouched. Backup Safe untouched.

## JP-UI-05 readiness

Booking journey visual evidence complete. JP-UI-05 (login/signup/manage booking/dashboard parity) may proceed.
