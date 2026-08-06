# JETPK-RELEASE-01 — Deployment Plan

**Baseline SHA:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Target:** JetPakistan production (`/home/pkjetp/jetpk_app`, `/home/pkjetp/public_html`)
**Execution:** Operator-only — **not authorized from this audit**

Paths use PHP 8.3 explicitly. No `set -euo pipefail` — each step uses explicit checks.

---

## Constants

```bash
PHP=/usr/local/lsws/lsphp83/bin/php
APP=/home/pkjetp/jetpk_app
PUBLIC=/home/pkjetp/public_html
NODE=/usr/local/bin/node
NPM=/usr/local/bin/npm
# PM2: install during deploy if absent — nvm v24.18.0 not present on server 2026-08-06
PM2=/usr/local/bin/pm2
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/usr/local/bin:$PATH"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP=/home/pkjetp/backups/jetpk-release-01-${STAMP}
```

> **ACCESS-01-R2 note:** Documented `/opt/alt/php-fpm83/usr/bin/php` is **not present**. Use `/usr/local/lsws/lsphp83/bin/php` (8.3.31, pdo_mysql). `/usr/bin/php` lacks pdo_mysql.

---

## Stop gates (abort deploy if any fail)

| Gate | Check |
|------|-------|
| G-01 | Disk free ≥ 2 GB on `/home/pkjetp` |
| G-02 | Backup archive non-empty and checksum verified |
| G-03 | `.env` backed up separately |
| G-04 | `migrate:status` captured pre-deploy |
| G-05 | Public Next proxy plan confirmed (port 3000) |
| G-06 | Maintenance window communicated |
| G-07 | No active production incident |

---

## Step 1 — Preflight and disk space

```bash
df -h /home/pkjetp
if ! df -h /home/pkjetp | awk 'NR==2 {exit ($4+0 < 2)}'; then
  echo "STOP: insufficient disk space"
  exit 1
fi

$PHP -v | head -1
$NODE -v
test -x "$PHP" || { echo "STOP: PHP missing"; exit 1; }
test -x "$NODE" || { echo "STOP: Node missing"; exit 1; }
```

**Stop gate:** PHP 8.3.x and Node 20+ (documented v24.18.0).

---

## Step 2 — Backups

See `JETPK-RELEASE-01-ROLLBACK-PLAN.md` Step 1 for full backup script.

Minimum:

```bash
mkdir -p "$BACKUP"
cp -a "$APP/.env" "$BACKUP/.env.backup"
test -s "$BACKUP/.env.backup" || { echo "STOP: env backup failed"; exit 1; }

# Application tree (exclude vendor, logs, cache)
tar \
  --exclude='jetpk_app/vendor' \
  --exclude='jetpk_app/node_modules' \
  --exclude='jetpk_app/frontend/node_modules' \
  --exclude='jetpk_app/frontend/.next' \
  --exclude='jetpk_app/storage/logs' \
  --exclude='jetpk_app/storage/framework/cache' \
  --exclude='jetpk_app/storage/framework/sessions' \
  --exclude='jetpk_app/storage/framework/views' \
  -czf "${BACKUP}/jetpk_app.tar.gz" -C /home/pkjetp jetpk_app

tar -czf "${BACKUP}/public_html.tar.gz" -C /home/pkjetp public_html

# Database (operator credentials)
# mysqldump ... > "${BACKUP}/database.sql"

printf '%s\n' "$BACKUP" > "$APP/storage/app/jetpk-release-01-last-backup-path.txt"
sha256sum "${BACKUP}/jetpk_app.tar.gz" > "${BACKUP}/jetpk_app.tar.gz.sha256"
sha256sum -c "${BACKUP}/jetpk_app.tar.gz.sha256" || { echo "STOP: backup checksum failed"; exit 1; }
```

**Stop gate:** All backup artifacts exist and are non-empty.

---

## Step 3 — Maintenance mode decision

For full JP-FULLSTACK cutover: **enable maintenance**.

```bash
cd "$APP"
$PHP artisan down --retry=60 --secret="jetpk-release-01-${STAMP}"
```

Record secret for operator bypass URL. Skip only for verified zero-downtime partial deploys (not recommended for this release).

---

## Step 4 — Directory creation

```bash
mkdir -p "$APP/frontend"
mkdir -p "$APP/storage/logs"
mkdir -p "$APP/storage/framework/cache/data"
mkdir -p "$APP/storage/framework/sessions"
mkdir -p "$APP/storage/framework/views"
mkdir -p "$APP/bootstrap/cache"
mkdir -p "$PUBLIC/themes/frontend/jetpakistan/css"
mkdir -p "$PUBLIC/client-assets/jetpk-assets"
mkdir -p "$PUBLIC/js"
```

---

## Step 5 — Application upload

Upload per `JETPK-RELEASE-01-FILE-MANIFEST.md`:

- SFTP single-file or controlled sync
- **Never** blind-sync entire `public_html`
- **Never** upload `.env`, `vendor/`, `node_modules/`, `.next/`

**Stop gate:** Spot-check new JP-FULLSTACK paths exist:

