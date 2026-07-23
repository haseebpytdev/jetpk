# Admin API Readiness

Phase: **JETPK-DASH-01**

## Current state

| Item | Status |
|------|--------|
| `routes/api.php` | **Does not exist** |
| Admin data for lists | Blade views + **web** JSON (`admin.bookings.data`, agents/applications data) |
| Auth for AJAX | Session cookies (`web` middleware), not Sanctum/token API |
| Dashboard aggregates | Server-rendered via `AgencyDashboardService` in `DashboardController` |

## Readiness by feature area

| Area | Read pattern today | Mutation pattern | API readiness |
|------|-------------------|------------------|---------------|
| Dashboard overview | Controller + service → Blade | N/A (read-only page) | **Medium** — aggregate queries exist; need JSON DTO + auth |
| Bookings list | `bookings.data` JSON | Many POST/PATCH on booking | **Medium** — partial list API; actions need separate endpoints |
| Bookings detail | Full HTML show | Heavy POST surface | **Low** — needs decomposed action API |
| Agents | `agents.data` JSON | Limited admin CRUD elsewhere | **Medium** for list |
| Settings / suppliers | Form POST | High mutation risk | **Low** — secrets; admin-only |
| Finance / reports | GET + export routes | Adjustments POST | **Low–medium** |
| Support tickets | HTML + reply POST | Staff/admin split | **Medium** |
| Page settings | Dedicated controller | Publish/preview POST | **Medium** (staff gate) |

## Recommended future API shape (documentation only)

1. **Phase 2+:** Laravel `routes/api.php` or `/admin/api/*` web group with same session + CSRF for same-origin Next.
2. **Read-first:** `GET /admin/api/dashboard/overview` mirroring `AgencyDashboardService::build()` + `buildAdminCommandCenter()`.
3. **List endpoints:** reuse query logic from `BookingManagementController@data`, paginated + filter params documented from Blade.
4. **Mutations:** explicit action endpoints with idempotency keys; never expose supplier credentials in JSON.
5. **Staff:** parallel `/staff/api/*` with `staff.permission` middleware on each route.

## DASH-01 constraint

Next app uses **mock fixtures only** — no live API calls.
