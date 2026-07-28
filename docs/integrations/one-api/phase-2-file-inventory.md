# One API Phase 2 — Authoritative file inventory

**Branch:** `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1`  
**Generated:** 2026-07-22  
**Raw git captures:** `storage/app/one-api-git-status-raw.txt`, `one-api-git-diff-name-status.txt`, `one-api-git-diff-stat.txt`, `one-api-git-untracked.txt`  
**Machine manifests:** `storage/app/one-api-phase-2-runtime-files.txt` (79 paths), `one-api-phase-2-test-files.txt` (73), `one-api-phase-2-doc-files.txt` (13), `one-api-phase-2-excluded-files.txt` (260)

## Classification key

| Code | Meaning |
|------|---------|
| A | One API new file |
| B | One API shared-registration modification |
| C | One API test / fixture / documentation |
| D | Unrelated pre-existing change |
| E | Generated artifact — do not commit or deploy |
| F | Uncertain — requires review |

## Summary counts

| Class | Approx. count | Notes |
|-------|----------------|-------|
| A | ~55 PHP + 1 JS + 4 views | Entire `app/Services/Suppliers/OneApi/`, checkout controller, probes, matrix runner |
| B | ~15 tracked diffs | Enum, routers, config, admin form, `routes/web.php`, platform modules |
| C | 8 PHPUnit classes + fixtures + 13 integration docs | See test manifest |
| D | ~40+ Sabre/support paths | Preserved; excluded from One API SFTP |
| E | UI_test, JETPK_*, matrix CSV under `storage/app/` | |
| F | Client page CMS, AppServiceProvider, theme CSS | Not One API; not reverted |

## A — One API new runtime files (deploy candidates)

All paths listed in `storage/app/one-api-phase-2-runtime-files.txt`. Highlights:

- **Module:** `app/Services/Suppliers/OneApi/**` (auth, search, SOAP, pricing, bundles, ancillaries, booking, reservation, workflow, checkout).
- **Adapters:** `OneApiFlightSupplierAdapter`, `OneApiSupplierBookingAdapter`, `OneApiBookingRouterService`, `OneApiFareRevalidationService`, `OneApiSupplierTicketingAdapter`.
- **HTTP:** `app/Http/Controllers/Frontend/OneApiCheckoutController.php`.
- **CLI:** `OneApi*Command.php` (matrix, audit, probes, reconcile, phase-2 inventory).
- **Support:** `app/Support/OneApi/OneApiTestMatrixRunner.php`, `OneApiMutationCommandGate.php`, `OneApiSupplierConnectionNormalizer.php`.
- **Frontend:** `public/js/ota-one-api-checkout.js`, `resources/views/frontend/bookings/one-api/extras.blade.php`.

## B — Shared files modified for One API (why necessary)

| File | Why |
|------|-----|
| `app/Enums/SupplierProvider.php` | Register `one_api` provider enum |
| `app/Services/Suppliers/SupplierAdapterResolver.php` | Resolve One API flight adapter |
| `app/Services/Booking/BookingProviderRouter.php` | Route bookings to One API handler |
| `app/Services/Suppliers/SupplierBookingService.php` | Dispatch One API booking adapter |
| `app/Services/Suppliers/TicketingService.php` | One API ticketing adapter hook |
| `app/Services/Suppliers/SupplierConnectionService.php` | Required credential keys for One API |
| `app/Http/Controllers/Admin/SupplierConnectionController.php` | `OneApiSupplierConnectionNormalizer` in normalize chain |
| `app/Support/Bookings/SupplierLifecycleContextResolver.php` | `HANDLER_ONE_API` lifecycle |
| `app/Support/Suppliers/SupplierLifecycleCapabilities.php` | Capability flags |
| `app/Support/Platform/PlatformModule*.php` | `one_api_supplier` module gate |
| `config/supplier_credentials.php` | One API credential schema |
| `config/suppliers.php`, `config/ota-suppliers.php` | Supplier block + fan-out |
| `config/logging.php` | `one-api` log channel |
| `routes/web.php` | Checkout catalog / final-price routes |
| `resources/views/dashboard/admin/api-settings/form.blade.php` | Include One API panel |
| `resources/views/.../one_api.blade.php` | Admin connection UI |
| `resources/views/.../passenger-details-body.blade.php` | Conditional extras partial + continue gate |

## C — Tests and fixtures

See `storage/app/one-api-phase-2-test-files.txt`. PHPUnit:

- `tests/Unit/Services/Suppliers/OneApi/*` (auth, pricing, bundle, money, booking ambiguous, phase-2 closure)
- `tests/Feature/Console/OneApiTestMatrixCommandTest.php`
- `tests/Feature/Suppliers/OneApiCheckoutFlowFeatureTest.php`
- `tests/Feature/Admin/OneApiSupplierConnectionFeatureTest.php`
- `tests/Fixtures/Suppliers/OneApi/*`

Docs under `docs/integrations/one-api/` (protocol, configuration, testing, runbook, this inventory, completeness audit).

## D — Unrelated pre-existing changes (do not stage for One API)

Tracked `git diff HEAD` includes substantial **Sabre GDS** work (not introduced by One API registration):

- `app/Services/Suppliers/Sabre/**` (booking, cancel, GDS revalidation, normalizer)
- `app/Support/Sabre/Scenario/**` (live scenario runner, QR unticketed flows)
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioRunnerTest.php` (modified)
- `app/Console/Commands/SabreGds*.php` (many untracked)

Other unrelated tracked edits: `BookingCommunicationService.php`, `OtaFoundationSeeder.php`, theme CSS, admin jetpakistan index, `bootstrap/app.php`, `ClientPublicWebrootPath.php`, etc.

**No Sabre files were reverted during phase 2.**

## E — Excluded artifacts

`UI_test/**`, `JETPK_*`, `storage/app/one-api-matrix-test/*.csv`, local auth JSON, screenshots, deployment review diffs.

## F — Uncertain (review before any broad commit)

- `app/Providers/AppServiceProvider.php` (+1 line — verify command registration only)
- Client managed page controllers/models (parallel CMS work)
- `_tabler_pkg/package.json`

## Deployment manifest rule

Production runtime list = `storage/app/one-api-deploy-files.txt` (mirrors runtime manifest). **Excludes** tests, vendor PDFs, fixtures, PHPUnit, matrix CSV output, Sabre-only paths.
