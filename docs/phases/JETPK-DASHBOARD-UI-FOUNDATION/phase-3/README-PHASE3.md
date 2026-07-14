# Phase 3 — Customer Booking Detail, Support & Profile Navigation

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION  
**Baseline:** `98ae4a8` (Phase 2 themed customer dashboard)  
**Depends on:** Phase 1 (`<x-dashboard.breadcrumbs>` + `ota-dashboard-foundation.css`)  
**Status:** integrated @ Cursor audit (breadcrumb-only normalization)

## Disposition

| Page | Route | Change |
|---|---|---|
| Booking detail | `customer.bookings.show` | + breadcrumbs (legacy + JetPakistan themed) |
| Support index/create/show | `customer.support.tickets.*` | + breadcrumbs (legacy shell only) |
| Profile | `profile.edit` → `profile.edit-frontend` | + breadcrumbs |
| Support hub | `customer.support.index` | unchanged (redirect only) |
| Travelers | `customer.travelers.*` | deferred to Phase 5 (shared views) |

## Files

- `resources/views/dashboard/customer/bookings/show.blade.php`
- `resources/views/dashboard/customer/support/tickets/{index,create,show}.blade.php`
- `resources/views/profile/edit-frontend.blade.php`
- `resources/views/themes/customer/jetpakistan/bookings/show.blade.php`
- `public/themes/frontend/jetpakistan/css/portal.css` (+ breadcrumb styles, `portal.css?v=41`)
- `tests/Feature/Dashboard/CustomerDetailSupportNavigationTest.php`
- `tests/playwright/jetpk-dashboard/customer-detail-support.spec.ts`
- `tests/playwright/jetpk-dashboard/ensure-fixtures.php` (shared Playwright fixture helper)
- `tests/playwright/jetpk-dashboard/global-setup.ts`

## Verification

```bash
php artisan view:clear
php artisan test tests/Feature/Dashboard/CustomerDetailSupportNavigationTest.php
npx playwright test -c playwright.jetpk-dashboard.config.ts --project=customer-detail-support
```
