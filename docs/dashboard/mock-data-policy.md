# Mock Data Policy

Phase: **JETPK-DASH-04-05** (extends DASH-03)

## Rules

1. All dashboard metrics, bookings, payments, customers, and notifications in `/testdash` are **synthetic**.
2. Do not embed real PNRs, passenger names from production, payment amounts from live DB, or supplier credentials in fixtures or `NEXT_PUBLIC_*` variables.
3. `NEXT_PUBLIC_USE_MOCK_DATA=true` is required for preview; live data flags stay off until a gated API phase.
4. UI actions that would mutate Laravel state show **preview-only** feedback (alerts / disabled controls) and never POST to `/admin` or `/staff`.
5. Bookings list/detail in DASH-02 is **read-only** — no cancel, refund, ticket, or payment actions.
6. Payments ledger in DASH-03 is **read-only** — no capture, refund, reconcile, or mark-paid actions.
7. Customers and suppliers in DASH-04-05 are **read-only** — no edit, suspend, credential reveal, settlement, or live API actions.

## Fixture location

- [`dashboard/mocks/overview-fixtures.ts`](../../dashboard/mocks/overview-fixtures.ts) — overview widgets
- [`dashboard/mocks/booking-fixtures.ts`](../../dashboard/mocks/booking-fixtures.ts) — deterministic booking list (25 records)
- [`dashboard/mocks/payment-fixtures.ts`](../../dashboard/mocks/payment-fixtures.ts) — deterministic payment/transaction ledger (35 records, linked to bookings)
- [`dashboard/mocks/customer-fixtures.ts`](../../dashboard/mocks/customer-fixtures.ts) — deterministic customer/traveller records (30 records, linked to bookings/payments)
- [`dashboard/mocks/supplier-fixtures.ts`](../../dashboard/mocks/supplier-fixtures.ts) — deterministic supplier records (22 records, linked to bookings/payments)
- Loaded via [`dashboard/services/overview-service.ts`](../../dashboard/services/overview-service.ts), [`dashboard/services/booking-service.ts`](../../dashboard/services/booking-service.ts), [`dashboard/services/payment-service.ts`](../../dashboard/services/payment-service.ts), [`dashboard/services/customer-service.ts`](../../dashboard/services/customer-service.ts), and [`dashboard/services/supplier-service.ts`](../../dashboard/services/supplier-service.ts)

## Recoverable error preview (bookings, payments, customers & suppliers)

Append `previewError=1` to the bookings, payments, customers, or suppliers query string to simulate a recoverable service error (deterministic, for QA only).

Append `previewLoading=1` to customers or suppliers to render the deterministic loading skeleton (QA only).

## Laravel-side flags (future wiring)

Documented for ops; **not connected** in DASH-01/02:

- `DASHBOARD_PREVIEW_ENABLED`
- `DASHBOARD_PREVIEW_ALLOW_LIVE_DATA=false`
- `DASHBOARD_PREVIEW_ALLOW_MUTATIONS=false`

## Review checklist

Before enabling live data in a future phase:

- Auth session + CSRF for same-origin API
- RBAC on every read endpoint
- Audit log for admin API access
- Pen-test mutation endpoints separately from read endpoints
