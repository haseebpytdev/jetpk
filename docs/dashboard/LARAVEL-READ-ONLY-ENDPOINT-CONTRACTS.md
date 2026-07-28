# Laravel Read-Only Endpoint Contracts — JETPK-DASH-11 Prompt 02–03

All routes are **GET-only** under `/api/dashboard/*`. Laravel session (`web` guard) is required.

**Inventory:** 21 routes total — 8 Prompt 02, 13 Prompt 03 (suppliers 2, agents 2, pnrs 2, tickets 2, reports 5).

## Routes

| Method | Route | Permission | Stale after |
|--------|-------|------------|-------------|
| GET | `/api/dashboard/session` | authenticated dashboard user | 5 min |
| GET | `/api/dashboard/overview` | `dashboard.view` | 60s |
| GET | `/api/dashboard/bookings` | `bookings.view` | 30s |
| GET | `/api/dashboard/bookings/{id}` | `bookings.view` + `BookingPolicy::view` | 30s |
| GET | `/api/dashboard/payments` | `payments.view` | 30s |
| GET | `/api/dashboard/payments/{id}` | `payments.view` | 30s |
| GET | `/api/dashboard/customers` | `customers.view` | 60s |
| GET | `/api/dashboard/customers/{id}` | `customers.view` | 60s |
| GET | `/api/dashboard/suppliers` | `suppliers.view` | 120s |
| GET | `/api/dashboard/suppliers/{id}` | `suppliers.view` + `SupplierConnectionPolicy::view` | 120s |
| GET | `/api/dashboard/agents` | `agents.view` | 60s |
| GET | `/api/dashboard/agents/{id}` | `agents.view` + `AgentPolicy::view` | 60s |
| GET | `/api/dashboard/pnrs` | `pnrs.view` | 30s |
| GET | `/api/dashboard/pnrs/{id}` | `pnrs.view` + `BookingPolicy::view` | 30s |
| GET | `/api/dashboard/tickets` | `tickets.view` | 30s |
| GET | `/api/dashboard/tickets/{id}` | `tickets.view` + `BookingPolicy::view` | 30s |
| GET | `/api/dashboard/reports/summary` | `reports.view` | 300s |
| GET | `/api/dashboard/reports/bookings` | `reports.view` | 300s |
| GET | `/api/dashboard/reports/payments` | `reports.view` | 300s |
| GET | `/api/dashboard/reports/suppliers` | `reports.view` | 300s |
| GET | `/api/dashboard/reports/agents` | `reports.view` | 300s |

## Authorization mapping

| Dashboard key | Laravel enforcement |
|---------------|---------------------|
| `dashboard.view` | `DashboardPermissionResolver::canViewDashboard()` |
| `bookings.view` | `Gate::authorize('viewAny', Booking::class)` |
| `payments.view` | `DashboardPermissionResolver::canViewPayments()` |
| `customers.view` | `Gate::authorize('viewAny', User::class)` (platform admin) |
| `suppliers.view` | `Gate::authorize('viewAny', SupplierConnection::class)` (platform admin) |
| `agents.view` | `Gate::authorize('viewAny', Agent::class)` (platform admin) |
| `pnrs.view` | `Gate::authorize('viewAny', Booking::class)` |
| `tickets.view` | `DashboardPermissionResolver::canViewTickets()` |
| `reports.view` | `StaffPermission::ReportsView` / `AgentPermission::ReportsView` / platform admin |

Staff keys map from `StaffPermission` (e.g. `staff.bookings.view`). Agent portal uses `AgentPermission::BookingsView`.

## Response envelope

```json
{
  "data": {},
  "meta": {
    "source": "laravelReadOnly",
    "fetchedAt": "ISO-8601",
    "referenceTime": "ISO-8601",
    "staleAfter": "ISO-8601",
    "requestIdSafe": "DASH-XXXXXXXX",
    "recordCount": 0,
    "fixtureRevision": null,
    "schemaVersion": "dash-read-only-v1"
  },
  "pagination": { "page": 1, "pageSize": 25, "total": 0, "pageCount": 1 },
  "filters": {},
  "source": "laravelReadOnly",
  "generatedAt": "ISO-8601",
  "referenceTime": "ISO-8601",
  "warnings": [],
  "schemaVersion": "dash-read-only-v1"
}
```

## Error envelope

```json
{
  "error": {
    "code": "unauthenticated|forbidden|not_found|rate_limited|...",
    "message": "sanitized",
    "status": 401,
    "referenceIdSafe": "HTTP-401"
  },
  "meta": { "source": "laravelReadOnly", "schemaVersion": "dash-read-only-v1" }
}
```

## Excluded fields (all endpoints)

`password`, `password_hash`, `mfa_secret`, `recovery_codes`, `session_id`, `csrf_token`, `api_key`, `supplier_credentials`, `pcc`, `lniata`, `card_number`, `pan`, `passport_number`, `national_id`, unrestricted notes, raw supplier payloads.

## Bookings query parameters

`page`, `pageSize`, `q`, `status`, `payment`, `supplier`, `channel`, `bookingDateFrom`, `bookingDateTo`, `departureDateFrom`, `departureDateTo`, `sort`, `direction`

Channel values: `gds`, `ndc` (derived from supplier provider metadata).

## Payments query parameters

`page`, `pageSize`, `q`, `paymentStatus`, `method`, `currency`, `reconciliation`, `dateFrom`, `dateTo`, `sort`, `direction`

## Customers query parameters

`page`, `pageSize`, `q`, `accountStatus`, `verificationStatus`, `customerType`, `sort`, `direction`

## Frontend adapter

- `dashboard/lib/read-only/laravel/laravel-client.ts` — GET client with same-origin credentials
- `dashboard/lib/read-only/laravel/transformers/*` — map Laravel DTOs to fixture-compatible types
- Module services: `session-service`, `overview-service`, `booking-service`, `payment-service`, `customer-service`

## Fixture mapping

| Endpoint | Fixture equivalent |
|----------|-------------------|
| session | `session-service` / `mockUser` |
| overview | `overview-fixtures` |
| bookings | `booking-fixtures` |
| payments | `payment-fixtures` |
| customers | `customer-fixtures` |

## Implementation status (Prompt 02)

| Module | Laravel | Next.js adapter | UI integrated |
|--------|---------|-----------------|---------------|
| Session | ✅ | ✅ | ✅ shell/header/sidebar |
| Overview | ✅ | ✅ | ✅ |
| Bookings | ✅ | ✅ | ✅ |
| Payments | ✅ | ✅ | ✅ |
| Customers | ✅ | ✅ | ✅ |

## Known limitations

- Customer spend aggregates return zero until finance read services are wired (Prompt 03+).
- Overview charts remain fixture-backed when Laravel returns no trend series.
- Next.js dev server requires `NEXT_PUBLIC_LARAVEL_API_BASE` pointing at Laravel origin for live mode.
