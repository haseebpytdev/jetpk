# Dashboard Production Deployment — DASH-13

**Status:** Pre-deployment validation complete locally. **Live upload not executed.**

## Server paths

| Purpose | Path |
|---------|------|
| Laravel app | `/home/pkjetp/jetpk_app` |
| Public webroot | `/home/pkjetp/public_html` |
| Dashboard app | `/home/pkjetp/jetpk_app/dashboard` |
| Optional static HTML | `/home/pkjetp/jetpk_app/storage/app/back-office-dashboard/{admin\|staff}/dashboard` |
| Backups | `/home/pkjetp/backups/dashboard-cutover-$STAMP/` |

## Runtime requirements (from code)

| Item | Value | Source |
|------|-------|--------|
| Node.js | `^18.18.0 \|\| ^19.8.0 \|\| >= 20.0.0` (recommend **20 LTS** or **22 LTS**) | `dashboard/package-lock.json` → `node_modules/next.engines` |
| npm | Compatible with lockfile (`npm ci`); local validation used **11.4.2** | Local toolchain |
| Next.js | **15.5.21** (locked) | `dashboard/package-lock.json` |
| React | **19.x** | `dashboard/package.json` |
| Production port | **3001** | `dashboard/package.json` → `"start": "next start -p 3001"` |
| Playwright/smoke port | **3002** (local only) | `dashboard/package.json` → `"start:smoke"` |
| Host binding | Next.js default (`0.0.0.0` for `next start`; reachable at `127.0.0.1:3001` from PHP) | Next.js 15 default |
| Startup command | `npm run start` → `next start -p 3001` | `dashboard/package.json` |
| Working directory | `/home/pkjetp/jetpk_app/dashboard` | Deployment layout |
| Laravel proxy URL | `http://127.0.0.1:3001` | `config/dashboard.php` default |
| Laravel proxy timeout | **30 seconds** | `BackOfficeDashboardController::proxyToNextServer()` |
| Proxy failure | Returns `null` → **503** if static HTML also absent | `BackOfficeDashboardController` |
| Memory | Plan for **≥512 MB** Node heap on shared hosting; monitor during first `npm run build` | Operator sizing |
| Build strategy | **Server-side** `npm ci && npm run build` after SFTP source upload (do **not** upload `.next` or `node_modules`) | Static export blocked by `searchParams` on module pages |

## Process manager decision

**Blocked until operator runs server capability commands** (see Part D in phase summary).

Preferred hierarchy:

1. cPanel **Setup Node.js App** (persistent, reboot-safe) — preferred if available under `pkjetp`
2. **PM2** under `pkjetp` — if `command -v pm2` succeeds
3. **systemd** — only if account has unit-file privileges (unlikely on shared cPanel)
4. Do **not** use `nohup npm start &` as the final strategy

### Template A — cPanel Node.js App

| Action | Command |
|--------|---------|
| App root | `/home/pkjetp/jetpk_app/dashboard` |
| Startup file | `node_modules/next/dist/bin/next` |
| Args | `start -p 3001` |
| Node version | 20.x or 22.x per engines |
| Stop | cPanel → Stop application |
| Restart | cPanel → Restart application |
| Status | cPanel → application status panel |
| Logs | cPanel → application log / `~/logs/` |
| Reboot persistence | Enabled when created via cPanel Node.js manager |

### Template B — PM2 (only if installed)

```bash
cd /home/pkjetp/jetpk_app/dashboard
pm2 start npm --name jetpk-dashboard -- run start
pm2 save
pm2 startup   # follow printed instructions if permitted
```

| Action | Command |
|--------|---------|
| Stop | `pm2 stop jetpk-dashboard` |
| Restart | `pm2 restart jetpk-dashboard` |
| Status | `pm2 status jetpk-dashboard` |
| Logs | `pm2 logs jetpk-dashboard --lines 200` |

## Environment contract

Laravel (`/home/pkjetp/jetpk_app/.env`):

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

| Variable | Mandatory | Default (code) | Accepted values | Production | Rollback / disabled |
|----------|-----------|----------------|-----------------|------------|---------------------|
| `DASHBOARD_NEXT_SERVER_URL` | No (has default) | `http://127.0.0.1:3001` | Valid HTTP(S) base URL, no trailing path | `http://127.0.0.1:3001` | Remove or set empty + disable proxy |
| `DASHBOARD_NEXT_PROXY_ENABLED` | No | `true` | `true`, `false`, `1`, `0` (PHP `env()` bool cast) | `true` | `false` |

**Failure behavior:**

- Proxy disabled or URL empty → static HTML only; else **503**
- Node unreachable → connection exception → **503**
- Upstream non-2xx → **503**

**Config cache:** Values are read via `env()` in `config/dashboard.php`. After `.env` changes run `php artisan config:clear` before `config:cache`, or run `config:clear` during cutover.

