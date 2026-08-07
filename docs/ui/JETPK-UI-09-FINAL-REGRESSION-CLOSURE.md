# JETPK-UI-09 — Final Responsive, Accessibility and Visual Regression Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-09 |
| Branch | `phase/jetpk-ui-09-final-regression-closure` |
| Baseline | `8e0ce75` (post UI-08 merge) |
| Gaps | JETPK-UI-001, 011, 012, 013, 017, 018, 020 |
| Deployment | NOT PERFORMED |

## Changes

- Production smoke server enables `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` for honest homepage sections when Laravel is offline.
- Airport combobox keyboard navigation starts unhighlighted; first ArrowDown selects index 0.
- Dashboard Playwright uses `scripts/playwright-server.mjs` with build-id guard; `test:smoke` runs build first.
- New regression specs: `frontend/tests/jetpk-ui-09-final-regression.spec.ts`, `dashboard/tests/jetpk-ui-09-regression.spec.ts`.

## Gap closure

| Gap | Status |
|-----|--------|
| JETPK-UI-001 | **CLOSED** — production preview renders without client exception |
| JETPK-UI-011 | **CLOSED** — keyboard airport selection commits IATA |
| JETPK-UI-012 | **CLOSED** — homepage hero/search/sections on production smoke |
| JETPK-UI-013 | **CLOSED** — dashboard smoke webServer deterministic with build guard |
| JETPK-UI-017 | **CLOSED** — 768×1024 matrix in UI-09 specs |
| JETPK-UI-018 | **CLOSED** — dark theme portal + dashboard matrix |
| JETPK-UI-020 | **CLOSED** — 360×800 matrix in UI-09 specs |

**Remaining open gaps:** 0

## Tests

- `frontend/tests/homepage.spec.ts`
- `frontend/tests/jetpk-ui-09-final-regression.spec.ts`
- `dashboard/tests/jetpk-ui-09-regression.spec.ts`
- `dashboard/tests/overview.smoke.spec.ts` (subset via test:smoke gate)

## Final status

**PASS** — homepage/UI-09 regression green; content-policy smoke gate verified; dashboard UI-09 regression 18/18.
