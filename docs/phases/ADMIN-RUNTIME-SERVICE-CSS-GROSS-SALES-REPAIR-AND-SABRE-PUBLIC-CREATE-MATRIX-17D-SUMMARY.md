# ADMIN-RUNTIME-SERVICE-CSS-GROSS-SALES-REPAIR-AND-SABRE-PUBLIC-CREATE-MATRIX-17D — Summary

## Objective
Repair admin runtime (`SupplierBookingService` deployment gap), restore JetPakistan admin ops CSS, correct Gross Sales KPI semantics, and extend Sabre public create proof (fake HTTP only).

## Workstream A — Supplier booking service
- **Canonical class:** `App\Services\Suppliers\SupplierBookingService` (exists in repo; production missing file → `BindingResolutionException`).
- **Injection sites:** `BookingProviderRouter`, supplier router services, `DuffelBookingService`, tests.
- **Controller change:** `BookingManagementController` (admin) and `Staff\BookingController` no longer inject `SupplierBookingService` directly; they use `BookingProviderRouter::isBookingEligible()` / `markManualPnr()` (router still resolves `SupplierBookingService` internally).
- **Production action:** SFTP `app/Services/Suppliers/SupplierBookingService.php` and all adapter dependencies already in tree.

## Workstream B — Admin CSS
- **Root cause:** `themes.admin.jetpakistan.layouts.dashboard` loaded `dashboard.css` only; main content uses `ota-dash-*` / Tabler `card` classes defined in `public/css/ota-admin-console.css` + Tabler (loaded by legacy `layouts/dashboard.blade.php`).
- **Fix:** JetPakistan admin layout now includes Tabler CSS, `ota-design-system.css`, `ota-admin-console.css`, Tabler Icons, and `ota-admin-console` body classes.

## Workstream C — Gross Sales
- **Old:** `SUM(fare.total)` over all scoped bookings (included **cancelled**).
- **New:** **Gross Sales** = sum for `status != cancelled` only; added **`cancelled_booking_value`** for transparency (cancelled fare totals).
- Pending orphan (Booking 2 shape) still counts toward Gross Sales when not cancelled.

## Workstream D/E — Sabre tests
- `SabreFreshPublicCreateOneDispatchPhase17DTest` — one fake create HTTP + one attempt; duplicate run does not re-dispatch.
- `SabrePublicCreateStructuralPayloadMatrixPhase17DTest` — payload segment order/count (direct + connecting).
- `SabreConfirmationOutcomePhase17DTest` — PNR vs needs-review messaging.

## Tests
Run: `php artisan test --filter=Phase17D`

## SFTP runtime manifest (no tests/docs)
- `app/Services/Suppliers/SupplierBookingService.php` (**critical if missing on prod**)
- `app/Services/Booking/BookingProviderRouter.php`
- `app/Http/Controllers/Admin/BookingManagementController.php`
- `app/Http/Controllers/Staff/BookingController.php`
- `app/Services/Dashboard/AgencyDashboardService.php`
- `resources/views/themes/admin/jetpakistan/layouts/dashboard.blade.php`
- `public/css/ota-admin-console.css` (if not on server)
- `public/vendor/tabler/**` (if not on server)

## Production activation
```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
cd /home/pkjetp/jetpk_app
$PHP -l app/Services/Suppliers/SupplierBookingService.php
$PHP artisan optimize:clear && $PHP artisan package:discover && $PHP artisan config:cache && $PHP artisan route:cache
```

## Verification (read-only)
- `curl -sI https://jetpakistan.pk/admin/bookings` (authenticated)
- `$PHP artisan route:list --name=admin.bookings`
- Guest/admin route checks from phase 17C diagnostic

## Status
Implementation complete locally; **not committed** unless requested.
