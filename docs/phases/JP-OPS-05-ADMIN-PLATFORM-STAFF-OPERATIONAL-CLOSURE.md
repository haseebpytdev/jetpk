# JP-OPS-05 Admin Platform Staff Operational Closure

## Phase

**JP-OPS-05** — Admin and Platform Staff operational closure
**Branch:** `phase/jetpk-ops-05-admin-platform-staff-closure`
**Baseline:** `b93ce492b04fe8ab038c97d460d63d4e69ae8fa1`

## Objective

Connect the Next.js `dashboard/` Admin/Staff surface to Laravel-authoritative session, capabilities, KPI reads, and safe review mutations — without Blade retirement, production deploy, or JP-OPS-06 execution.

## Mutation binding truth (JP-OPS-05B — Option B)

| Classification | Count |
|----------------|------:|
| CONNECTED (full Next binding) | **6** |
| BACKEND_WITHOUT_NEXT_BINDING | **8** |
| DEFERRED | **145** |
| **Total** | **159** |

Six UI-bound review mutations: payment verify/reject (admin + staff) and deposit approve/reject (admin). Eight cancel/refund review mutations retain Laravel JSON only — no production dashboard service, reachable UI, or operational Playwright.

## Root causes

1. **GAP-001:** Dashboard API was GET-only; 159 Blade mutations had no Next binding.
2. **GAP-002:** `session ?? mockUser` chrome fallback in live mode.
3. **GAP-010:** Fixture KPIs/RBAC active in preview default mode.
4. No JP-OPS-02-equivalent mutation client in dashboard.
5. No server-serialized capabilities/navigation for Staff permission filtering.
6. **JP-OPS-05B:** Tests still assumed legacy `admin@ota.demo` agency-admin semantics and obsolete Sabre service mocks.

## Backend changes

| Component | Change |
|-----------|--------|
| `BackOfficePortalAccess` | Admin/Staff session usability |
| `BackOfficeCapabilitiesPresenter` | Capabilities, navigation, per-record gates |
| `RespondsWithBackOfficeJson` | Portal mutation JSON trait |
| `DashboardSessionResource` | Extended context contract |
| Admin/Staff controllers | JSON on payment/deposit/cancel/refund review |
| `DashboardDepositsController` | Read API for deposits |
| `DashboardPaymentResource` | Capabilities + `laravelPaymentId` |
| `admin/settings` Blade | Iterate `$groups` from `AdminSettingsHubController` |
| `admin/accounting/reconciliation` Blade | Include reconciliation cards partial |

## Frontend changes

| Area | Change |
|------|--------|
| `dashboard/lib/api/laravel-action-client.ts` | JP-OPS-02-equivalent client, no CSRF replay |
| `session-service.ts` | Live unavailable state, server nav/capabilities, fixture Reports nav |
| `sidebar.tsx` | Session navigation in **live** mode only; preview uses full `navGroups` |
| `payment-review-actions.tsx` | Verify/reject with duplicate lock |
| `deposits/*` | Admin deposit review page |
| Playwright | `jp-ops-05-*` regression specs |
| `read-only-integration.foundation.spec.ts` | Sensitive localStorage keys only (allows `jp-theme-preference`) |

## Gaps closed / reclassified

| Gap | Status |
|-----|--------|
| GAP-001 | **PARTIALLY_CLOSED** — 6 UI-connected + 8 backend-only review JSON; 145 deferred |
| GAP-002 | **CLOSED** — live unavailable chrome |
| GAP-010 | **CLOSED** — live mode gates |

## Baseline A/B (JP-OPS-05B)

- Stash `637cf580…` preserved 79 files; restore verified; stash dropped.
- Baseline 9-file gate: **102 passed, 18 failed, 3 errors, 4 risky**.
- JP-OPS-05 12-file gate: **151 passed, 0 failed, 0 errors, 0 risky**.

## Tests (JP-OPS-05B final)

| Gate | Result |
|------|--------|
| Mandatory 12-file Laravel gate | **151/151** (0 risky) |
| `BackOfficeOperationalClosureTest` | **12/12** |
| `BackOfficeSessionContractTest` | **10/10** |
| `BackOfficePrivilegeEscalationTest` | **6/6** |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| JP-OPS-02 client security | PASS |
| JP-OPS-03 customer regression | PASS (23 tests) |
| JP-OPS-04 agent regression | PASS (28 tests) |
| Dashboard JP-OPS-05 regression | **7** checks, **0 failures** |
| Dashboard JP-OPS-05 operational | **30** tests, **0 failures** |
| Dashboard full smoke | **1126** tests, **0 failures**, exit code **0** |

## OTP

`OTP_DEMO_*` / `DemoFixedLoginOtpGate` — **unchanged** (`git diff` exit 0)

## Status

**READY FOR JP-OPS-05 COMMIT** (pending review authorization; do not commit without approval)
