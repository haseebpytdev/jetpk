# Mock Data Policy

Phase: **JETPK-DASH-01**

## Rules

1. All dashboard metrics, bookings, customers, and notifications in `/testdash` are **synthetic**.
2. Do not embed real PNRs, passenger names from production, payment amounts from live DB, or supplier credentials in fixtures or `NEXT_PUBLIC_*` variables.
3. `NEXT_PUBLIC_USE_MOCK_DATA=true` is required for preview; live data flags stay off until a gated API phase.
4. UI actions that would mutate Laravel state show **preview-only** feedback (alerts / disabled controls) and never POST to `/admin` or `/staff`.

## Fixture location

- [`dashboard/mocks/overview-fixtures.ts`](../../dashboard/mocks/overview-fixtures.ts)
- Loaded via [`dashboard/services/overview-service.ts`](../../dashboard/services/overview-service.ts)

## Laravel-side flags (future wiring)

Documented for ops; **not connected** in DASH-01:

- `DASHBOARD_PREVIEW_ENABLED`
- `DASHBOARD_PREVIEW_ALLOW_LIVE_DATA=false`
- `DASHBOARD_PREVIEW_ALLOW_MUTATIONS=false`

## Review checklist

Before enabling live data in a future phase:

- Auth session + CSRF for same-origin API
- RBAC on every read endpoint
- Audit log for admin API access
- Pen-test mutation endpoints separately from read endpoints
