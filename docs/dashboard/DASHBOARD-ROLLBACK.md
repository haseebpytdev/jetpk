# Dashboard Rollback — DASH-13

**Execute only if live cutover fails.** Preserves bookings, payments, PNRs, supplier credentials, Sabre gates.

## Rollback commands

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
BACKUP=/home/pkjetp/backups/dashboard-cutover-YYYYMMDD-HHMMSS
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
PM2=/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2

test -d "$BACKUP/jetpk_app" || { echo "Set BACKUP to valid stamp"; exit 1; }

# 1. Maintenance
cd "$APP"
$PHP artisan down

# 2. Stop/delete PM2 process
$PM2 stop jetpk-dashboard 2>/dev/null || true
$PM2 delete jetpk-dashboard 2>/dev/null || true
$PM2 save

# 3. Remove watchdog cron only (preserve Laravel scheduler)
TMP=/tmp/pkjetp.cron.$$
crontab -l 2>/dev/null | grep -v 'ensure-jetpk-dashboard' > "$TMP" || true
crontab "$TMP"
rm -f "$TMP"
crontab -l

# 4. Restore Laravel files
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

# 5. Restore or remove dashboard runtime
if [ -d "$BACKUP/jetpk_app/dashboard" ]; then
  rm -rf "$APP/dashboard"
  cp -a "$BACKUP/jetpk_app/dashboard" "$APP/dashboard"
else
  rm -rf "$APP/dashboard"
fi

# 6. Disable proxy — edit /home/pkjetp/jetpk_app/.env manually:
# DASHBOARD_NEXT_PROXY_ENABLED=false

# 7. Clear caches
$PHP artisan route:clear
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan view:clear
$PHP artisan optimize

# 8. Application up
$PHP artisan up

# 9. Verify legacy routes
$PHP artisan route:list | grep -E "admin|staff" | head -20
```

## Verification after rollback

1. `/admin` and `/staff` legacy Blade dashboards render
2. Agent `/agent` and Customer `/customer` unchanged
3. Public site homepage, login, flights search
4. No persistent 503 on admin/staff entry routes
5. `pm2 list` shows no `jetpk-dashboard`
6. Crontab has Laravel scheduler only (no watchdog line)

## Must not affect

- Database data (bookings, payments, PNRs)
- Supplier credentials
- `SABRE_CANCEL_*` gates
- Agent/Customer dashboard ownership
