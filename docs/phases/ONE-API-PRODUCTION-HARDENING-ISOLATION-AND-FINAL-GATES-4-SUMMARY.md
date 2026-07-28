# ONE-API-PRODUCTION-HARDENING-ISOLATION-AND-FINAL-GATES-4 — Summary

## Phase name

ONE-API-PRODUCTION-HARDENING-ISOLATION-AND-FINAL-GATES-4

## Branch

`phase/one-api-flyjinnah-airarabia-full-supplier-integration-1`

## Objective

Close internal acceptance gaps: isolate production fixture hooks, reconcile runtime inventory, expand workflow/auth/search/pricing tests, baseline regressions, v4 backup/rollback/deploy artifacts, staging package **without** commit/push/deploy.

## Final status

**NOT COMPLETE** — Part 19 acceptance gates **do not pass**. One API suite is green; enumerated Parts 3–8 matrices, communication idempotency, clean-HEAD PHPUnit baseline, and full workflow negative corpus remain incomplete.

## Included scope (Phase 4)

- `OneApiFixtureTransportScope` + SOAP/checkout gating; removed booking meta `one_api_diagnostic.fixture_path` hook.
- Matrix command explicit `enable('matrix_command')` for fixture mode.
- Hold lifecycle integration test (book on hold → read → modify payment fixtures).
- Fixture transport security unit tests.
- Runtime reconciliation doc; supplier connection auth audit; regression baseline doc.
- `OneApiPhase4InventoryCommand` + v4 backup/rollback/SFTP/post-deploy manifests.
- `SupplierConnectionCrudTest` Phase 3 platform-admin promotion **reverted**.

## Excluded scope

- Sabre/CMS/frontend/UI_test unrelated changes.
- Live supplier calls, deploy, git staging/commit/push.
- Broad `SupplierConnectionCrudTest` RBAC repair (separate phase).

## Investigation findings

- Phase 2 **79** paths vs Phase 3 **61** “clean” paths explained by **21 shared** registration files — not accidental deletion.
- Generic `SupplierConnectionCrudTest` failures (agency admin vs `platform_admin` middleware) are **RBAC semantic mismatch**, not One API regression.
- `OneApiBookingService` does not wire `BookingCommunicationService` — communication idempotency **unproven**.

## Root causes (remaining gaps)

- Spec Parts 3–5 enumerate hundreds of discrete assertions; existing tests cover subsets via closure/matrix tests, not full enumerated matrices.
- Clean HEAD baseline worktree lacked `vendor/` — strict parity not executed.
- Vendor SOAP endpoint unavailable — live SOAP/booking blocked regardless of code readiness.

## Exact files changed (Phase 4 touch set)

See `storage/app/one-api-phase-4-new-files.txt`, `one-api-phase-4-shared-files.txt`, `one-api-phase-4-tests.txt`, `one-api-phase-4-review-diff.patch`.

Key runtime:

- `app/Support/OneApi/OneApiFixtureTransportScope.php`
- `app/Services/Suppliers/OneApi/Transport/OneApiSoapTransport.php`
- `app/Services/Suppliers/OneApi/Booking/OneApiBookingService.php`
- `app/Services/Suppliers/OneApi/Checkout/OneApiCheckoutFlowService.php`
- `app/Console/Commands/OneApiTestMatrixCommand.php`
- `app/Console/Commands/OneApiPhase4InventoryCommand.php`

## Tests executed

| Command | Result |
|---------|--------|
| `vendor/bin/phpunit --filter=OneApi` | **30 passed**, 59 assertions, no network |
| `OneApiTestMatrixCommandTest` | **1 passed**, 5 assertions |
| `OneApiSupplierConnectionFeatureTest` + matrix admin | **3 passed** |
| `SabreGdsLiveScenarioRunnerTest` | **27 passed**, 144 assertions |
| `IatiIntegrationTest` + `PiaNdcAdminOptionPnrTest` | 11 pass, 1 fail (PIA admin confirm) |
| `SupplierConnectionCrudTest` | **23 failed** / 26 (agency admin 403) |

## Known limitations

- No transport interface split (scope gate on `OneApiSoapTransport` instead).
- Communication on paid/hold/hold-pay not integrated in booking service.
- Review patch scoped to One API paths; shared hunks need manual `git add -p`.

## Rollback instructions

Use `storage/app/one-api-rollback-v4.sh` with manifest from `one-api-predeploy-backup-v4.sh`.

## Commit SHA

**None** — nothing committed in Phase 4.

## Recommendation

| Gate | Ready? |
|------|--------|
| Isolated commit | **Not ready** |
| Deployment | **Not ready** |
| Controlled REST live search | **Not ready** |
| Controlled SOAP price | **Not ready** (vendor SOAP unavailable) |
| Controlled live booking | **Not ready** |
