# JP-UI-04A Complete Booking Visual Matrix

Phase: **JP-UI-04A**  
Command: `npm run audit:visual:jp-ui-04a` (from `frontend/`)  
Artifacts: `frontend/.visual-audit/jp-ui-04a/` (**gitignored**)  
Committed summary: `frontend/docs/visual/jp-ui-04a-capture-result.json`

## Root cause (JP-UI-04 evidence gap)

JP-UI-04 used `jp-ui-04-scenarios.ts` with **28 hand-written scenarios** (layout/theme smoke only). No declarative registry enforcing 120 scenarios; no full theme × viewport × zoom × operational-state matrix; payment/success/review variants and interaction states omitted; manifest did not gate on expected count.

## Corrected architecture

| Layer | File |
|-------|------|
| Scenario registry | `tests/visual-audit/jp-ui-04a-scenarios.ts` (120 scenarios, duplicate-ID + family-count guards) |
| Deterministic fixtures | `tests/visual-audit/jp-ui-04a-fixtures.ts` |
| Capture helpers | `tests/visual-audit/jp-ui-04a-helpers.ts` |
| Serial matrix spec | `tests/visual-audit/jp-ui-04a-visual-matrix.spec.ts` |
| Capture orchestrator | `scripts/capture-jp-ui-04a.mjs` |
| Manifest verifier | `scripts/verify-jp-ui-04a-manifest.mjs` |
| Targeted state specs | `tests/jp-ui-04a-*.spec.ts` (8 files) |

Reuses JP-UI-03A patterns: `themeStorageValue()`, overflow assertion, hydration/page-error monitors, production Playwright server.

## Expected scenario count: **120**

| Family | Count |
|--------|------:|
| Results | 38 |
| Fare selection | 16 |
| Passengers | 15 |
| Seat capability (unsupported) | 4 |
| Review | 14 |
| Payment | 17 |
| Success | 16 |
| **Total** | **120** |

## Final run (2026-07-30)

| Metric | Value |
|--------|------:|
| Expected | 120 |
| Actual | 120 |
| Passed | 120 |
| Failed | 0 |
| Skipped | 0 |
| Screenshots | 120 |
| Duration | 387s |
| Overflow failures | 0 |
| Hydration failures | 0 |
| Page-error failures | 0 |

## Seat selection classification

`seat_map_available: false` — Seats step omitted from progress; **unscored** against seat mockup; classified as future conditional target when Laravel enables seat maps.
