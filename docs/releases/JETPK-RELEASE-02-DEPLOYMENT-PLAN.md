# JETPK-RELEASE-02 — Deployment Plan

**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Status:** LOCKED — executable at explicit deployment authorization

**PHP CLI:** `/usr/local/lsws/lsphp83/bin/php` (8.3.31)  
**Public Next port:** `3010`  
**Dashboard Next port:** `3001`  
**Forbidden port:** `3000` (nghttpx)

---

## Pre-flight gates (deployment window)

1. Confirm deployed tree matches engineering SHA `b95efd4` (or newer authorized SHA).
2. Recheck ports **3010** and **3001** free (`ss -ltn`).
3. Confirm port **3000** still nghttpx — do not bind Next there.
4. DB backup completed and gzip verified.
5. Filesystem + `.htaccess` + CyberPanel vhost backup completed.
6. `php artisan migrate:status` — if pending = 0 → **SKIP migrations**.
7. Fixture flags remain UNSET/FALSE on server and in Next env.
8. PM2 installed to `$HOME/.npm-global/bin`.

---

## Ordered sequence

### Phase A — Backups (mandatory)

```bash
TS=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p /home/pkjetp/backups
# DB — credentials from .env at deploy (never log password)
mysqldump -h127.0.0.1 -u"$DB_USER" -p"$DB_PASS" "$DB_DATABASE" | gzip > /home/pkjetp/backups/jetpk-db-$TS.sql.gz
gzip -t /home/pkjetp/backups/jetpk-db-$TS.sql.gz

tar czf /home/pkjetp/backups/jetpk_app-$TS.tar.gz -C /home/pkjetp jetpk_app
tar czf /home/pkjetp/backups/public_html-$TS.tar.gz -C /home/pkjetp public_html
# Export CyberPanel vhost config for jetpakistan.pk before proxy change
```

### Phase B — Laravel + public assets

```bash
PHP=/usr/local/lsws/lsphp83/bin/php
cd /home/pkjetp/jetpk_app
composer install --no-dev --optimize-autoloader
npm ci && npm run build   # root Vite if manifest changed
# SFTP sync per FILE-MANIFEST
# Mirror public/themes, public/css, public/js, public/build, client-assets → public_html
# MIGRATION ACTION: SKIP unless migrate:status shows new pending
```

### Phase C — PM2 install (user-local)

```bash
export NPM_CONFIG_PREFIX=$HOME/.npm-global
export PATH=$HOME/.npm-global/bin:$PATH
mkdir -p $HOME/.npm-global
npm install -g pm2
command -v pm2   # must succeed
```

### Phase D — Public Next (localhost first)

```bash
cd /home/pkjetp/jetpk_app/frontend
npm ci
# Production env — NO OTA_ALLOW_CONTENT_FIXTURE, NO OTA_ALLOW_SESSION_FIXTURE
# NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES unset/false
# LARAVEL_URL=http://127.0.0.1
npm run build
pm2 delete jetpk-public-frontend 2>/dev/null || true
pm2 start node --name jetpk-public-frontend -- node_modules/next/dist/bin/next start -p 3010
curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3010/   # expect 200
```

**Never use:** `start-smoke.mjs`, `playwright-server.mjs`, `npm run start:smoke`, `npm run test:smoke`.

### Phase E — Dashboard Next (localhost first)

```bash
cd /home/pkjetp/jetpk_app/dashboard
npm ci && npm run build
pm2 delete jetpk-dashboard 2>/dev/null || true
pm2 start node --name jetpk-dashboard -- node_modules/next/dist/bin/next start -p 3001
curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/admin/dashboard   # expect 200
pm2 save
```

### Phase F — Proxy cutover (CyberPanel operator)

**D-02 locked method:**

1. CyberPanel → Websites → `jetpakistan.pk` → **Backup current vhost config**.
2. Create LiteSpeed **External Application** `jetpk-public-next` → `127.0.0.1:3010`.
3. Add **Context** `/` → proxy handler → `jetpk-public-next` (preserve static file handling where applicable).
4. Route admin/staff dashboard traffic to External App `jetpk-dashboard` → `127.0.0.1:3001` (exact path map per panel).
5. Ensure Laravel routes not absorbed by Next remain reachable via `/laravel/*` through Next rewrite.
6. **Do not** stop or reconfigure nghttpx on port 3000.

### Phase G — Post-cutover verification

| Check | Expected |
|-------|----------|
| `https://jetpakistan.pk/` | 200, no client exception |
| `/login`, `/about-us`, `/faq` | 200 |
| `/laravel/*` API/session | Functional |
| `/admin/dashboard`, `/staff/dashboard` | Dashboard Next shell |
| `public_html/storage` symlink | Intact |
| Cron `schedule:run` | Running (no queue worker needed) |

---

## Rollback triggers

See [JETPK-RELEASE-02-ROLLBACK-PLAN.md](./JETPK-RELEASE-02-ROLLBACK-PLAN.md).
