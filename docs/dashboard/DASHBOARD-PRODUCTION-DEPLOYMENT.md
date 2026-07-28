# Dashboard Production Deployment — DASH-13 (Operator Package)

**Status:** Operator-ready. **Live upload not executed from Cursor.**

## Verified production runtime

| Item | Value |
|------|-------|
| OS | AlmaLinux 8.10 (x86_64, glibc 2.28) |
| Node.js | **v24.18.0** (`/home/pkjetp/.nvm/versions/node/v24.18.0/bin/node`) |
| npm | **11.16.0** |
| PM2 | **7.0.3** (fork mode) |
| Next.js | **15.5.21** (from `package-lock.json`) |
| Bind | **127.0.0.1:3001** |
| Laravel proxy | `http://127.0.0.1:3001` (30s timeout) |
| Persistence | PM2 + **one-minute cron watchdog** + `pm2 save` |
| PHP | 8.3 — `/opt/alt/php-fpm83/usr/bin/php` |

## Server paths

| Purpose | Path |
|---------|------|
| Laravel app | `/home/pkjetp/jetpk_app` |
| Public webroot | `/home/pkjetp/public_html` |
| Dashboard runtime | `/home/pkjetp/jetpk_app/dashboard` |
| PM2 home | `/home/pkjetp/.pm2` |
| Logs | `/home/pkjetp/logs` |
| Watchdog script | `/home/pkjetp/bin/ensure-jetpk-dashboard.sh` |
| Backups | `/home/pkjetp/backups/dashboard-cutover-$STAMP/` |

## Environment (Laravel `.env`)

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

Rollback line:

```env
DASHBOARD_NEXT_PROXY_ENABLED=false
```

## Deployment order

1. Verify port 3001 free
2. Create backup
3. Maintenance mode
4. SFTP Laravel files (5)
5. SFTP dashboard source (369 files)
6. Update `.env`
7. `npm ci && npm run build`
8. PM2 start `jetpk-dashboard`
9. Node health check
10. Install watchdog script + cron
11. `pm2 save`
12. Laravel cache clear/optimize
13. `artisan up`
14. Live verification
15. Merge after sign-off only

## Step 0 — Pre-flight

```bash
ss -ltnp 2>/dev/null | grep ":3001" || netstat -ltnp 2>/dev/null | grep ":3001" || echo "PORT_3001_FREE"
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
node -v   # expect v24.18.0
npm -v    # expect 11.16.0
pm2 -v    # expect 7.0.3
```

## Step 1 — Backup

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP=/home/pkjetp/backups/dashboard-cutover-$STAMP
mkdir -p "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice"
mkdir -p "$BACKUP/jetpk_app/config"
mkdir -p "$BACKUP/jetpk_app/routes"
mkdir -p "$BACKUP/jetpk_app/dashboard"
test -f /home/pkjetp/jetpk_app/routes/admin.php && cp -a /home/pkjetp/jetpk_app/routes/admin.php "$BACKUP/jetpk_app/routes/"
test -f /home/pkjetp/jetpk_app/routes/staff.php && cp -a /home/pkjetp/jetpk_app/routes/staff.php "$BACKUP/jetpk_app/routes/"
test -f /home/pkjetp/jetpk_app/routes/web.php && cp -a /home/pkjetp/jetpk_app/routes/web.php "$BACKUP/jetpk_app/routes/"
test -f /home/pkjetp/jetpk_app/config/dashboard.php && cp -a /home/pkjetp/jetpk_app/config/dashboard.php "$BACKUP/jetpk_app/config/"
test -f /home/pkjetp/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php && cp -a /home/pkjetp/jetpk_app/app/Http/Controllers/BackOffice/BackOfficeDashboardController.php "$BACKUP/jetpk_app/app/Http/Controllers/BackOffice/"
test -d /home/pkjetp/jetpk_app/dashboard && cp -a /home/pkjetp/jetpk_app/dashboard "$BACKUP/jetpk_app/" || true
echo "BACKUP=$BACKUP" | tee "$BACKUP/STAMP.txt"
ls -la "$BACKUP"
```

## Step 2 — Maintenance

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
cd "$APP"
$PHP artisan down
```

## Step 3 — SFTP upload

Run every `mkdir` and `put` from `docs/dashboard/DASH-13-SFTP-COMMANDS.txt` (81 mkdir + 374 put).

## Step 4 — Environment

Edit `/home/pkjetp/jetpk_app/.env` — append or update:

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

## Step 5 — Build

```bash
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
export HOME=/home/pkjetp
cd /home/pkjetp/jetpk_app/dashboard
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm ci
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm run build
```

## Step 6 — PM2 start (fork mode, single process)

