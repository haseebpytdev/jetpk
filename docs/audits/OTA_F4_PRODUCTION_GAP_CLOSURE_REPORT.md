# OTA F4 Production Gap Closure Report

Generated: 2026-06-17T18:00:00+00:00  
Phase: **OTA-DEVCP-F4-PRODUCTION-GAP-CLOSURE**

## Objective

Close remaining production polish gaps from smoke scan and prior logs without risky new features, DB cleanup, or live doc uploads.

## Gap closure status

| # | Gap | Fix | Verification |
|---|-----|-----|----------------|
| 1 | Dev CP Blade typo (`card-title"Platform`) | Local file already correct at line 55; upload if live differs | Visual check on `/dev/cp` after upload |
| 2 | `/password/forgot` 404 | `Route::redirect('/password/forgot', '/forgot-password')` in `routes/web.php` | `FrontendRoutesTest::test_public_route_aliases_redirect_to_canonical_paths` |
| 3 | `/booking-lookup` 404 | `Route::redirect('/booking-lookup', '/lookup-booking')` | Same test |
| 4 | `/flights` 404 | `Route::redirect('/flights', '/')` before flight search group | Same test |
| 5 | Admin booking show 500 on missing Sabre data | `AdminSabreDiagnosticPanelsPresenter` try/catch on `pnrReadinessPanel` + `hostClassificationPanel` | `BookingManagementControllerSmokeTest` |
| 6 | Admin preview 500 on bad row | `BookingManagementController::preview()` try/catch → 422 JSON | `BookingManagementControllerSmokeTest` |
| 7 | Unguarded sync-pnr-itinerary POST | `assertSyncPnrItineraryPostAllowed()` + trait try/catch | Smoke tests for cancelled/non-Sabre bookings |
| 8 | SQL `created_at` on dashboard recent list | `AgencyDashboardService` → `orderByDesc('bookings.created_at')` | `php artisan test --filter=Dashboard` |
| 9 | Schema mismatch (`audit_logs.meta`, `agencies.code`, etc.) | Existing `Schema::hasColumn` / `Agency::restrictedSelectColumns()` — no new migration | Audit grep; no new 500 paths found |
| 10 | Auth notification fail-soft | Existing try/catch confirmed; added `AuthLoginNotificationFailSoftTest` | `php artisan test --filter=AuthLoginNotificationFailSoft` |
| 11 | Dev CP smoke readiness | F3 routes/nav unchanged; companies legacy redirect only | `php artisan test --filter=Developer` |
| 12 | `APP_DEBUG=true` on live | Documented recommendation only — **do not auto-change `.env`** | Ops manual step after F4/F5 smoke |

## Files changed this pass (local)

- `routes/web.php`
- `app/Support/Bookings/AdminSabreDiagnosticPanelsPresenter.php`
- `app/Http/Controllers/Concerns/HandlesSabrePnrItinerarySync.php`
- `app/Support/Bookings/AdminBookingSupplierActions.php`
- `app/Http/Controllers/Admin/BookingManagementController.php`
- `app/Services/Dashboard/AgencyDashboardService.php`
- `tests/Feature/FrontendRoutesTest.php`
- `tests/Feature/Admin/BookingManagementControllerSmokeTest.php`
- `tests/Feature/Auth/AuthLoginNotificationFailSoftTest.php` (new)
- `docs/audits/OTA_F4_PRODUCTION_GAP_CLOSURE_REPORT.md` (new)
- `docs/audits/OTA_SECURITY_HARDENING_REPORT.md` (updated)
- `docs/audits/OTA_DEV_CP_GAP_REPORT.md` (updated)
- `summary.md`

## APP_DEBUG recommendation (live ops)

After F4/F5 smoke passes on production:

1. Set **`APP_DEBUG=false`** in live `.env` (currently flagged **unsafe** in security audit).
2. Run `php artisan config:clear` on server.
3. Re-smoke public/auth/Dev CP routes and `tail storage/logs/laravel.log`.

Do not print or commit env secrets.

## Remaining gaps (expected / out of scope)

- **`APP_DEBUG=true` on live** — ops action required; not changed in F4.
- **Turnstile API outage** — fail-closed on support/lookup forms by design.
- **Full manual QA** (desktop/tablet/mobile all portals) — deferred per sprint workflow.
- **Dead view** `resources/views/developer/companies/index.blade.php` — orphan; not rendered.
- **`bookings.meta` on very old DB** — if column never migrated, writes may still fail; additive migration may be needed later (not in F4 scope).
- **Dev CP Blade typo** — if live still broken, upload `index.blade.php` only.

## Verification commands (local)

```text
php -l (all changed PHP) — pass
composer dump-autoload -o — pass
php artisan optimize:clear — pass
php artisan route:list | grep -Ei "password|booking-lookup|lookup-booking|flights|dev\.cp|admin.bookings"
php artisan migrate:status
php artisan test --filter=BookingManagement — 6 passed
php artisan test --filter=BookingReport — 2 passed
php artisan test --filter=Dashboard — 89 passed, 10 failed (pre-existing agent dashboard / unrelated; not F4 scope)
php artisan test --filter=Auth — 160 passed, 20 failed (pre-existing agency_admin 403 / unrelated; not F4 scope)
php artisan test --filter=Developer — 66 passed
php artisan test --filter=AuthLoginNotificationFailSoft — 1 passed
php artisan test --filter=test_public_route_aliases — 1 passed
```
