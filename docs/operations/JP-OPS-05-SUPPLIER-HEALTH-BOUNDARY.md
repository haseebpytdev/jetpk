# JP-OPS-05 Supplier Health Boundary

## Connected (read-only)

- `GET /api/dashboard/suppliers` — enabled/disabled, health metadata, non-secret config status
- Overview `supplierFailures` KPI from `AgencyDashboardService`

## Not exposed

- Credentials, API keys, secrets
- Request signatures, full supplier payloads
- Production cancellation hosts
- Supplier configuration mutations (Blade-only)

## Mutations

All supplier configuration mutations remain **BLADE_FALLBACK_RETAINED** unless a future audited JSON contract is approved.
