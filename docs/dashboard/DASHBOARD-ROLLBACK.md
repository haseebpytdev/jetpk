# Dashboard Rollback — DASH-13

## When to rollback

- Live authentication failure on `/admin/dashboard` or `/staff/dashboard`
- RBAC regression (staff seeing forbidden modules with live data)
- Public site regression
- Unresolved 500s in dashboard routes

## Backup location (pre-cutover)

```
/home/pkjetp/backups/dashboard-cutover-YYYYMMDD-HHMMSS/jetpk_app
/home/pkjetp/backups/dashboard-cutover-YYYYMMDD-HHMMSS/public_html
```

Back up affected files only: `routes/admin.php`, `routes/staff.php`, `routes/web.php`, `bootstrap/app.php`, `app/Http/Controllers/BackOffice/`, `storage/app/back-office-dashboard/`, `public/_next/`.

## Rollback steps

1. `php artisan down`
2. Restore backed-up Laravel route files and remove `BackOfficeDashboardController` registration if added separately
3. Restore previous `public/_next` or remove if newly added
4. Remove `storage/app/back-office-dashboard/` export if causing issues
5. `php artisan route:clear && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize`
6. `php artisan up`
7. Verify `/admin` and `/staff` legacy Blade dashboards render
8. Verify public site and Agent/Customer portals

## Must not affect

- Bookings, payments, PNR data
- Supplier credentials
- Sabre cancellation gates (`SABRE_CANCEL_*`)
- Agent/Customer dashboards

## Rollback test

Documented commands are complete; **do not execute** unless deployment fails.
