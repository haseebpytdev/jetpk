# Phase 11 — Patch base audit

## Expected patch base commit

| Field | Value |
|-------|--------|
| **Branch tip (committed)** | `b155b10d0b9c5984c645d6aba473d746415cd2e9` |
| **Message** | Fix JetPakistan homepage hero visual clarity without layout changes. |
| **Branch** | `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1` |

The Phase 10 review patch was produced as **`git diff` of the working tree against this commit** for **tracked shared paths only** (see `docs/integrations/one-api/phase-10-review-patch-evidence.md`).

**Blob verification:** `app/Enums/SupplierProvider.php` “before” hunk blob `24738ae…` matches `git ls-tree b155b10` for that file.

## Patch artifacts (Phase 10 → Phase 11)

| Artifact | Role |
|----------|------|
| `storage/app/one-api-phase-10-review.patch` | Original shared-file diff; **strict** `git apply --check` fails on `routes/web.php` (CRLF on trailing context line). |
| `storage/app/one-api-phase-11-review.patch` | Regenerated via `git diff b155b10 -- <20 paths>` (cmd redirect, LF); **strict** `git apply --check` passes on clean `b155b10` worktree. |
| `storage/app/one-api-phase-*-stage-new-files.ps1` | Untracked dedicated runtime (77 paths in `one-api-phase-10-new-files.txt`). |
| `storage/app/one-api-phase-*-stage-shared-files.ps1` | Interactive `git add -p` for 20 patch paths + mixed notes. |
| `storage/app/one-api-phase-*-interactive-stage.md` | Human staging checklist. |

## What the patch contains (20 tracked paths)

Shared registrations and integration only — **no** `new file mode` hunks:

- `app/Enums/SupplierProvider.php`
- `app/Http/Controllers/Admin/SupplierConnectionController.php`
- `app/Services/Booking/BookingProviderRouter.php`
- `app/Services/Suppliers/SupplierAdapterResolver.php`
- `app/Services/Suppliers/SupplierBookingService.php`
- `app/Services/Suppliers/SupplierConnectionService.php`
- `app/Services/Suppliers/TicketingService.php`
- `app/Support/Bookings/SupplierLifecycleContextResolver.php`
- `app/Support/Platform/PlatformModuleEnforcer.php`
- `app/Support/Platform/PlatformModuleGate.php`
- `app/Support/Platform/PlatformModuleRegistry.php`
- `app/Support/Suppliers/SupplierLifecycleCapabilities.php`
- `bootstrap/app.php` (One API JSON exception handler hunk)
- `config/logging.php`, `config/ota-suppliers.php`, `config/supplier_credentials.php`, `config/suppliers.php`
- `resources/views/dashboard/admin/api-settings/form.blade.php`
- `resources/views/frontend/booking/partials/passenger-details-body.blade.php`
- `routes/web.php` (One API checkout route group)

## What the patch does **not** contain (by design)

| Bucket | Source list | Commit procedure |
|--------|-------------|------------------|
| New untracked runtime | `storage/app/one-api-phase-10-new-files.txt` (77 PHP paths) | `one-api-phase-11-stage-new-files.ps1` |
| Mixed (partial hunks) | `storage/app/one-api-phase-10-mixed-files.txt` | `git add -p` — notably **`bootstrap/providers.php`** (OneApiServiceProvider) **not in `.patch`** |
| Public mirror | `public/js/ota-one-api-checkout.js` | explicit add with new files |
| Admin panel partial | `resources/views/.../one_api.blade.php` | explicit add |
| Tests/fixtures | `storage/app/one-api-phase-10-tests.txt` (+ Phase 11 router integration test) | optional commit; required for CI |
| Docs/evidence | `docs/integrations/one-api/`, `storage/app/one-api-phase-*.txt` | excluded from deploy per manifest |
| Vendor PDFs/credentials | `one-api-phase-10-excluded-files.txt` | must not commit |

## Mixed-file / shared-file representation

- **Shared-file changes:** fully represented in the `.patch` for the 20 paths above.
- **Mixed files:** `bootstrap/app.php` and `routes/web.php` One API hunks are in the patch; **`bootstrap/providers.php`** is mixed-only and copied/staged separately.
- **Unrelated working-tree work:** Sabre scenario commands, PIA/IATI-only edits, CMS/theme/UI_test paths are **absent** from patch path list (spot-check grep: no `UI_test`, no standalone Sabre test edits).

## Generated storage artifacts

Patch files and staging scripts are **review packaging** under `storage/app/` — not runtime deploy targets (`phase-10-excluded-files.txt`). They must not be uploaded as application code.

## Runtime registration dependency closure (minimum)

1. Patch shared registrations (enum, routers, adapters, config, routes, bootstrap JSON handler).
2. All paths in `one-api-phase-10-new-files.txt` + `OneApiServiceProvider` registration in **`bootstrap/providers.php`**.
3. `public/js/ota-one-api-checkout.js` + checkout Blade hooks for One API UX.
4. Sanitized tests/fixtures from `one-api-phase-10-tests.txt` and `tests/Feature/Suppliers/OneApiBookingProviderRouterIntegrationTest.php` for commit-readiness.

## SHA-256 (review patches)

| File | SHA-256 |
|------|---------|
| `one-api-phase-10-review.patch` | `93A1A66FB0064186092E7B7D1997FCC03182C9C6CF438E512B875529DD5488FE` |
| `one-api-phase-11-review.patch` | `A65DADC01B667D0BE3725EB8289A043A570AEF604ED2872668E769C14F3D7E9F` |

**Recommendation:** use **Phase 11** patch for clean-room `git apply --check` (LF-normalized).
