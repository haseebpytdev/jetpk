# ONE API Dormant Deployment — Readiness

## Verdict

**READY FOR DORMANT CODE DEPLOYMENT**

## Blockers

None identified for dormant **code upload** when configuration checklist is followed and backups are taken first.

## Safety fixes in source commit `bae3e32`

1. **`OneApiFlightSearchService` live-search gate** — Fail-closed `OneApiValidationException` (`live_search_disabled`) before auth/HTTP when `live_search_enabled` is false and fixture scope is off.
2. **UTF-8 BOM removed** — `resources/views/dashboard/admin/api-settings/form.blade.php` and `resources/views/frontend/booking/partials/passenger-details-body.blade.php`.
3. **Regression tests** — `OneApiDormantLiveSearchGateTest` (5 cases) included in repository; not packaged.

## Warnings

1. **Local Laravel boot** — `vendor/` absent in prep workspace; artisan boot validation deferred to server post-deploy commands.
2. **Excluded phase inventory commands** — `ota:one-api-phase-*-inventory` commands intentionally omitted from package.
3. **Excluded test matrix command** — `ota:one-api-test-matrix` and `OneApiTestMatrixRunner` omitted (fixture/CI tooling).

## Assumptions

- Production/test server already runs JetPakistan main at or before base commit `0305dec` for non–One API paths.
- Public assets are served from `/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com` mirror, not Laravel `public/`.
- No One API `supplier_connections` row is active during dormant deploy (recommended).
- Composer `vendor/` on server is current; **no composer.json changes** in release diff.

## Unresolved items

- Remote file existence for **replace** paths must be confirmed during backup step (`new-files.log`).
- Assign real `--connection=<ID>` only if an inactive One API row exists for audit commands.

## Dormant configuration checklist (exact keys)

### Environment (`config/suppliers.php`)

| Variable | Dormant value |
|---|---|
| `ONE_API_CONNECT_TIMEOUT_SECONDS` | optional (default 10) |
| `ONE_API_REQUEST_TIMEOUT_SECONDS` | optional (default 60) |
| `ONE_API_SEARCH_TIMEOUT_SECONDS` | optional (default 90) |
| `ONE_API_TOKEN_CACHE_FALLBACK_TTL_SECONDS` | optional (default 3000) |
| `ONE_API_TOKEN_EXPIRY_MARGIN_SECONDS` | optional (default 120) |
| `ONE_API_WORKFLOW_CONTEXT_TTL_SECONDS` | optional (default 3600) |
| `ONE_API_LIVE_SEARCH_ENABLED` | **false** or unset |
| `ONE_API_LIVE_BOOKING_ENABLED` | **false** or unset |
| `ONE_API_LIVE_PAYMENT_MODIFICATION_ENABLED` | **false** or unset |

### Platform module

- `one_api_supplier` → **false** (default in `PlatformModuleRegistry`)

### Supplier connection (when row exists)

- `provider` = `one_api`
- `is_active` = **false** (or non-Active status)
- Credential fields `live_search_enabled`, `live_booking_enabled`, `live_payment_modification_enabled` → **false**
- `on_hold_enabled`, `hold_payment_enabled` → **false**
- `soap_url` may be empty (SOAP blocked in `LiveOneApiSoapTransport`)

### Database

- **No migrations** required.

### Composer / npm

- **No composer.json / package.json changes** in release — no install/build on server for this package.

### Maintenance mode

- **Not recommended** for dormant code-only upload.
