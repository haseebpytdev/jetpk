# Auth & RBAC Integration Plan

Phase: **JETPK-DASH-01** (planning only)

## Current Laravel model

- **Not Spatie** — see [`docs/audit/admin-rbac-matrix.md`](../audit/admin-rbac-matrix.md)
- Admin portal: `account.type:platform_admin` → `/admin`
- Staff portal: `account.type:staff` + `StaffPermission` middleware on mutating routes
- Staff **cannot** access `/admin` even with all staff permissions

## DASH-01 preview

Single shared Next UI with **no authentication**. Nav items include `planned` stubs; RBAC is not enforced in the browser.

## Recommended integration phases

### Phase A — Read-only session API

1. Add Laravel read endpoints (or `/admin/api/*` web group) returning DTOs from existing services (`AgencyDashboardService`, list presenters).
2. Same-site session cookies + CSRF token for Next fetches from `/testdash` once same-origin mounted.
3. Gate each endpoint with existing policies/Gates.

### Phase B — Nav & module visibility

1. Port sidebar rules from `PlatformModuleGate` + JetPK sidebar Blade into a server-side nav config API.
2. For staff sessions, filter items using `StaffPermission` and deny admin-only modules (users, suppliers, settings hub).

### Phase C — Mutations

1. Keep mutations on Laravel POST/PATCH routes initially; Next triggers only after explicit phase with confirmation UX.
2. Never expose supplier credentials or payment capture in JSON.

### Phase D — Portal split UX

Optional: `/testdash/admin` vs `/testdash/staff` route groups sharing components but different nav trees — mirrors production portal separation.

## Shared UI note

Production requires **staff never uses admin URLs**. Preview convenience of one shell must not carry into production RBAC without server enforcement.
