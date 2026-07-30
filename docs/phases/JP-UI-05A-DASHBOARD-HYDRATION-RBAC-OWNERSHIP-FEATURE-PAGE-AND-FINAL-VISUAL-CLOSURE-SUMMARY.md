# JP-UI-05A — Dashboard Hydration, RBAC, Ownership, Feature Page and Final Visual Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-05A-DASHBOARD-HYDRATION-RBAC-OWNERSHIP-FEATURE-PAGE-AND-FINAL-VISUAL-CLOSURE |
| Branch | `phase/jetpk-ui-05a-dashboard-hydration-rbac-closure` |
| Baseline | `3eb64ec` (JP-UI-05 final main HEAD) |
| Objective | Correct JP-UI-05 closure gaps: fix dashboard React #418 at source, remove hydration suppression, rerun unfiltered 132-scenario matrix, classify dashboard feature pages, add ownership/RBAC functional evidence |

## Original JP-UI-05 closure gaps

| Gap | JP-UI-05A resolution |
|-----|---------------------|
| React #418 filtered as benign for dashboard captures | `filterBenignPageErrors()` removed; verifier fails on any hydration warning |
| Dashboard hydration mismatch at source | Card/Table children + invalid HTML nesting + theme SSR parity fixed |
| Dashboard feature pages shell-only in visual evidence | 20 dashboard scenarios capture module content (KPIs, tables, stubs, RBAC) |
| No ownership/RBAC functional tests beyond 17 auth/lookup tests | 24 targeted Playwright specs (12 frontend + 12 dashboard) |
| Customer/agent private routes missing explicit noindex metadata | `robots: { index: false, follow: false }` on portal layouts |

## Hydration reproduction and fix

See `frontend/docs/visual/JP-UI-05A-DASHBOARD-HYDRATION-ROOT-CAUSE-AND-FIX.md`.

**Root causes:**
1. `CardDescription` as `<p>` wrapping block elements (invalid HTML → browser DOM correction mismatch)
2. `Card`, `Table`, `Th`, `Td` primitives self-closing without `{children}` (empty SSR vs full client)
3. Hardcoded `data-theme="light"` on dashboard `<html>` conflicting with bootstrap script

**Removed suppression:**
- `filterBenignPageErrors()` in `jp-ui-05-helpers.ts` (stripped React #418 for `application === "dashboard"`)

**Retained (acceptable):**
- `suppressHydrationWarning` on dashboard `<html>` only for theme attribute set by inline bootstrap before paint

## Dashboard route classification summary

See `frontend/docs/visual/JP-UI-05A-DASHBOARD-FEATURE-PAGE-OPERATIONAL-PREVIEW-STUB-MATRIX.md`.

| Class | Examples |
|-------|----------|
| Operational (A/B) | bookings, payments, agents, users, pnrs, tickets, customers, audit |
| Preview (C) | overview charts, reports, CMS, settings with fixture flag |
| Honest stub (D) | `/planned/*` including cancellations queue |
| Forbidden (E) | platform staff users route with `dataSourcePreview=forbidden` |
| Missing (F) | dedicated deposits/refunds list routes (KPI cards only) |

## Functional test results

| Suite | Command | Result |
|-------|---------|--------|
| Customer ownership | `npx playwright test tests/jp-ui-05a-customer-ownership.spec.ts` | 4/4 PASS |
| Agent RBAC | `npx playwright test tests/jp-ui-05a-agent-rbac.spec.ts` | 5/5 PASS |
| Profile/logout | `npx playwright test tests/jp-ui-05a-profile-logout.spec.ts` | 3/3 PASS |
| Dashboard hydration | `npx playwright test tests/jp-ui-05a-hydration.spec.ts` (dashboard) | 9/9 PASS |
| Dashboard RBAC | `npx playwright test tests/jp-ui-05a-rbac.spec.ts` (dashboard) | 3/3 PASS |

Laravel tests not run — no Laravel changes.

## Visual audit (unfiltered)

| Metric | Result |
|--------|--------|
| Command | `npm run audit:visual:jp-ui-05` |
| expected | 132 |
| executed | 132 |
| passed | 132 |
| failed | 0 |
| skipped | 0 |
| screenshots | 132 |
| duration | 538s |
| hydration warnings | 0 |
| React #418 | 0 |
| page errors | 0 |
| overflow failures | 0 |

Artifact: `frontend/docs/visual/jp-ui-05-capture-result.json`

## Build and lint

| App | typecheck | lint | build |
|-----|-----------|------|-------|
| frontend | PASS | PASS | PASS |
| dashboard | PASS | PASS | PASS |

## Changed files

### Dashboard
- `dashboard/app/layout.tsx` — ThemeProvider, robots noindex, theme bootstrap
- `dashboard/components/theme/ThemeProvider.tsx` — new
- `dashboard/components/dashboard/feature-states.tsx` — new shared states
- `dashboard/components/ui/card.tsx`, `table.tsx` — children rendering
- `dashboard/components/ui/data-source-status.tsx` — access denied testid
- `dashboard/features/*/components/*-mobile-card.tsx` — valid HTML nesting
- `dashboard/lib/format.ts`, `lib/theme/theme-bootstrap-script.ts`
- `dashboard/playwright.config.ts` — port 3003
- `dashboard/tests/jp-ui-05a-hydration.spec.ts`, `jp-ui-05a-rbac.spec.ts`

### Frontend
- `frontend/app/customer/layout.tsx`, `agent/layout.tsx` — noindex metadata
- `frontend/lib/theme/theme-bootstrap-script.ts` — audit theme params
- `frontend/tests/visual-audit/jp-ui-05-helpers.ts` — suppression removed
- `frontend/tests/visual-audit/jp-ui-05-fixtures.ts` — validation-fail fixture, unroute
- `frontend/tests/visual-audit/jp-ui-05-scenarios.ts` — signup-validation-errors fixture
- `frontend/tests/jp-ui-05a-*.spec.ts` — ownership/RBAC/logout tests

## Commit SHAs

| Commit | SHA | Message |
|--------|-----|---------|
| Hydration fix | _pending_ | fix(dashboard): resolve hydration mismatch without suppression |
| Feature pages | _pending_ | feat(dashboard): complete role-safe feature page visual states |
| Tests | _pending_ | test(frontend): add JP-UI-05A ownership and RBAC closure tests |
| Documentation | _pending_ | docs(visual): record JP-UI-05A final closure evidence |
| Merge | _pending_ | merge: complete JP-UI-05A dashboard and RBAC closure |
| Final docs SHA | _pending_ | docs(phases): set JP-UI-05A closure SHAs in summary |

## Remaining limitations

- Dashboard deposits/refunds have no dedicated list routes; overview KPI and planned stubs only
- Profile in dashboard is header/sidebar menu only; no dedicated profile page
- Frontend ownership tests use Playwright route mocks; Laravel remains final authority
- Logout redirect not asserted end-to-end (session fixture persists); CSRF POST verified
- Production untouched; Backup Safe untouched

## JP-UI-06 readiness

**Ready** — JP-UI-05 visual foundation complete with unfiltered hydration evidence. Next phase: `JP-UI-06-ASSETS-ANIMATIONS-RESPONSIVE-ACCESSIBILITY-SCREENSHOT-DIFF-AND-FINAL-VISUAL-CLOSURE`.
