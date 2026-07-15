# JetPK 9G Deployment Plan

Phase: **JETPK-DASHBOARD-SYSTEM-PAGE-BUILDER-EMAIL-PACK-CLOSURE-9G**

## Changed files

### PHP
- `app/Support/Audits/JetpkDashboardRouteAuditService.php` (new)
- `app/Console/Commands/JetpkDashboardRouteAuditCommand.php` (new)
- `app/Console/Commands/JetpkDashboardThemeAuditCommand.php` (new)
- `app/Console/Commands/JetpkPageSettingsAuditCommand.php` (new)
- `app/Support/Client/ClientPageKeys.php`
- `app/Support/Client/ClientPageSectionSchema.php` (new)
- `app/Services/Client/ClientPageContentResolver.php`
- `app/Http/Controllers/Admin/ClientPageSettingsController.php`
- `app/Support/Emails/JetpkEmailViewResolver.php`
- `app/Support/Emails/JetpkEmailSampleData.php`
- `config/jetpk_email.php`

### Views
- `resources/views/themes/admin/jetpakistan/api-settings/create.blade.php` (new)
- `resources/views/themes/admin/jetpakistan/api-settings/edit.blade.php`
- `resources/views/dashboard/admin/api-settings/form.blade.php`
- `resources/views/themes/admin/jetpakistan/page-settings/edit.blade.php`
- `resources/views/themes/admin/jetpakistan/page-settings/partials/*` (new)
- `resources/views/themes/frontend/jetpakistan/sections/why-book.blade.php`
- `resources/views/emails/themes/jetpakistan/**` (9 new templates)

### Public assets
- `public/themes/admin/jetpakistan/css/dashboard.css` (v5 via layout `?v=5`)

### Docs
- `docs/JETPK_DASHBOARD_DESIGN_SYSTEM.md`
- `docs/JETPK_DASHBOARD_ROUTE_INVENTORY.md`
- `docs/JETPK_PAGE_SETTINGS_AND_PAGE_BUILDER.md`
- `docs/JETPK_EMAIL_TEMPLATE_PACK.md`
- `docs/JETPK_9G_DEPLOYMENT_PLAN.md`
- `docs/JETPK_9G_LIVE_QA_CHECKLIST.md`
- `summary.md`

## Migrations

**None** — page settings use existing `client_page_settings` JSON columns.

## Backup

```bash
# On server before upload
mysqldump -u USER -p DATABASE client_page_settings client_page_assets > jetpk-9g-backup.sql
tar czf jetpk-9g-files-backup.tgz public/themes/admin/jetpakistan resources/views/themes/admin/jetpakistan
```

## SFTP upload

```bash
sftp -P 22 pkjetp@65.109.34.176
```

Upload all changed files preserving paths under project root.

## SSH deployment

```bash
cd /home/pkjetp/domains/jetpakistan.pk/public_html   # adjust to actual path
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan jetpk:dashboard-route-audit --client=jetpk
php artisan jetpk:dashboard-theme-audit --client=jetpk
php artisan jetpk:page-settings-audit
php artisan jetpk:live-theme-audit
php artisan ota:jetpk-email-template-audit
php artisan ota:route-page-health-audit --all --skip-source-scan
php artisan ota:jetpk-flow-leak-audit
```

## Rollback

1. Restore files from `jetpk-9g-files-backup.tgz`
2. Revert `dashboard.blade.php` asset version to `?v=4` if needed
3. `php artisan optimize:clear`

## Business logic

Supplier credential encryption, booking, payment, PNR, and ticketing logic **unchanged** — UI/form layout and page settings content only.
