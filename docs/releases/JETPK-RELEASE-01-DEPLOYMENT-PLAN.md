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
# PUBLIC_NEXT_PORT: operator-approved localhost port — NOT 3000 (nghttpx occupied). See §01C.
PUBLIC_NEXT_PORT=<OPERATOR_APPROVED_PORT>
DASHBOARD_PORT=3001
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/usr/local/bin:$PATH"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP=/home/pkjetp/backups/jetpk-release-01-${STAMP}
# PM2: not installed pre-deploy — discover after Step 7e install via: command -v pm2
```

> **01C architecture:** Use `/usr/local/lsws/lsphp83/bin/php` only. `/opt/alt/php-fpm83` is **not present**. Port **3000** is occupied by **nghttpx** — do **not** bind public Next to 3000. `PUBLIC_NEXT_PORT` requires operator approval before deploy (D-01).

---

## Stop gates (abort deploy if any fail)

| Gate | Check |
|------|-------|
| G-01 | Disk free ≥ 2 GB on `/home/pkjetp` |
| G-02 | Backup archive non-empty and checksum verified |
| G-03 | `.env` backed up separately |
| G-04 | `migrate:status` captured pre-deploy; pending set calculated |
| G-05 | **`PUBLIC_NEXT_PORT` approved**; port free on localhost; LiteSpeed/CyberPanel proxy plan confirmed for that port |
| G-06 | Maintenance window communicated |
| G-07 | No active production incident |
| G-08 | PM2 installed and `command -v pm2` succeeds (Step 7e) |
| G-09 | Localhost health on `$PUBLIC_NEXT_PORT` passes **before** public proxy activation |

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

**Stop gate:** PHP 8.3.x (lsphp83) and Node 20+ (`/usr/local/bin/node` — v22.23.1 observed pre-deploy).

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

## Step 7 — Frontend build, PM2 install, and runtime preparation

### 7a — Root Vite (Blade/admin assets)

```bash
cd "$APP"
$NPM ci
$NPM run build
if [ $? -ne 0 ]; then echo "STOP: root vite build failed"; exit 1; fi
```

### 7b — Public Next.js frontend (build only)

```bash
cd "$APP/frontend"

export NODE_ENV=production
unset NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES
unset OTA_ALLOW_SESSION_FIXTURE
unset NEXT_PUBLIC_SESSION_PREVIEW

export NEXT_PUBLIC_APP_URL=https://jetpakistan.pk
export NEXT_PUBLIC_LARAVEL_URL=https://jetpakistan.pk
export LARAVEL_URL=http://127.0.0.1

$NPM ci
$NPM run typecheck
if [ $? -ne 0 ]; then echo "STOP: typecheck failed"; exit 1; fi
$NPM run lint
if [ $? -ne 0 ]; then echo "STOP: lint failed"; exit 1; fi
$NPM run build
if [ $? -ne 0 ]; then echo "STOP: next build failed"; exit 1; fi
```

### 7c — Operator port approval (mandatory before PM2 start)

```bash
# Abort unless PUBLIC_NEXT_PORT is set and approved (not 3000)
if [ -z "$PUBLIC_NEXT_PORT" ] || [ "$PUBLIC_NEXT_PORT" = "3000" ]; then
  echo "STOP: PUBLIC_NEXT_PORT not approved or conflicts with nghttpx on 3000"
  exit 1
fi
ss -ltnp 2>/dev/null | grep -E ":${PUBLIC_NEXT_PORT}\b" && echo "STOP: port in use" && exit 1
```

**Candidate ports** (not listening pre-deploy; not referenced in account config): `3010`, `3100`, `3002`, `3003`. **Reserved:** `3001` (dashboard). **Forbidden:** `3000` (nghttpx).

### 7d — PM2 installation (separate cutover step — not pre-installed)

```bash
# Pre-deploy state: PM2 absent; Node /usr/local/bin/node v22.23.1; npm /usr/local/bin/npm
NPM_PREFIX=$($NPM config get prefix 2>/dev/null)
echo "NPM_PREFIX=$NPM_PREFIX"
# Read-only writability check before install (do not create files):
test -w "$NPM_PREFIX" 2>/dev/null && echo "PREFIX_WRITABLE=yes" || echo "PREFIX_WRITABLE=no — operator must install PM2 to user-writable prefix"

