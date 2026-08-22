# JP-INT-01 RUNTIME MANIFEST

## Pins

| Pin | SHA |
|---|---|
| PRODUCTION_BASE_SHA | `8cf657d7d35cc97848318f56184825ac49af6225` |
| WAVE9_ENGINEERING_SHA | `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` |
| START_BRANCH_HEAD (docs head before JP-INT-01) | `745bd2cd79dd1e9090f0581f72e7c1f01233fc0d` |
| FINAL_JP_INT01_ENGINEERING_SHA | `0e07af92880dbe38dcfd80d362f2193030eb903b` |

## Deployment comparison guidance

- **A (Wave-9 delta):** `67417d22…` → FINAL_JP_INT01_ENGINEERING_SHA
- **B (actual production deploy delta):** `8cf657d7…` → FINAL_JP_INT01_ENGINEERING_SHA  
  Use **B** for production upload because production has not yet received Wave-9.

## Migrations (2)

1. `database/migrations/2026_08_22_220000_create_integration_health_checks_table.php`
2. `database/migrations/2026_08_22_220100_add_purpose_to_payment_transactions_table.php`

## New routes (admin)

- `GET admin/integrations`
- `GET admin/integrations/{code}`
- `PATCH admin/integrations/{code}`
- `POST admin/integrations/{code}/activate`
- `POST admin/integrations/{code}/deactivate`
- `POST admin/integrations/{code}/test-connection`
- `POST admin/integrations/{code}/test-payment`
- `GET admin/integrations/{code}/health`
- `GET admin/integrations/{code}/docs`

Legacy: `GET admin/settings/payments` → `/admin/dashboard/integrations?provider=abhipay`

## New permissions

- `integrations.view`
- `integrations.manage`
- `integrations.test`
- `integrations.activate` (high risk)
- `integrations.test-payment` (high risk)
- `integrations.audit`

## New models / services (runtime)

- `App\Models\IntegrationHealthCheck`
- `App\Support\Integrations\IntegrationRegistry`
- `App\Support\Integrations\IntegrationDefinition`
- `App\Support\Integrations\IntegrationAuthorization`
- `App\Contracts\Integrations\IntegrationManager`
- `App\Services\Integrations\IntegrationHubService`
- `App\Services\Integrations\IntegrationManagerResolver`
- `App\Services\Integrations\IntegrationHealthRecorder`
- `App\Services\Integrations\IntegrationTestThrottle`
- `App\Services\Integrations\AbhiPayDiagnosticPaymentService`
- `App\Services\Integrations\Managers\*`
- `App\Http\Controllers\Admin\IntegrationsController`
- Dashboard: `dashboard/features/integrations/*`, `dashboard/app/[portal]/dashboard/integrations/page.tsx`

## Modified runtime (shared)

- `PaymentGateway` checkout readiness (v3 + callback)
- `PaymentGatewaySettingsService` present/testConnection/audit
- `PaymentTransaction.purpose`
- Dashboard nav / RBAC catalogs / portal paths / operational-api
- `BackOfficeCapabilitiesPresenter`, legacy redirect controller
- `routes/admin.php`, `AppServiceProvider` gates

## Dashboard path

- `/admin/dashboard/integrations`

## Public frontend dependencies

- None new (Wave-9 public checkout reused)

## Rollback base

- `745bd2cd79dd1e9090f0581f72e7c1f01233fc0d` (pre JP-INT-01 docs/engineering head on this branch)
- Or production `8cf657d7…` if rolling back a full Wave-9+JP-INT-01 deploy

## Explicit exclusions from runtime upload

- tests/, docs/, tmp/, screenshots, .next, private tooling, playwright captures

## Unexpected runtime subsystems

- NONE
