# Dashboard Rollback — DASH-13

**Status:** Documented only. **Do not execute** unless live cutover fails.

## When to rollback

- Live authentication failure on `/admin/dashboard` or `/staff/dashboard`
- RBAC regression (staff seeing forbidden modules with live data)
- Public site regression
- Unresolved 500s or persistent 503 on dashboard routes
- Node process cannot be stabilized on port 3001

## Backup location

```
/home/pkjetp/backups/dashboard-cutover-YYYYMMDD-HHMMSS/jetpk_app/
```

Created before upload per `DASHBOARD-PRODUCTION-DEPLOYMENT.md`.

### Files backed up (overwritten by cutover)

| Path | Notes |
|------|-------|
| `routes/admin.php` | Restores legacy `/admin` Blade entry |
| `routes/staff.php` | Restores legacy `/staff` Blade entry |
| `routes/web.php` | Removes `/testdash` redirect route |
| `config/dashboard.php` | Remove if newly added |
| `app/Http/Controllers/BackOffice/BackOfficeDashboardController.php` | Remove if newly added |
| `dashboard/` (entire tree) | If existed pre-cutover; else delete on rollback |

### New paths to delete on rollback (no prior backup)

- `app/Http/Controllers/BackOffice/BackOfficeDashboardController.php`
- `config/dashboard.php`
- `dashboard/` (full Next.js source tree)
- `storage/app/back-office-dashboard/` (only if static export was synced)

### Process manager

- Stop PM2: `pm2 stop jetpk-dashboard && pm2 delete jetpk-dashboard && pm2 save`
- Or cPanel Node.js → Stop / Remove application

## Rollback commands

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
# Set to the backup taken immediately before cutover:
BACKUP=/home/pkjetp/backups/dashboard-cutover-YYYYMMDD-HHMMSS

test -d "$BACKUP/jetpk_app" || { echo "Set BACKUP to a valid stamp"; exit 1; }

# 1. Maintenance
cd "$APP"
$PHP artisan down

# 2. Stop Node dashboard
pm2 stop jetpk-dashboard 2>/dev/null || true
pm2 delete jetpk-dashboard 2>/dev/null || true
# Or stop via cPanel Node.js manager

# 3. Restore Laravel files
cp -a "$BACKUP/jetpk_app/routes/admin.php" "$APP/routes/admin.php"
cp -a "$BACKUP/jetpk_app/routes/staff.php" "$APP/routes/staff.php"
cp -a "$BACKUP/jetpk_app/routes/web.php" "$APP/routes/web.php"
if [ -f "$BACKUP/jetpk_app/config/dashboard.php" ]; then
  cp -a "$BACKUP/jetpk_app/config/dashboard.php" "$APP/config/dashboard.php"
else
  rm -f "$APP/config/dashboard.php"
fi
if [ -f "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" ]; then
  cp -a "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" \
    "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php"
else
  rm -f "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php"
fi

# 4. Remove new dashboard tree (if no pre-cutover dashboard/)
if [ ! -d "$BACKUP/jetpk_app/dashboard" ]; then
  rm -rf "$APP/dashboard"
elif [ -d "$BACKUP/jetpk_app/dashboard" ]; then
  rm -rf "$APP/dashboard"
  cp -a "$BACKUP/jetpk_app/dashboard" "$APP/dashboard"
fi

# 5. Disable proxy in .env (manual edit — do not commit)
# DASHBOARD_NEXT_PROXY_ENABLED=false
# Remove or comment DASHBOARD_NEXT_SERVER_URL

# 6. Clear caches
$PHP artisan route:clear
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan view:clear
$PHP artisan optimize

# 7. Application up
$PHP artisan up

# 8. Verify legacy behavior
$PHP artisan route:list | grep -E "^.*admin|^.*staff" | head -20
```

## Verification after rollback

1. `/admin` and `/staff` legacy Blade dashboards render
2. Public site homepage, login, flights search
3. Agent `/agent` and Customer `/customer` portals unchanged
4. No 503 on previously working admin/staff entry routes

## Must not affect

- Bookings, payments, PNR database data
- Supplier credentials
- Sabre cancellation gates (`SABRE_CANCEL_*`)
- Agent/Customer dashboards

## Rollback test

Commands are complete and reviewed. **Not executed** in DASH-13 validation.
