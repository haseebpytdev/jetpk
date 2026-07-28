# Dashboard Rollback — DASH-13

Loads backup path from `/home/pkjetp/jetpk_app/storage/app/dash13-last-backup-path.txt`.

## Rollback `.env`

```bash
ENV_FILE=/home/pkjetp/jetpk_app/.env
ENV_BACKUP=/home/pkjetp/jetpk_app/.env.dash13-backup-$(date -u +%Y%m%dT%H%M%SZ)
cp -a "$ENV_FILE" "$ENV_BACKUP"

python3 <<'PY'
import pathlib, re
path = pathlib.Path("/home/pkjetp/jetpk_app/.env")
text = path.read_text(encoding="utf-8", errors="replace").splitlines()
out = []
for line in text:
    m = re.match(r"^\s*(DASHBOARD_NEXT_PROXY_ENABLED)\s*=", line)
    if m:
        out.append("DASHBOARD_NEXT_PROXY_ENABLED=false")
    else:
        out.append(line)
if not any(l.startswith("DASHBOARD_NEXT_PROXY_ENABLED=") for l in out):
    out.append("DASHBOARD_NEXT_PROXY_ENABLED=false")
path.write_text("\n".join(out) + "\n", encoding="utf-8")
PY

grep -E '^DASHBOARD_NEXT_PROXY_ENABLED=' "$ENV_FILE"
```

## Full rollback

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
BACKUP=$(cat "$APP/storage/app/dash13-last-backup-path.txt")
MANIFEST="$BACKUP/jetpk_app/MANIFEST.tsv"
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
PM2=/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2

test -d "$BACKUP/jetpk_app" || { echo "BACKUP_MISSING: $BACKUP"; exit 1; }

cd "$APP" && $PHP artisan down

$PM2 stop jetpk-dashboard 2>/dev/null || true
$PM2 delete jetpk-dashboard 2>/dev/null || true
$PM2 save --force

TMP=/tmp/pkjetp.cron.$$
crontab -l 2>/dev/null | grep -v 'ensure-jetpk-dashboard' > "$TMP" || true
crontab "$TMP"
rm -f "$TMP"

# Restore routes (always existed pre-cutover)
cp -a "$BACKUP/jetpk_app/routes/admin.php" "$APP/routes/admin.php"
cp -a "$BACKUP/jetpk_app/routes/staff.php" "$APP/routes/staff.php"
cp -a "$BACKUP/jetpk_app/routes/web.php" "$APP/routes/web.php"

# config/dashboard.php
if grep -q $'config/dashboard.php\tyes\t' "$MANIFEST" 2>/dev/null || [ -f "$BACKUP/jetpk_app/config/dashboard.php" ]; then
  cp -a "$BACKUP/jetpk_app/config/dashboard.php" "$APP/config/dashboard.php"
else
  rm -f "$APP/config/dashboard.php"
fi

# BackOfficeDashboardController
if grep -q $'BackOfficeDashboardController.php\tyes\t' "$MANIFEST" 2>/dev/null || [ -f "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" ]; then
  cp -a "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" \
    "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php"
else
  rm -f "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php"
fi

# dashboard tree
if [ -d "$BACKUP/jetpk_app/dashboard" ]; then
  rm -rf "$APP/dashboard"
  cp -a "$BACKUP/jetpk_app/dashboard" "$APP/dashboard"
else
  rm -rf "$APP/dashboard"
fi

# .env rollback (proxy disabled) — run block above first

$PHP artisan route:clear
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan view:clear
$PHP artisan optimize
$PHP artisan up

curl -sS -o /dev/null -w "login %{http_code}\n" https://jetpakistan.pk/login
curl -sS -I https://jetpakistan.pk/admin/dashboard
```

**PM2 rollback strategy:** After `pm2 delete jetpk-dashboard`, run `pm2 save --force` to persist the empty process list. This removes `jetpk-dashboard` from the dump without leaving a stale entry.

## Must not affect

Bookings, payments, PNR data, supplier credentials, `SABRE_CANCEL_*` gates, Agent/Customer dashboards.