Dashboard (`/home/pkjetp/jetpk_app/dashboard/.env` — optional; not required for proxy-only cutover):

```env
NEXT_PUBLIC_DASHBOARD_MODE=production
NEXT_PUBLIC_USE_MOCK_DATA=false
NEXT_PUBLIC_ALLOW_MUTATIONS=false
```

## Pre-deploy local validation (completed)

```bash
cd dashboard
npm ci
npm run build
# Verified server on :3002, then:
npx playwright test -c playwright.reuse.config.ts --retries=0 --shard=1/6
# … through --shard=6/6 (union = 1080 tests, 49 files)
```

**Result:** 1080 passed, 0 failed, 0 flaky, 0 skipped, retries=0 (after `pnrs-filters` portal-prefix fix).

## Production file manifest

### Laravel (5 files)

- `app/Http/Controllers/BackOffice/BackOfficeDashboardController.php`
- `config/dashboard.php`
- `routes/admin.php`
- `routes/staff.php`
- `routes/web.php`

### Dashboard source (364 files — see full list)

Complete per-file SFTP commands: **`docs/dashboard/DASH-13-SFTP-COMMANDS.txt`**

Includes: `app/`, `components/`, `features/`, `layouts/`, `lib/`, `mocks/`, `services/`, `types/`, `public/`, `scripts/build-production.mjs`, `next.config.ts`, `package.json`, `package-lock.json`, `tsconfig.json`, `postcss.config.mjs`, `tailwind.config.ts`, `next-env.d.ts`, `.env.example`

**Excluded from upload:** `node_modules/`, `.next/`, `tests/`, `test-results/`, `playwright-report/`, `playwright.config.ts`, `playwright.reuse.config.ts`, `eslint.config.mjs`, `scripts/sync-dashboard-export.mjs`, `scripts/generate-sftp-commands.ps1`

## Server capability check (operator — run before deploy)

```bash
whoami
hostname
node -v
npm -v
command -v node
command -v npm
command -v pm2
pm2 -v
ps -ef | grep -E "next|node|pm2" | grep -v grep
ss -ltnp 2>/dev/null | grep ":3001" || netstat -ltnp 2>/dev/null | grep ":3001"
ulimit -a
free -m
df -h
ls -ld /home/pkjetp/jetpk_app
ls -ld /home/pkjetp/public_html
/opt/alt/php-fpm83/usr/bin/php -r "var_dump(function_exists('curl_init'));"
curl --version
```

## Backup manifest (before upload)

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
ls -la "$BACKUP"
```

Record new paths for rollback deletion: entire `dashboard/` tree if first deploy; `BackOfficeDashboardController.php`, `config/dashboard.php` if new.

## SFTP commands

**Full mkdir and put list:** `docs/dashboard/DASH-13-SFTP-COMMANDS.txt` (81 mkdir + 374 put).

Regenerate locally:

```powershell
powershell -File dashboard/scripts/generate-sftp-commands.ps1 > docs/dashboard/DASH-13-SFTP-COMMANDS.txt
```

## Server deployment commands (do not run until operator approves)

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP=/home/pkjetp/backups/dashboard-cutover-$STAMP
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app

# 1. Verify backup exists
test -d "$BACKUP/jetpk_app/routes" && echo "BACKUP_OK" || { echo "BACKUP_MISSING"; exit 1; }

# 2. Maintenance
cd "$APP"
$PHP artisan down

# 3. After SFTP upload — dashboard build
cd "$APP/dashboard"
npm ci
npm run build

# 4. Laravel env (append if missing — edit manually, do not overwrite .env)
# DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
# DASHBOARD_NEXT_PROXY_ENABLED=true

# 5. Laravel caches
cd "$APP"
$PHP artisan route:clear
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan view:clear

# 6. Start / restart Node (use Template A or B above)
# pm2 restart jetpk-dashboard   # example

# 7. Node health
curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/admin/dashboard | grep -E '^200$'

# 8. Optimize Laravel
$PHP artisan optimize

# 9. Route verification
$PHP artisan route:list | grep -E "admin/dashboard|staff/dashboard|testdash"

# 10. Application up
$PHP artisan up

# 11. Laravel proxy smoke (authenticated — manual or staging cookie)
# curl -I https://<jetpakistan-host>/admin/dashboard

# 12. Logs
# pm2 logs jetpk-dashboard --lines 50
tail -n 50 "$APP/storage/logs/laravel.log"
```

## Post-deploy verification

- `/dashboard` role redirects
- `/admin/dashboard`, `/staff/dashboard` authenticated
- `/testdash` redirects (not previewable)
- `/api/dashboard/session` 401 unauthenticated
- Public site smoke (homepage, login, flights search)
- Agent/Customer dashboards unchanged

See `docs/dashboard/DASHBOARD-ROLLBACK.md` for rollback.
