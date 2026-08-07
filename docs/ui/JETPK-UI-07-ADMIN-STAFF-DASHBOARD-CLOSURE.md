# JETPK-UI-07 — Admin and Staff Dashboard Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-07 |
| Branch | `phase/jetpk-ui-07-admin-staff-dashboard-closure` |
| Baseline | `41344ddd1ca3151385f8edb1ac146fd0a1ce9559` |
| Gaps | JETPK-UI-007, JETPK-UI-019, JETPK-UI-021 |
| Deployment | NOT PERFORMED |

## Changes

- Removed duplicate PLANNED nav stubs (`Staff Management`, `Roles & Permissions`, `Audit Logs`, `System Settings`) from `dashboard/lib/nav-config.ts`; live Users/Roles/Audit/Settings remain under Access control.
- Added `dashboard-portal-label` to sidebar: **Admin console** vs **Staff console**.
- Differentiated staff preview fixture session (narrower nav, Platform Staff role).
- Reclassified Blade admin overview tests: `/admin` redirect asserted; obsolete Blade-at-root stylesheet tests skipped with Next cutover justification.
- Extended `overview.smoke.spec.ts` and `jp-ui-05a-rbac.spec.ts` for nav dedupe and portal labels.

## Gap closure

| Gap | Status |
|-----|--------|
| JETPK-UI-007 | **CLOSED** |
| JETPK-UI-019 | **CLOSED** |
| JETPK-UI-021 | **CLOSED** |

**Remaining open gaps:** 8

## Tests

- `dashboard/tests/overview.smoke.spec.ts`
- `dashboard/tests/jp-ui-05a-rbac.spec.ts`
- `php artisan test --filter=JetPakistan`

## Final status

Pending merge after acceptance PASS.
