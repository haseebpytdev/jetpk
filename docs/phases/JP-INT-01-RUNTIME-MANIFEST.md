# JP-INT-01 RUNTIME MANIFEST

## Pins

| Pin | SHA |
|---|---|
| PRODUCTION_BASE_SHA | `8cf657d7d35cc97848318f56184825ac49af6225` |
| WAVE9_ENGINEERING_SHA | `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` |
| START_BRANCH_HEAD (docs head before JP-INT-01) | `745bd2cd79dd1e9090f0581f72e7c1f01233fc0d` |
| FINAL_JP_INT01_ENGINEERING_SHA | `26ff103287437b995074847a74be1cd227404594` |

## Deployment comparison guidance

- **A (Wave-9 delta):** `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` → `26ff103287437b995074847a74be1cd227404594`
- **B (actual production deploy delta):** `8cf657d7d35cc97848318f56184825ac49af6225` → `26ff103287437b995074847a74be1cd227404594`  
  Use **B** for production upload because production has not yet received Wave-9.

Exact runtime path list for B (excluding tests/docs/tmp): see engineering commit range / `tmp/jp-int-01-deploy-runtime-files.txt` generated at closure (**53** runtime paths).

## Verification closure gates (2026-08-22)

| Gate | Result |
|---|---|
| Dashboard typecheck | PASS |
| Public frontend typecheck | PASS |
| Dashboard production build | PASS |
| Public frontend production build | PASS |
| Playwright `tests/jp-int-01-integrations.spec.ts` | PASS (7/7) |
| Laravel JP-INT-01 + Wave-9 filter | PASS (20 tests / 83 assertions) |

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
- Dashboard: `dashboard/features/integrations/*` (including preview fixtures), `dashboard/app/[portal]/dashboard/integrations/page.tsx`

## Modified runtime (shared)

- `PaymentGateway` checkout readiness (v3 + callback)
- `PaymentGatewaySettingsService` present/testConnection/audit
- `PaymentTransaction.purpose`
- Dashboard nav / RBAC catalogs / portal paths / operational-api
- `BackOfficeCapabilitiesPresenter`, legacy redirect controller
- `routes/admin.php`, `AppServiceProvider` gates
- Plus Wave-9 Review/payment runtime paths included in production delta B

## Dashboard path

- `/admin/dashboard/integrations`

## Public frontend dependencies

- None new for JP-INT-01 alone (Wave-9 public checkout included in delta B)

## Rollback base

- Engineering tip before verification fix: `0e07af92880dbe38dcfd80d362f2193030eb903b`
- Pre JP-INT-01 branch docs head: `745bd2cd79dd1e9090f0581f72e7c1f01233fc0d`
- Production: `8cf657d7d35cc97848318f56184825ac49af6225`

## Explicit exclusions from runtime upload

- tests/, docs/, tmp/, screenshots, .next, private tooling, playwright captures
- `dashboard/tests/jp-int-01-integrations.spec.ts`

## Unexpected runtime subsystems

- NONE
