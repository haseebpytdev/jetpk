# Dashboard Production Deployment — DASH-13 (Operator Hard-Closure)

**Domain:** https://jetpakistan.pk  
**Status:** Operator package — **not deployed from Cursor**

## Verified runtime

| Item | Value |
|------|-------|
| OS | AlmaLinux 8.10 x86_64 |
| Node | v24.18.0 (`/home/pkjetp/.nvm/versions/node/v24.18.0/bin/node`) |
| npm | 11.16.0 |
| PM2 | 7.0.3 fork mode, process `jetpk-dashboard` |
| Bind | 127.0.0.1:3001 |
| Watchdog health | `http://127.0.0.1:3001/admin/dashboard` → **HTTP 200** (direct Next.js, no Laravel auth) |
| Persistence | PM2 + one-minute cron watchdog + `pm2 save` after online |

## Step 0 — Port check

```bash
ss -ltnp 2>/dev/null | grep ":3001" || netstat -ltnp 2>/dev/null | grep ":3001" || echo "PORT_3001_FREE"
```

## Step 1 — Backup (persists path + manifest)

```bash
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP="/home/pkjetp/backups/dashboard-cutover-$STAMP"
APP=/home/pkjetp/jetpk_app
MANIFEST="$BACKUP/jetpk_app/MANIFEST.tsv"

mkdir -p "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice"
mkdir -p "$BACKUP/jetpk_app/config"
mkdir -p "$BACKUP/jetpk_app/routes"
mkdir -p "$BACKUP/jetpk_app/dashboard"

record() {
  local path="$1" existed="$2" action="$3"
  printf '%s\t%s\t%s\n' "$path" "$existed" "$action" >>"$MANIFEST"
}

for f in routes/admin.php routes/staff.php routes/web.php; do
  if [ -f "$APP/$f" ]; then
    cp -a "$APP/$f" "$BACKUP/jetpk_app/$f"
    record "$f" yes restore
  else
    record "$f" no restore
  fi
done

if [ -f "$APP/config/dashboard.php" ]; then
  cp -a "$APP/config/dashboard.php" "$BACKUP/jetpk_app/config/dashboard.php"
  record "config/dashboard.php" yes restore
else
  record "config/dashboard.php" no delete_on_rollback
fi

if [ -f "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" ]; then
  cp -a "$APP/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" \
    "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php"
  record "app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" yes restore
else
  record "app/Http/Controllers/BackOffice/BackOfficeDashboardController.php" no delete_on_rollback
fi

if [ -d "$APP/dashboard" ]; then
  cp -a "$APP/dashboard" "$BACKUP/jetpk_app/"
  record "dashboard/" yes restore_tree
else
  record "dashboard/" no delete_on_rollback
fi

printf '%s\n' "$BACKUP" > "$APP/storage/app/dash13-last-backup-path.txt"
test -d "$BACKUP"
cat "$APP/storage/app/dash13-last-backup-path.txt"
cat "$MANIFEST"
```

## Step 2 — Maintenance

```bash
/opt/alt/php-fpm83/usr/bin/php /home/pkjetp/jetpk_app/artisan down
```

## Step 3 — SFTP

Execute all mkdir and put commands from operator package (81 mkdir + 369 put + watchdog).

## Step 4 — `.env` (idempotent)

```bash
ENV_FILE=/home/pkjetp/jetpk_app/.env
ENV_BACKUP=/home/pkjetp/jetpk_app/.env.dash13-backup-$(date -u +%Y%m%dT%H%M%SZ)
cp -a "$ENV_FILE" "$ENV_BACKUP"

python3 <<'PY'
import pathlib, re
path = pathlib.Path("/home/pkjetp/jetpk_app/.env")
text = path.read_text(encoding="utf-8", errors="replace").splitlines()
updates = {
    "DASHBOARD_NEXT_SERVER_URL": "http://127.0.0.1:3001",
    "DASHBOARD_NEXT_PROXY_ENABLED": "true",
}
seen = set()
out = []
for line in text:
    m = re.match(r"^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=", line)
    if m and m.group(1) in updates:
        key = m.group(1)
        out.append(f"{key}={updates[key]}")
        seen.add(key)
    else:
        out.append(line)
for key, val in updates.items():
    if key not in seen:
        out.append(f"{key}={val}")
path.write_text("\n".join(out) + "\n", encoding="utf-8")
PY

grep -E '^DASHBOARD_NEXT_SERVER_URL=|^DASHBOARD_NEXT_PROXY_ENABLED=' "$ENV_FILE"
```

## Step 5 — Build

```bash
export HOME=/home/pkjetp
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
cd /home/pkjetp/jetpk_app/dashboard
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm ci
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm run build
```

## Step 6 — PM2 start (fork, single process)

```bash
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
PM2=/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2
cd /home/pkjetp/jetpk_app/dashboard
$PM2 delete jetpk-dashboard 2>/dev/null || true
$PM2 start /home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm \
  --name jetpk-dashboard \
  --cwd /home/pkjetp/jetpk_app/dashboard \
  --interpreter none \
  -- run start
$PM2 status jetpk-dashboard
curl -sS -o /dev/null -w "node_admin %{http_code}\n" http://127.0.0.1:3001/admin/dashboard
```

## Step 7 — PM2 save (only after online)

```bash
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 save
```

## Step 8 — Watchdog + cron

```bash
mkdir -p /home/pkjetp/bin /home/pkjetp/logs
chmod 755 /home/pkjetp/bin/ensure-jetpk-dashboard.sh
TMP=/tmp/pkjetp.cron.$$
crontab -l 2>/dev/null > "$TMP" || true
grep -q 'schedule:run' "$TMP" || echo '* * * * * /opt/alt/php-fpm83/usr/bin/php /home/pkjetp/jetpk_app/artisan schedule:run >> /dev/null 2>&1' >> "$TMP"
grep -q 'ensure-jetpk-dashboard' "$TMP" || echo '* * * * * /bin/bash /home/pkjetp/bin/ensure-jetpk-dashboard.sh' >> "$TMP"
crontab "$TMP"
rm -f "$TMP"
crontab -l
```

## Step 9 — Laravel caches

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
cd "$APP"
$PHP artisan route:clear
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan view:clear
$PHP artisan optimize
$PHP artisan route:list | grep -E "admin/dashboard|staff/dashboard|testdash"
$PHP artisan up
```

## Step 10 — Live checks (jetpakistan.pk)

```bash
curl -sS -o /dev/null -w "session %{http_code}\n" https://jetpakistan.pk/api/dashboard/session
curl -sS -I https://jetpakistan.pk/login
curl -sS -I https://jetpakistan.pk/admin/dashboard
curl -sS -I https://jetpakistan.pk/staff/dashboard
curl -sS -I https://jetpakistan.pk/testdash
```

| URL | Unauthenticated expected |
|-----|--------------------------|
| `/api/dashboard/session` | **401** |
| `/login` | **200** |
| `/admin/dashboard` | **302** → login |
| `/staff/dashboard` | **302** → login |
| `/testdash` | **302** → login or role dashboard |

## Post-sign-off merge

```bash
git fetch jetpk
git checkout main
git pull jetpk main
git merge --no-ff phase/jetpk-dash-13-admin-staff-production-cutover -m "merge(dashboard): DASH-13 admin/staff production cutover"
git push jetpk main
```

See `DASHBOARD-ROLLBACK.md` for rollback.
