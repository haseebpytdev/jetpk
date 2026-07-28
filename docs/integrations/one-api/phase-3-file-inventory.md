# One API Phase 3 — File inventory

**Branch:** `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1`  
**Generated:** 2026-07-22 via `php artisan ota:one-api-phase-3-inventory`

## Manifests

| File | Purpose |
|------|---------|
| `storage/app/one-api-phase-3-clean-runtime-files.txt` | Server deploy candidates (new One API tree + JS/views) |
| `storage/app/one-api-phase-3-shared-files.txt` | Tracked shared files (registration + `bootstrap/app.php`) |
| `storage/app/one-api-phase-3-test-files.txt` | PHPUnit + fixtures |
| `storage/app/one-api-phase-3-doc-files.txt` | Integration docs |
| `storage/app/one-api-phase-3-generated-files.txt` | Local evidence / storage outputs |
| `storage/app/one-api-phase-3-excluded-files.txt` | Sabre, UI_test, JETPK_*, uncertain |
| `storage/app/one-api-phase-3-stage-new-files.ps1` | Explicit `git add -- <path>` for new files only |
| `storage/app/one-api-phase-3-shared-file-hunks.md` | Interactive staging notes |

## Shared tracked files (mixed-hunk risk)

| Path | Classification |
|------|----------------|
| `bootstrap/app.php` | **Mixed** — One API JSON exception handler + unrelated exception handling |
| `routes/web.php` | One API checkout routes only (verify diff) |
| `config/*.php` | One API blocks only (verify diff) |
| `SupplierProvider.php`, routers, adapters | One API registration |
| `passenger-details-body.blade.php` | One API extras + continue gate |
| Sabre / `BookingCommunicationService` / theme CSS | **Unrelated** — do not stage for One API |

## Phase 3 runtime additions (highlights)

- `app/Services/Suppliers/OneApi/Checkout/OneApiCheckoutCatalogPresenter.php`
- Ancillary fixtures: `tests/Fixtures/Suppliers/OneApi/ancillary_*.xml`
- Updated `public/js/ota-one-api-checkout.js` (selection IDs, grouped UI)
- `app/Console/Commands/OneApiPhase3InventoryCommand.php`

## Verification vs Phase 2 manifests

Re-run `git status --short` captured in `storage/app/one-api-git-status-phase3.txt`. Phase 2 runtime list remains valid superset; Phase 3 clean list focuses on isolated One API deploy set.