```bash
test -f "$APP/frontend/features/public-content/utils/content-policy-core.mjs" || echo "WARN: check frontend upload"
test -f "$APP/composer.json"
```

---

## Step 6 — Composer install

```bash
cd "$APP"
composer install --no-dev --optimize-autoloader
if [ $? -ne 0 ]; then echo "STOP: composer install failed"; exit 1; fi
composer dump-autoload
```

---

## Step 7 — Frontend build and runtime preparation

### 7a — Root Vite (Blade/admin assets)

```bash
cd "$APP"
$NPM ci
$NPM run build
if [ $? -ne 0 ]; then echo "STOP: root vite build failed"; exit 1; fi
```

### 7b — Public Next.js frontend

```bash
cd "$APP/frontend"

# Production env — NO fixture flags
export NODE_ENV=production
unset NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES
unset OTA_ALLOW_SESSION_FIXTURE
unset NEXT_PUBLIC_SESSION_PREVIEW

# Set per server (examples — use actual production values)
# export NEXT_PUBLIC_APP_URL=https://www.jetpakistan.com
# export NEXT_PUBLIC_LARAVEL_URL=https://www.jetpakistan.com
# export LARAVEL_URL=http://127.0.0.1:80

$NPM ci
$NPM run typecheck
if [ $? -ne 0 ]; then echo "STOP: typecheck failed"; exit 1; fi
$NPM run lint
if [ $? -ne 0 ]; then echo "STOP: lint failed"; exit 1; fi
$NPM run build
if [ $? -ne 0 ]; then echo "STOP: next build failed"; exit 1; fi
```

### 7c — PM2 public frontend (if not already running)

```bash
cd "$APP/frontend"
$PM2 delete jetpk-public-frontend 2>/dev/null || true
$PM2 start "$NPM" \
  --name jetpk-public-frontend \
  --cwd "$APP/frontend" \
  --interpreter none \
  -- run start
$PM2 status jetpk-public-frontend
curl -sS -o /dev/null -w "public_next %{http_code}\n" http://127.0.0.1:3000/ || echo "WARN: Next not responding on 3000"
```

**Stop gate:** HTTP 200 from `127.0.0.1:3000/` (or documented proxy health).

### 7d — Dashboard (only if dashboard subtree changed)

```bash
cd "$APP/dashboard"
$NPM ci
$NPM run build
$PM2 restart jetpk-dashboard || true
curl -sS -o /dev/null -w "dashboard %{http_code}\n" http://127.0.0.1:3001/admin/dashboard
```

---

## Step 8 — Migration execution

```bash
cd "$APP"
$PHP artisan migrate:status > storage/logs/pre-migrate-${STAMP}.txt

$PHP artisan migrate --force
if [ $? -ne 0 ]; then echo "STOP: migration failed — initiate rollback"; exit 1; fi

$PHP artisan migrate:status > storage/logs/post-migrate-${STAMP}.txt
```

**Do not run** demo seeders (`OtaFinanceDemoSeeder`, `ResponsiveAgentPortalAuditSeeder`).

Optional reference-data only when explicitly authorized:

```bash
# $PHP artisan db:seed --class=AirportAirlineReferenceSeeder --force
```

---

## Step 9 — Public asset synchronization

Mirror built/static assets to `public_html`:

```bash
# Themes
rsync -a "$APP/public/themes/" "$PUBLIC/themes/" 2>/dev/null || \
  cp -a "$APP/public/themes/." "$PUBLIC/themes/"

# Client assets
rsync -a "$APP/public/client-assets/" "$PUBLIC/client-assets/" 2>/dev/null || \
  cp -a "$APP/public/client-assets/." "$PUBLIC/client-assets/"

# JS mirror
rsync -a "$APP/public/js/" "$PUBLIC/js/" 2>/dev/null || \
  cp -a "$APP/public/js/." "$PUBLIC/js/"

# Vite build
rsync -a "$APP/public/build/" "$PUBLIC/build/" 2>/dev/null || \
  cp -a "$APP/public/build/." "$PUBLIC/build/"

# Verify dual-root parity (example)
sha256sum "$APP/public/themes/frontend/jetpakistan/css/"*.css 2>/dev/null
sha256sum "$PUBLIC/themes/frontend/jetpakistan/css/"*.css 2>/dev/null
```

### storage:link (if missing)

```bash
cd "$APP"
if [ ! -L public/storage ]; then
  $PHP artisan storage:link
fi
```

---

## Step 10 — Cache rebuild

```bash
cd "$APP"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
if [ $? -ne 0 ]; then echo "STOP: cache rebuild failed"; exit 1; fi
```

---

## Step 11 — Queue restart

```bash
cd "$APP"
$PHP artisan queue:restart
```

Verify worker running (Supervisor or cron per `docs/production-cron-smtp-notifications.md`).

---

## Step 12 — Scheduler verification

```bash
crontab -l | grep schedule:run || echo "WARN: scheduler cron missing"
cd "$APP"
$PHP artisan schedule:list
```

---

## Step 13 — Service restart

```bash
$PM2 save
# Reload PHP-FPM if host panel provides safe reload — operator discretion
# Confirm reverse proxy routes to 127.0.0.1:3000 for public site
```

