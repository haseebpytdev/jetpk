# JETPK-RELEASE-02 — File Manifest

**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Strategy:** Full-release manifest (no delta guess). Asset drift vs live server **pending SSH recheck**.

## Legend

| Action | Meaning |
|--------|---------|
| COPY | Upload/sync from release tree |
| BUILD_ON_SERVER | Upload source; generate on server |
| PRESERVE | Do not overwrite live path |
| CONDITIONAL | Deploy-window decision |
| DO_NOT_COPY | Never upload from workstation |

---

## Laravel application (`jetpk_app`)

| Path | Action | Notes |
|------|--------|-------|
| `app/` | COPY | Full application code |
| `bootstrap/` | COPY | |
| `config/` | COPY | Never copy `.env` |
| `database/migrations/` | COPY | Run only if `migrate:status` shows pending |
| `database/seeders/` | DO_NOT_COPY | Production seeders forbidden except explicit ops authorization |
| `public/` (excl. generated) | COPY + mirror | Mirror theme/js/css to `public_html` per dual-root rule |
| `resources/views/` | COPY | Blade fallbacks retained |
| `routes/` | COPY | |
| `artisan` | COPY | |
| `composer.json` / `composer.lock` | COPY | Run `composer install --no-dev` on server |
| `vendor/` | BUILD_ON_SERVER | Never upload local vendor |
| `.env` | PRESERVE | Live production secrets |
| `storage/` | PRESERVE | Except framework cache clears if required |
| `storage/app/private/` | PRESERVE | Booking documents |
| `storage/logs/` | PRESERVE | |
| `node_modules/` | BUILD_ON_SERVER | Root Vite if used |
| `public/build/` | BUILD_ON_SERVER | `npm run build` at Laravel root |
| `tests/` | DO_NOT_COPY | |
| `.git/` | DO_NOT_COPY | SFTP deploy — no git pull on production |

---

## Public webroot (`public_html`)

| Path | Action | Notes |
|------|--------|-------|
| `.htaccess` | CONDITIONAL | Backup before replace; proxy rules added at cutover |
| `index.php` | PRESERVE/COPY | Laravel front controller — verify live mapping |
| `storage` symlink | PRESERVE | Must remain → `jetpk_app/storage/app/public` |
| `themes/frontend/jetpakistan/**` | COPY | Mirror from `jetpk_app/public/themes/...` |
| `client-assets/jetpk-assets/**` | COPY | Mirror uploads |
| `js/**` | COPY | Mirror from `jetpk_app/public/js/` |
| `css/**` | COPY | Bump `ota-public.css` cache-bust in Blade when changed |
| `build/**` | BUILD_ON_SERVER | Vite manifest |

---

## Public Next (`frontend/`)

| Path | Action | Notes |
|------|--------|-------|
| `frontend/` source | COPY | Excl. `node_modules`, `.next` |
| `frontend/node_modules/` | BUILD_ON_SERVER | `npm ci` |
| `frontend/.next/` | BUILD_ON_SERVER | `npm run build` with `NODE_ENV=production` |
| Production start | — | `next start -p $PUBLIC_NEXT_PORT` — **not** `start-smoke.mjs` |

**Required env (no fixture flags):** `NEXT_PUBLIC_APP_URL`, `NEXT_PUBLIC_LARAVEL_URL`, `LARAVEL_URL=http://127.0.0.1`, `NODE_ENV=production`

---

## Dashboard Next (`dashboard/`)

| Path | Action | Notes |
|------|--------|-------|
| `dashboard/` source | COPY | Excl. `node_modules`, `.next` |
| `dashboard/node_modules/` | BUILD_ON_SERVER | `npm ci` |
| `dashboard/.next/` | BUILD_ON_SERVER | `npm run build` |
| Production start | — | `next start -p $DASHBOARD_PORT` (candidate 3001) |

---

## Client metadata

| Path | Action |
|------|--------|
| `clients/jetpk/deployment.json` | CONDITIONAL (ops metadata only) |
| `clients/jetpk/env.production.example` | DO_NOT_COPY (reference only) |

---

## Protected — never delete or blind-sync

- `/home/pkjetp/jetpk_app/.env`
- `/home/pkjetp/jetpk_app/storage/app/private/`
- `/home/pkjetp/public_html/client-assets/` user uploads
- Production database
- `public_html/storage` symlink (unless explicitly reconciled with backup)

---

## Drift status

**INCOMPLETE** — targeted hash comparison vs live `public_html` and `jetpk_app/public` requires SSH (D-06).
