# JETPK-RELEASE-02 — Deployment Plan

**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Status:** DRAFT — **not executable until Loop B unblocks and operator authorizes deployment**

**PHP CLI (verified historical — recheck at window):** `/usr/local/lsws/lsphp83/bin/php`  
**Never use:** `/opt/alt/php-fpm83/usr/bin/php`

---

## Pre-flight gates (deployment window)

1. Confirm `main` / deployed tree = `b95efd4` (or newer engineering-approved SHA).
2. Recheck `PUBLIC_NEXT_PORT` (candidate **3010**) and `DASHBOARD_PORT` (candidate **3001**) with `ss -ltnp`.
3. Confirm port **3000** still nghttpx — do not bind Next there.
4. DB backup completed and verified (see Rollback plan).
5. Filesystem + `.htaccess` backup completed.
6. `php artisan migrate:status` — if pending = 0 → **MIGRATION ACTION: SKIP** (recheck only).
7. Fixture flags: `OTA_ALLOW_CONTENT_FIXTURE`, `OTA_ALLOW_SESSION_FIXTURE` = UNSET/FALSE.
8. PM2 installed to user prefix; `command -v pm2` succeeds.

---

## Ordered deployment sequence

### Phase A — Backup (mandatory)

| Step | Action |
|------|--------|
| A1 | `mysqldump` full DB → `/home/pkjetp/backups/jetpk-db-<TIMESTAMP>.sql.gz` |
| A2 | `tar czf` `jetpk_app` (excl. `vendor`, `node_modules`, `storage/logs`) |
| A3 | `tar czf` `public_html` critical paths + `.htaccess` |
| A4 | Record backup sizes and verify gzip integrity |

### Phase B — Laravel + public assets

| Step | Action |
|------|--------|
| B1 | SFTP sync Laravel code per manifest (changed files or controlled full sync) |
| B2 | `composer install --no-dev --optimize-autoloader` in `jetpk_app` |
| B3 | `npm ci && npm run build` at Laravel root (Vite) if manifest changed |
| B4 | Mirror `public/themes`, `public/js`, `public/css`, `client-assets` → `public_html` |
| B5 | **Conditional migrations:** only if `migrate:status` shows pending safe migrations |
| B6 | `php artisan config:cache` / `route:cache` / `view:cache` **only if** standard for this deploy |

### Phase C — Public Next (localhost first)

| Step | Action |
|------|--------|
| C1 | SFTP `frontend/` source (no `node_modules`, `.next`) |
| C2 | `cd frontend && npm ci && npm run build` with production env (no fixture flags) |
| C3 | PM2 start: `next start -p $PUBLIC_NEXT_PORT` (not `start-smoke.mjs`) |
| C4 | `curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:$PUBLIC_NEXT_PORT/` → expect 200 |
| C5 | Verify `/laravel/*` proxy from Next to Laravel |

### Phase D — Dashboard Next (localhost first)

| Step | Action |
|------|--------|
| D1 | SFTP `dashboard/` source |
| D2 | `cd dashboard && npm ci && npm run build` |
| D3 | PM2 start: `next start -p $DASHBOARD_PORT` |
| D4 | `curl` admin/staff dashboard routes on localhost |

### Phase E — Proxy cutover (operator / panel)

| Step | Action |
|------|--------|
| E1 | Configure LiteSpeed reverse proxy: public vhost → `127.0.0.1:$PUBLIC_NEXT_PORT` |
| E2 | Configure admin/staff paths → dashboard Next (exact path map per D-02 resolution) |
| E3 | Preserve Laravel API/session routes not handled by Next |
| E4 | **Do not** stop nghttpx on 3000 |

### Phase F — Post-cutover verification

| Step | Action |
|------|--------|
| F1 | Public routes: `/`, `/login`, `/about-us`, `/faq` — HTTP 200, no client exception |
| F2 | Protected Laravel/API routes respond (no 500) |
| F3 | Admin `/admin/dashboard`, Staff `/staff/dashboard` smoke |
| F4 | `public_html/storage` symlink intact |
| F5 | Scheduler/cron: `schedule:run` evidence in logs |
| F6 | Queue: if `sync`, **no** `queue:restart` required |
| F7 | Manual browser QA handoff (desktop/tablet/mobile) |

**Not required:** live booking, payment, supplier search, ticketing.

---

## Rollback triggers

- Public Next localhost health fails before proxy activation → abort cutover.
- Proxy enabled but homepage 500 / client exception → disable proxy, restore `.htaccess` backup.
- Migration failure → stop, restore DB from A1 if data affected.
- Dashboard unreachable → stop dashboard PM2, retain Blade admin fallback if applicable.

See [JETPK-RELEASE-02-ROLLBACK-PLAN.md](./JETPK-RELEASE-02-ROLLBACK-PLAN.md).