$NPM install -g pm2
if [ $? -ne 0 ]; then echo "STOP: PM2 install failed"; exit 1; fi
PM2=$(command -v pm2)
if [ -z "$PM2" ]; then echo "STOP: pm2 not in PATH"; exit 1; fi
$PM2 -v
```

### 7e — PM2 public frontend (operator-approved port)

```bash
cd "$APP/frontend"
$PM2 delete jetpk-public-frontend 2>/dev/null || true
$PM2 start "$NPM" \
  --name jetpk-public-frontend \
  --cwd "$APP/frontend" \
  --interpreter none \
  -- node node_modules/next/dist/bin/next start -p "$PUBLIC_NEXT_PORT"
$PM2 status jetpk-public-frontend
curl -sS -o /dev/null -w "public_next %{http_code}\n" "http://127.0.0.1:${PUBLIC_NEXT_PORT}/" || echo "STOP: Next not responding"
```

**Stop gate:** HTTP 200 from `http://127.0.0.1:${PUBLIC_NEXT_PORT}/` **before** enabling public LiteSpeed proxy. Do **not** use `npm run start` (hardcodes port 3000 in `package.json`).

### 7f — Dashboard Next (in scope for full JP-FULLSTACK cutover)

Dashboard deployment is **required** in this release — production has no dashboard Next process.

```bash
cd "$APP/dashboard"
$NPM ci
$NPM run build
if [ $? -ne 0 ]; then echo "STOP: dashboard build failed"; exit 1; fi
$PM2 delete jetpk-dashboard 2>/dev/null || true
$PM2 start "$NPM" \
  --name jetpk-dashboard \
  --cwd "$APP/dashboard" \
  --interpreter none \
  -- node node_modules/next/dist/bin/next start -p "$DASHBOARD_PORT"
$PM2 status jetpk-dashboard
curl -sS -o /dev/null -w "dashboard %{http_code}\n" "http://127.0.0.1:${DASHBOARD_PORT}/admin/dashboard"
```

Do **not** use `pm2 restart jetpk-dashboard` — the process does not exist pre-deploy.

---

## Step 8 — Migration execution (conditional)

```bash
cd "$APP"
$PHP artisan migrate:status --no-ansi > storage/logs/pre-migrate-${STAMP}.txt

PENDING_COUNT=$($PHP artisan migrate:status --no-ansi | grep -c 'Pending' || true)
echo "PENDING_MIGRATIONS=$PENDING_COUNT"

if [ "$PENDING_COUNT" -eq 0 ]; then
  echo "MIGRATION_EXECUTION=SKIPPED_NO_PENDING"
else
  echo "STOP: unexpected pending migrations — analyze list and require separate authorization"
  $PHP artisan migrate:status --no-ansi | grep Pending
  exit 1
fi

$PHP artisan migrate:status --no-ansi > storage/logs/post-migrate-${STAMP}.txt
```

Pre-deploy state (ACCESS-01-R2): **104 completed, 0 pending**. Re-check against final release SHA before deploy. Do **not** run `migrate --force` when pending count is zero.

**Do not run** demo seeders (`OtaFinanceDemoSeeder`, `ResponsiveAgentPortalAuditSeeder`).

Optional reference-data only when explicitly authorized:

```bash
# $PHP artisan db:seed --class=AirportAirlineReferenceSeeder --force
```

---

## Step 9 — Public asset synchronization (controlled — no blind sync)

**Pre-deploy drift (ACCESS-01-R2):** `themes` CONTENT_DRIFT; `client-assets` CONTENT_DRIFT; `js` CONTENT_DRIFT; `css` MATCH; `build` MATCH.

Policy:

1. `public_html` backed up in Step 2 before any copy.
2. Produce itemized dry-run per directory (diff file lists + SHA-256).
3. Copy **only** approved source-of-truth paths from `jetpk_app/public`.
4. **Do not** use `rsync --delete` or blind full-tree sync.
5. Preserve unknown live-only files until operator review.
6. Verify SHA-256 parity per directory after copy.
7. Rollback from `public_html` backup on failure.

```bash
# Example: themes (repeat pattern per directory — operator reviews dry-run first)
# diff -u <(cd "$APP/public/themes" && find . -type f | sort) \
#         <(cd "$PUBLIC/themes" && find . -type f | sort)
# cp -a approved-files-only ...
```

### storage:link reconciliation (separate stop gate — do not run blindly)

**Pre-deploy state:**

