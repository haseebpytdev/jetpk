# JP-OPS-07 Admin/Staff Operational Parity Closure

**Branch:** `phase/jetpk-ops-07-admin-staff-parity-closure`
**Baseline:** `d97c33e72061ff164a1760c826af14aaad4e8e2c`
**Status:** READY FOR JP-OPS-07 COMMIT (pending human review; no commit in phase)

## Objective

Close mandatory cancel/refund review Next bindings and inventory-driven core Admin/Staff operational parity; reconcile all 159 canonical mutations.

## Mutation reconciliation

| Class | Count |
|-------|------:|
| CONNECTED | 50 |
| INTENTIONAL_BLADE_FALLBACK | 25 |
| DEFERRED_TO_JP-UX-CMS-01 | 65 |
| DEFERRED_TO_JP-RUNTIME-01 | 19 |
| **Total** | **159** |

Duplicate classifications: 0 · unclassified: 0 · missing routes: 0 · invented routes: 0

## Baseline hydration exception (JP-DASH-HYDRATION-01)

A/B comparison on approved baseline `d97c33e72061ff164a1760c826af14aaad4e8e2c` (live + mock production build):

| Gate | Passed | Failed | Skipped | Exit |
|------|--------|--------|---------|------|
| Hydration `--repeat-each=5` | 38 | 7 | 0 | 1 |
| Cluster (hydration + audit + cms) `--repeat-each=3` | 200 | 4 | 0 | 1 |

Baseline hydration failures: intermittent React **#418** (`args[]=HTML`) on `/admin/dashboard/payments`, `/agents`, `/pnrs`, `/bookings`. Baseline cluster also failed audit nav link smoke (3×) and hydration agents (1×).

**Conclusion:** intermittent hydration instability and the baseline Audit navigation smoke failure **predate JP-OPS-07**. Hydration-isolation experiments were **removed** from this branch. Full dashboard smoke / hydration gates are **deferred** to **JP-DASH-HYDRATION-01**. This phase does **not** claim the complete dashboard smoke suite is green.

## Hydration experiments removed (operational-only finalization)

Reverted to baseline or deleted: deleted `loading.tsx` files (restored), `app/layout.tsx` / `globals.css` theme/preview refactor, `middleware.ts` preview headers, `ThemeProvider` SSR experiments, `DashboardPreviewProvider`, `data-source-preview` helpers, fixture service `setTimeout` removals, stable-sort-only filter tweaks, hydration spec/helper edits, diagnostic theme files.

Retained operational-only dashboard changes: `nav-config`, `portal-paths`, `operational-api`, operational workspaces/panels, `use-dashboard-live-mode`, `use-runtime-live-mutations-enabled` (JP-OPS-06 execution contract), session fixture navigation for operational routes.

## Tests executed (operational gates)

| Suite | Result |
|-------|--------|
| Laravel BackOffice closure cluster | **43 passed**, 0 failed, **165 assertions**, exit 0 |
| JP-OPS-05 admin/staff regression | 4 Playwright + Node suites, exit 0 |
| JP-OPS-06 admin/staff regression | 10 Playwright + Node suites, exit 0 |
| JP-OPS-07 runtime-linkage | PASS, exit 0 |
| JP-OPS-07 operational Playwright | **5 passed**, exit 0 |
| JP-OPS-07 connected-mutations Playwright | **17 passed**, exit 0 |
| dashboard typecheck | PASS, exit 0 |
| dashboard lint | PASS, exit 0 |
| dashboard build (live + mock) | PASS, exit 0 |

**Not required for JP-OPS-07 commit:** hydration `--repeat-each=5`, full `npm run test:smoke` (pre-existing baseline defects).

## Frontend regression

FRONTEND REGRESSION NOT RE-RUN — FRONTEND PRODUCTION CODE UNCHANGED

## Known limitations

- Core UI uses fixture row IDs for finance/agency/support live actions until read APIs supply queue rows.
- Agency activate/suspend routes absent from 159 inventory (documented, not invented).
- Intermittent React #418 hydration under production `next start` remains a **pre-existing** defect tracked under JP-DASH-HYDRATION-01.

## Recommended next phase

- **JP-DASH-HYDRATION-01** — scoped dashboard hydration stability (baseline-confirmed pre-existing).
- Human decision: whether JP-OPS-07 operational commit may proceed with hydration/smoke defects separated from CONNECTED closure.
