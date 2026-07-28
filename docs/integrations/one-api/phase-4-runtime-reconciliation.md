# Phase 4 — Runtime inventory reconciliation (Phase 2 vs Phase 3)

## Summary

| Manifest | Line count | Scope |
|----------|------------|--------|
| `one-api-phase-2-runtime-files.txt` | **79** | Full deploy set: One API module + **shared registration** + config + routes + views |
| `one-api-phase-3-clean-runtime-files.txt` | **61** | **Untracked/new One API-only** paths (classifier excluded shared tracked files) |
| `one-api-deploy-files-v3.txt` | Same as Phase 3 clean | Deploy list followed Phase 3 classifier |

**There is no accidental loss of 18 paths** — Phase 3 intentionally moved shared tracked files to `one-api-phase-3-shared-files.txt` (21 paths).

## Paths in Phase 2 but not Phase 3 “clean” (shared / deploy still required)

| Path | Disposition |
|------|-------------|
| `app/Enums/SupplierProvider.php` | Shared registration — stage with `git add -p` |
| `app/Services/Booking/BookingProviderRouter.php` | Shared |
| `app/Services/Suppliers/SupplierAdapterResolver.php` | Shared |
| `app/Services/Suppliers/SupplierBookingService.php` | Shared |
| `app/Services/Suppliers/SupplierConnectionService.php` | Shared |
| `app/Services/Suppliers/TicketingService.php` | Shared |
| `app/Http/Controllers/Admin/SupplierConnectionController.php` | Shared |
| `app/Support/Bookings/SupplierLifecycleContextResolver.php` | Shared |
| `app/Support/Platform/PlatformModule*.php` (3) | Shared |
| `app/Support/Suppliers/SupplierLifecycleCapabilities.php` | Shared |
| `config/logging.php`, `supplier_credentials.php`, `suppliers.php`, `ota-suppliers.php` | Shared |
| `routes/web.php` | Shared |
| `resources/views/.../one_api.blade.php`, `form.blade.php`, `passenger-details-body.blade.php` | Shared |

## Paths in Phase 3 but not Phase 2

| Path | Explanation |
|------|-------------|
| `app/Services/Suppliers/OneApi/Checkout/OneApiCheckoutCatalogPresenter.php` | **New** in Phase 3 |
| `app/Console/Commands/OneApiPhase3InventoryCommand.php` | **New** inventory command |

## Phase 4 additions (not in Phase 2 manifest)

| Path | Disposition |
|------|-------------|
| `app/Support/OneApi/OneApiFixtureTransportScope.php` | **Runtime required** |
| `app/Console/Commands/OneApiPhase4InventoryCommand.php` | Optional ops (can deploy) |
| `bootstrap/app.php` | **Mixed** — One API JSON exception handler |

## Authoritative Phase 4 runtime list

See `storage/app/one-api-phase-4-runtime-files.txt` (= Phase 2 list + Phase 4 new runtime classes).