- `/home/pkjetp/jetpk_app/public/storage` — **directory** (not symlink)
- `/home/pkjetp/public_html/storage` — **symlink** → `jetpk_app/storage/app/public` (live authority)

Policy:

1. Inspect `jetpk_app/public/storage` contents before any change.
2. Back up the directory if replacement is considered.
3. **Do not** auto-run `artisan storage:link` or delete the directory.
4. **Preserve** `public_html/storage` symlink — it serves live media.
5. Require explicit operator reconciliation decision (D-05) during deploy.

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

## Step 11 — Queue policy (sync driver)

Pre-deploy: `QUEUE_CONNECTION=sync` — **no persistent queue worker**.

```bash
cd "$APP"
QUEUE_DRIVER=$(grep -E '^QUEUE_CONNECTION=' .env | cut -d= -f2)
if [ "$QUEUE_DRIVER" = "sync" ]; then
  echo "QUEUE_RESTART=NOT_APPLICABLE_SYNC_DRIVER"
else
  echo "STOP: queue driver changed — require separate infrastructure authorization"
  exit 1
fi
```

Do **not** run `queue:restart` under the current sync driver. Queue-worker provisioning requires a separately authorized infrastructure phase if the driver changes to `database`.

---

## Step 12 — Scheduler verification

```bash
crontab -l | grep schedule:run || echo "WARN: scheduler cron missing"
cd "$APP"
$PHP artisan schedule:list
```

---

## Step 13 — Service persistence

```bash
PM2=$(command -v pm2)
$PM2 save
# Configure LiteSpeed/CyberPanel reverse proxy to 127.0.0.1:$PUBLIC_NEXT_PORT (operator — D-02)
# Do not stop or reconfigure nghttpx on port 3000
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
curl -sS -o /dev/null -w "home %{http_code}\n" https://jetpakistan.pk/
curl -sS -o /dev/null -w "login %{http_code}\n" https://jetpakistan.pk/login
curl -sS -o /dev/null -w "about %{http_code}\n" https://jetpakistan.pk/about-us
```

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
| Next on approved port not serving | Rollback or disable proxy to Blade fallback |
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
| G-PM2 | **REQUIRES_SETUP** | PM2 not installed — `npm install -g pm2` during Step 7d |

### Step 0 — Read-only production capture (repeat before deploy)

Re-run ACCESS-01 inspection when shell access is restored on **`185.215.166.176`**:

1. Fingerprint loop (14 files) → compare to `JETPK-RELEASE-01-FILE-MANIFEST.md` §13
2. `php artisan migrate:status --no-ansi` → resolve B-03
3. `pm2 list` + port `ss -ltnp` for 3000/3001
4. Dual-root drift loop for `themes`, `client-assets`, `js`, `css`, `build`
5. Allowlisted `.env` keys only

**Abort deploy** if read-only SSH capture cannot complete on corrected host.

### Cutover architecture (01C — ARCHITECTURE_DECISION_REQUIRED)

**Authoritative OTA domain:** `https://jetpakistan.pk` — **not** `www.jetpakistan.com` (different property).

**Port 3000 conflict:** `127.0.0.1:3000` is occupied by **nghttpx** (returns HTTP 404). Do **not** bind public Next to 3000. Do **not** stop or reconfigure nghttpx.

**Public Next cutover status:** `ARCHITECTURE_DECISION_REQUIRED` until:

1. Operator approves `PUBLIC_NEXT_PORT` (candidates: `3010`, `3100`, `3002`, `3003` — recheck immediately before deploy).
2. LiteSpeed/CyberPanel reverse-proxy method is approved (D-02).
3. Localhost health passes on approved port before public proxy activation.
4. Rollback plan restores Laravel Blade authority **without** stopping nghttpx.

Deploying `8d62db8` requires:

1. Upload `frontend/` + server build on **`$PUBLIC_NEXT_PORT`** (not 3000).
2. Install PM2 (non-root via npm global — Step 7d).
3. Start PM2 `jetpk-public-frontend` and `jetpk-dashboard` (cold start — no restart).
4. Configure vhost reverse proxy from `public_html` to `127.0.0.1:$PUBLIC_NEXT_PORT`.
5. Verify Laravel `/laravel/*` proxy through Next rewrites (`LARAVEL_URL=http://127.0.0.1`).

Rollback: revert vhost to Blade, stop/remove PM2 public process, **leave nghttpx on 3000 unchanged**.