```bash
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:$PATH"
cd /home/pkjetp/jetpk_app/dashboard
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 delete jetpk-dashboard 2>/dev/null || true
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 start /home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm \
  --name jetpk-dashboard \
  --cwd /home/pkjetp/jetpk_app/dashboard \
  --interpreter none \
  -- run start
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 status jetpk-dashboard
```

## Step 7 — Node health

```bash
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" http://127.0.0.1:3001/admin/dashboard
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" http://127.0.0.1:3001/staff/dashboard
```

## Step 8 — Watchdog script

```bash
mkdir -p /home/pkjetp/bin /home/pkjetp/logs
cp /home/pkjetp/jetpk_app/docs/dashboard/ensure-jetpk-dashboard.sh /home/pkjetp/bin/ensure-jetpk-dashboard.sh 2>/dev/null || \
  cat > /home/pkjetp/bin/ensure-jetpk-dashboard.sh <<'WATCHDOG_EOF'
# paste contents from docs/dashboard/ensure-jetpk-dashboard.sh
WATCHDOG_EOF
chmod 755 /home/pkjetp/bin/ensure-jetpk-dashboard.sh
```

Or upload `docs/dashboard/ensure-jetpk-dashboard.sh` to `/home/pkjetp/bin/ensure-jetpk-dashboard.sh` via SFTP.

## Step 9 — Cron (preserve Laravel scheduler)

```bash
TMP=/tmp/pkjetp.cron.$$
crontab -l 2>/dev/null > "$TMP" || true
grep -q 'schedule:run' "$TMP" || echo '* * * * * /opt/alt/php-fpm83/usr/bin/php /home/pkjetp/jetpk_app/artisan schedule:run >> /dev/null 2>&1' >> "$TMP"
grep -q 'ensure-jetpk-dashboard' "$TMP" || echo '* * * * * /bin/bash /home/pkjetp/bin/ensure-jetpk-dashboard.sh' >> "$TMP"
crontab "$TMP"
rm -f "$TMP"
crontab -l
```

## Step 10 — PM2 save

```bash
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 save
```

## Step 11 — Laravel caches

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
```

## Step 12 — Application up

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
cd /home/pkjetp/jetpk_app
$PHP artisan up
```

## Step 13 — Laravel proxy health

```bash
curl -sS -o /dev/null -w "session %{http_code}\n" http://127.0.0.1/api/dashboard/session
# Expect 401 unauthenticated from public webroot path — verify via browser when logged in:
# /admin/dashboard, /staff/dashboard
```

## Step 14 — Logs

```bash
export PM2_HOME=/home/pkjetp/.pm2
/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2 logs jetpk-dashboard --lines 50 --nostream
tail -n 50 /home/pkjetp/logs/jetpk-dashboard-watchdog.log
tail -n 50 /home/pkjetp/jetpk_app/storage/logs/laravel.log
```

## Live verification checklist

- [ ] `/dashboard` — role-based redirect
- [ ] `/admin/dashboard` — authenticated admin shell
- [ ] `/staff/dashboard` — authenticated staff shell
- [ ] `/testdash` — redirect only (not preview)
- [ ] `/api/dashboard/session` — 401 when unauthenticated
- [ ] `/admin/dashboard/bookings`, `/payments`, `/pnrs`, `/users`, `/settings`
- [ ] `/staff/dashboard/bookings`, `/payments`, `/pnrs`, `/reports`
- [ ] Agent Dashboard — legacy Blade (not Next.js)
- [ ] Agent Staff Dashboard — legacy (not Next.js)
- [ ] Customer Dashboard — unchanged
- [ ] Public homepage, login, flights search
- [ ] PM2 `jetpk-dashboard` online after SSH disconnect
- [ ] Watchdog log shows no restart loop

## Production file manifest

**Laravel (5):** `BackOfficeDashboardController.php`, `config/dashboard.php`, `routes/admin.php`, `routes/staff.php`, `routes/web.php`

**Dashboard (369 source files):** per `DASH-13-SFTP-COMMANDS.txt`

**Excluded:** `node_modules/`, `.next/`, `tests/`, playwright artifacts, `eslint.config.mjs`, `playwright*.config.ts`, `scripts/sync-dashboard-export.mjs`, `scripts/generate-sftp-commands.ps1`

## Post-verification merge (operator only, after sign-off)

```bash
git fetch jetpk
git checkout main
git pull jetpk main
git merge --no-ff phase/jetpk-dash-13-admin-staff-production-cutover -m "merge(dashboard): DASH-13 admin/staff production cutover"
git push jetpk main
```

See `DASHBOARD-ROLLBACK.md` for rollback.