---

## Step 14 — Application health checks

```bash
cd "$APP"
$PHP artisan ota:route-page-health-audit --all
# Required: fail=0

$PHP artisan jetpk:cms-route-safety-audit
# Required: fail=0

$PHP artisan route:list --columns=method,uri,name | head -20
```

**Stop gate:** `ota:route-page-health-audit --all` → `fail=0`.

---

## Step 15 — Maintenance mode removal

```bash
cd "$APP"
$PHP artisan up
```

---

## Step 16 — Post-deployment smoke tests

Execute matrix from `JETPK-RELEASE-01-PRE-DEPLOYMENT-READINESS.md` §10.

```bash
# Examples (read-only)
curl -sS -o /dev/null -w "home %{http_code}\n" https://www.jetpakistan.com/
curl -sS -o /dev/null -w "login %{http_code}\n" https://www.jetpakistan.com/login
curl -sS -o /dev/null -w "about %{http_code}\n" https://www.jetpakistan.com/about-us

tail -n 100 "$APP/storage/logs/laravel.log"
# No new production.ERROR tied to smoke URLs
```

**Forbidden during smoke:** booking submit, PNR, ticketing, payment provider calls, supplier API, customer email send.

---

## Step 17 — Rollback trigger criteria

Initiate `JETPK-RELEASE-01-ROLLBACK-PLAN.md` if:

| Condition | Action |
|-----------|--------|
| `ota:route-page-health-audit` fail > 0 | Rollback |
| Public site 500 on `/`, `/login` | Rollback |
| Migration failure | Rollback app + DB restore if needed |
| Next :3000 not serving | Rollback or disable proxy to Blade fallback |
| New `production.ERROR` on smoke paths | Rollback |
| Visible Parwaaz/Master branding | Rollback |
| Fixture CMS content visible | Rollback + verify `NODE_ENV=production` |

---

## Post-deploy metadata

Update `clients/jetpk/deployment.json` (local repo copy on next commit):

- `last_deployed_at`: ISO timestamp
- `last_deployed_by`: operator id
- Note SHA `8d62db8c2a37038e52e3130d45b9ad284510bfee`

---

## Local pre-deploy gate (run before SFTP — not on production)

```bash
php artisan ota:route-page-health-audit --all
php artisan test tests/Feature/Jetpk/PublicBladeBrandingLeakageAuditTest.php
cd frontend && npm run typecheck && npm run lint && npm run build
```

Recorded JP-FULLSTACK-01G evidence: Laravel 156 passed; Playwright 206 passed; Node regressions pass.

---

## JETPK-RELEASE-01A / ACCESS-01 — Deployment plan updates

**Authoritative production host:** `185.215.166.176`
**Obsolete host (do not use):** `65.109.34.176`
**Canonical SSH:**

```powershell
ssh -F NUL -i "$env:USERPROFILE\.ssh\jetpk_contabo_2026_v2" -p 22 -o IdentitiesOnly=yes pkjetp@185.215.166.176
```

### Pre-flight gate additions (before Step 1)

| Gate | Status | Action |
|------|--------|--------|
| G-SSH | **PASS** | Agent-unlocked SSH to `pkjetp@185.215.166.176` verified 2026-08-06R2 |
| G-LIVE-VHOST | **PASS** | OTA live site is `https://jetpakistan.pk` |
| G-NEXT-LIVE | **NOT DEPLOYED** | `frontend/` absent; no PM2; no proxy — Step 7c is **net-new cutover** |
| G-PHP-CLI | **PASS** | Use `/usr/local/lsws/lsphp83/bin/php` (not `/opt/alt/php-fpm83`) |
| G-MIGRATIONS | **PASS** | 104/104 Ran — deploy must **not** re-run applied migrations |
| G-PM2 | **REQUIRES_SETUP** | PM2 not installed; nvm v24.18.0 absent — install during Step 7 |

### Step 0 — Read-only production capture (repeat before deploy)

Re-run ACCESS-01 inspection when shell access is restored on **`185.215.166.176`**:

1. Fingerprint loop (14 files) → compare to `JETPK-RELEASE-01-FILE-MANIFEST.md` §13
2. `php artisan migrate:status --no-ansi` → resolve B-03
3. `pm2 list` + port `ss -ltnp` for 3000/3001
4. Dual-root drift loop for `themes`, `client-assets`, `js`, `css`, `build`
5. Allowlisted `.env` keys only

**Abort deploy** if read-only SSH capture cannot complete on corrected host.

### Cutover note (B-02 resolved NOT_DEPLOYED)

Current production serves **Laravel Blade** on `jetpakistan.pk`. Deploying `8d62db8` requires:

1. Upload `frontend/` + server build
2. Start PM2 `jetpk-public-frontend` on `127.0.0.1:3000`
3. **Configure vhost reverse proxy** from `public_html` to Next (operator — config not in repo)
4. Verify Laravel `/laravel/*` proxy through Next rewrites

Rollback implication: revert vhost to Blade **and** stop PM2 public process (see rollback plan §11).
