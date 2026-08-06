# JETPK-RELEASE-01 — File Manifest

**Baseline SHA:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Manifest type:** **Full-release** (no reliable production delta baseline)
**Generated:** 2026-08-06

---

## 1. Baseline policy

Repository metadata records **multiple conflicting production SHAs** (`45865a0`, `a9bc782`, `72c6f3f`, incremental phase deploys). `clients/jetpk/deployment.json` has `last_deployed_at: null`.

**Do not deploy as a guessed delta.** Use this full-release manifest and operator-confirmed `migrate:status` on `/home/pkjetp/jetpk_app`.

---

## 2. Server path mapping

| Classification | Repository prefix | Production target |
|----------------|-------------------|-------------------|
| Laravel application | `app/`, `bootstrap/`, `artisan`, `composer.json`, `composer.lock` | `/home/pkjetp/jetpk_app/` |
| Configuration | `config/` | `/home/pkjetp/jetpk_app/config/` |
| Routes | `routes/` | `/home/pkjetp/jetpk_app/routes/` |
| Migrations (upload only) | `database/migrations/` | `/home/pkjetp/jetpk_app/database/migrations/` |
| Blade views | `resources/views/` | `/home/pkjetp/jetpk_app/resources/views/` |
| Lang / other resources | `resources/lang/`, etc. | `/home/pkjetp/jetpk_app/resources/` |
| Public assets (app) | `public/` (excl. generated) | `/home/pkjetp/jetpk_app/public/` |
| Public assets (live mirror) | `public/themes/`, `public/client-assets/`, `public/js/`, `public/css/` | `/home/pkjetp/public_html/` (same relative path) |
| Public JS mirror (explicit) | `public/js/*.js` | `/home/pkjetp/public_html/js/` |
| Frontend source | `frontend/` (excl. `.next`, `node_modules`) | `/home/pkjetp/jetpk_app/frontend/` |
| Dashboard source | `dashboard/` (excl. `.next`, `node_modules`) | `/home/pkjetp/jetpk_app/dashboard/` |
| Client metadata | `clients/jetpk/*.json`, `env.production.example` | Optional reference only |
| Vite build output | `public/build/` | **Rebuild on server** — do not upload local |
| Next build output | `frontend/.next/` | **Rebuild on server** |
| Dashboard build | `dashboard/.next/` | **Rebuild on server** if dashboard updated |
| Composer vendor | `vendor/` | **`composer install` on server** |
| Writable (never upload overwrite) | `storage/`, `bootstrap/cache/` | Preserve; clear caches only |

---

## 3. Classification totals

| Classification | Tracked count | Upload | Notes |
|----------------|--------------:|:------:|-------|
| Laravel application (`app/`) | 1,799 | Yes | All PHP under `app/` |
| Bootstrap | 3 | Yes | |
| Configuration | 43 | Yes | Never overwrite `.env` |
| Database migrations | 104 | Yes | Run pending only |
| Routes | 10 | Yes | |
| Blade / email views | 639+ | Yes | Admin, agent, customer fallbacks |
| Public assets (static) | 104 | Yes + mirror | See §5 |
| Frontend source | 781 | Yes | Excl. `.next`, `test-results` |
| Dashboard source | 468 | Yes | If behind DASH-13 baseline |
| Client metadata | 6 | Optional | No `env.production` secrets |
| Tests | 1,377 | **No** | CI/local only |
| Documentation | 659+ | Optional | Ops runbooks only |
| Playwright / UI_test artifacts | — | **No** | |
| `vendor/` | — | **No** | `composer install --no-dev` |
| `node_modules/` (root, frontend, dashboard) | — | **No** | `npm ci` on server |
| `storage/framework/views/` | — | **No** | Regenerated |
| `storage/logs/` | — | **No** | Preserve on server |
| `.git/`, `.env*` | — | **No** | |

**Approximate upload file count (source only):** ~3,900 paths excluding `vendor`, `node_modules`, generated builds, tests.

---

## 4. Laravel application directories (deploy to `jetpk_app`)

Upload entire trees (SFTP per-file or controlled rsync **excluding** vendor/storage):

```
app/
bootstrap/
config/
database/migrations/
database/seeders/          # upload; do not run demo seeders
routes/
resources/
public/                    # see §5 — partial mirror to public_html
frontend/                  # exclude .next, node_modules, test-results
dashboard/                 # exclude .next, node_modules
clients/jetpk/             # metadata only
artisan
composer.json
composer.lock
package.json               # root Vite
package-lock.json
vite.config.js
```

### Explicit exclusions from upload

```
.git/
.env
.env.*
vendor/
node_modules/
frontend/node_modules/
frontend/.next/
frontend/test-results/
frontend/playwright-report/
dashboard/node_modules/
dashboard/.next/
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
storage/app/audits/*
tests/
UI_test/
docs/                      # optional
.phpunit.result.cache
```

---

## 5. Public asset synchronization plan

### Dual-deploy required (identical SHA-256 on both targets)

| Repository path | jetpk_app | public_html |
|-----------------|:---------:|:-----------:|
| `public/themes/frontend/jetpakistan/**` | ✓ | ✓ |
| `public/client-assets/jetpk-assets/**` | ✓ | ✓ |
| `public/js/**` | ✓ | ✓ (`public_html/js/`) |
| `public/css/ota-public.css` | ✓ | ✓ (bump `?v=` in layout if changed) |
| `public/build/**` | ✓ | ✓ (after `npm run build`) |
| `public/favicon.ico`, `robots.txt` | ✓ | Per vhost config |

### Mirror-only (if present on live vhost)

- `public_html/themes/` — **do not delete** unknown extra files; overwrite only manifest paths
- `public_html/client-assets/` — preserve operator uploads
- `public_html/js/` — merge; do not delete unrelated legacy JS without inventory

### Do not overwrite without backup

- `public_html/.htaccess`
- `public_html/index.php` (if custom entry — verify before replace)
- User-generated `client-assets` uploads

### Generated — rebuild, do not upload from laptop

| Output | Command | Location |
|--------|---------|----------|
| Vite assets | `npm ci && npm run build` (repo root) | `public/build/` |
| Next public | `cd frontend && npm ci && npm run build` | `frontend/.next/` |
| Dashboard Next | `cd dashboard && npm ci && npm run build` | `dashboard/.next/` |

---

## 6. Frontend runtime disposition

| Item | Value |
|------|-------|
| Framework | Next.js 15 (`frontend/`) |
| Production start | `next start -p 3000` |
| Bind | `127.0.0.1:3000` (recommended) |
| Process manager | PM2 (proposed: `jetpk-public-frontend`) |
| Env at build | `NODE_ENV=production`; **no** fixture flags |
| Laravel upstream | `LARAVEL_URL` for `/laravel/*` rewrites |
| Public URL env | `NEXT_PUBLIC_APP_URL`, `NEXT_PUBLIC_LARAVEL_URL` |

**Reverse proxy:** vhost must route public B2C traffic to Next :3000 while Laravel serves `/laravel/*` or internal API. Exact nginx/apache config is **server-side** — not in repo.

---

## 7. Dashboard runtime disposition (existing)

| Item | Value |
|------|-------|
| Process | PM2 `jetpk-dashboard` |
| Port | `127.0.0.1:3001` |
| Env | `DASHBOARD_NEXT_SERVER_URL`, `DASHBOARD_NEXT_PROXY_ENABLED` |
| Doc | `docs/dashboard/DASHBOARD-PRODUCTION-DEPLOYMENT.md` |

Deploy dashboard only if server copy is behind `8d62db8` dashboard subtree.

---

## 8. Database migration files (all 104 — upload entire directory)

Operator runs **only pending** migrations. Full list in `database/migrations/`. Key filenames for JP-FULLSTACK / CMS / auth:

| File | Class |
|------|-------|
| `2026_06_17_100000_add_must_change_password_to_users_and_developer_users.php` | Force-password |
| `2026_06_05_150000_create_cms_pages_table.php` | CMS pages |
| `2026_07_06_120000_create_client_page_builder_tables.php` | Page builder |
| `2026_07_13_000001_backfill_jetpk_homepage_content_minimum_cards.php` | **Data backfill** |
| `2026_07_16_120000_create_client_page_setting_revisions_table.php` | CMS revisions |
| `2026_07_16_130000_create_client_page_setting_defaults_table.php` | CMS defaults |
| `2026_07_19_120000_create_client_pages_table.php` | Client pages registry |

---

## 9. Files not to delete on production

- `.env`
- `storage/app/private/**` (booking PDFs, documents)
- `storage/app/public/**` (uploaded media)
- `public_html/client-assets/**` (live branding uploads)
- `public_html/.htaccess`
- Database tables / data
- PM2 process definitions (backup before change)
- Cron entries (backup `crontab -l`)

---

## 10. Writable directories (create if missing; chmod on server)

```
storage/
storage/app/
storage/app/private/
storage/app/public/
storage/framework/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/
```

---

## 11. Verification commands (post-upload, server)

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
cd "$APP"

# Spot-check critical new paths exist
test -f frontend/features/public-content/utils/content-policy-core.mjs
test -f frontend/package.json
test -d app/Http/Controllers

# PHP lint sample (expand to touched controllers)
$PHP -l app/Http/Controllers/Frontend/FlightController.php

# Dependency install (not upload)
composer install --no-dev --optimize-autoloader

# Record migration state BEFORE migrate
$PHP artisan migrate:status > storage/logs/pre-release-migrate-status.txt
```

---

## 12. Manifest integrity

This manifest describes **classification and path rules** for baseline `8d62db8`. For SHA-256 verification of critical paths, operator should generate on server post-deploy:

```bash
find app config routes resources/views frontend -type f | head -100 | xargs sha256sum
sha256sum public/themes/frontend/jetpakistan/css/*.css
sha256sum /home/pkjetp/public_html/themes/frontend/jetpakistan/css/*.css
```

Per-phase incremental manifests (e.g. PHASE18 11-file deploy) remain valid for **partial** hotfixes but do not cover JP-FULLSTACK-01 full cutover.

---

## 13. JETPK-RELEASE-01A — Fingerprint reference (local repository)

**Purpose:** Operator compares these SHA-256 values against production files when shell access is available. **No remote hashes captured** in 01A or ACCESS-01 (shell blocked).

### Baseline `8d62db8c2a37038e52e3130d45b9ad284510bfee`

| File | SHA-256 |
|------|---------|
| `composer.json` | `5da10e335e7074ccae2be803c853188db2f5f78dd5588e5e716c3e54b3c0cc68` |
| `composer.lock` | `b0bb7df2c865d4e4b8a9af3d836ca776d466b60d2a623e30ec17507eecac015c` |
| `package.json` | `915b4ea9d6ad54cbfdfab29d18b5d3815ce69feeeb2b6cf8f7d5ce627cad86d6` |
| `package-lock.json` | `21a8498cee07f0609ddd0153d8c60b6f4331cce1d89e122e346a51553a23c54f` |
| `frontend/package.json` | `4399606abe56658446cbbb34f7b8c11c3efae2c8412ea240feb7b41c28ff5ff1` |
| `frontend/package-lock.json` | `7ad9a6c9c989196b83f1a7f9ba60c805958576726d09913982d334615cbc2b9b` |
| `routes/web.php` | `a670f20ca81ef47c4fcc5fde3a220b9b2ccaf3e6a3644c0f7aa5d90c6d0e0b27` |
| `routes/agent.php` | `21ba57e7f06efe54dfb2e6159e56e420b63a4b6cd87edbb3d36ec3e339f02501` |
| `frontend/features/public-content/utils/content-policy.ts` | `5d85038a07b4b5d78ca3516f7f23ef426631317b53bf0cc1f0ffbd991af45444` |
| `frontend/features/public-content/utils/content-policy-core.mjs` | `dc5211287d35dc9e4c7692625bc58f220f37b18397598a55255fdb044692d06f` |
| `app/Http/Controllers/Agent/SavedTravelerController.php` | `2721b2641b64eb1a4ccf63f5a3a28076cff591793d5ac2289ef721d5d684b633` |
| `app/Http/Controllers/Agent/FinanceStatementController.php` | `8b977d690f3b9ebe625fd5aaea81cf22259ac0ddcfbd3d92b170fec4581850e7` |
| `app/Http/Controllers/Agent/AccountingLedgerController.php` | `fac9cb0f6c7d47d01be114da68234f442ad7236223ee309c7a0cbc55c0d47799` |
| `app/Http/Controllers/Frontend/GuestBookingLookupController.php` | `ff20060fcb60fafcdbed9ea9cc31bd6d9a79c3359b813c45d45a02d13bc358ab` |

### Older Blade candidates (`45865a0` / `a9bc782`)

| File | SHA-256 | Notes |
|------|---------|-------|
| `routes/web.php` | `5e658ddf7c218d2d221b128fe14101ee82c1f2705accbb2adabab1d0e4417b07` | Differs from `8d62db8` |
| `routes/agent.php` | `5e1a6a42f40f44bd2cb9b6cb8fbda933448035e3fb38085123435759d17e0f54` | Differs from `8d62db8` |
| `SavedTravelerController.php` | `900ea5ba9d9be1681ded9486ae162195e319cb2a663ae7f685549dc5eddbbe28` | Differs from `8d62db8` |
| `frontend/package.json` | — | **Absent** at older SHAs |
| `content-policy.ts` | — | **Absent** at older SHAs |

### 01A / ACCESS-01-R2 manifest strategy update

| Finding | Impact |
|---------|--------|
| Authoritative host | `185.215.166.176` |
| Production generation | **`72c6f3f3` era** (approximate) — not `8d62db8` |
| `frontend/` on server | **Absent** — full upload required |
| `composer.json`/`lock` | Match `8d62db8` |
| `routes/web.php` | Matches `72c6f3f3`; differs from `8d62db8` |
| Dual-root drift | `themes`, `client-assets`, `js` — mirror during deploy |
| Migrations | **All 104 Ran** — upload only; do not migrate |
| Deployment markers | **Missing** — create during deploy |

#### Remote SHA-256 fingerprints (captured 2026-08-06R2)

| File | Remote SHA-256 | vs `8d62db8` |
|------|----------------|--------------|
| `composer.json` | `5da10e335e7074ccae2be803c853188db2f5f78dd5588e5e716c3e54b3c0cc68` | MATCH |
| `composer.lock` | `b0bb7df2c865d4e4b8a9af3d836ca776d466b60d2a623e30ec17507eecac015c` | MATCH |
| `package.json` | `3c18092614d3938bd662b3b0b4ff50340e769fc5ea0a0a0158c130b33b2ca068` | DIFFER |
| `package-lock.json` | — | MISSING |
| `frontend/package.json` | — | MISSING (dir absent) |
| `routes/web.php` | `907b621d7ca7a1d05841be13083fbd03cd686e3e8d42bb264731313469fb434a` | DIFFER (`72c6f3f3`) |
| `routes/agent.php` | `beff7353b2b039b0e3632cfa9655d794b9fe73df185276c6f475bd6c4e4e009e` | DIFFER (`72c6f3f3`) |
| `content-policy.ts` | — | MISSING |
| `SavedTravelerController.php` | `e5d96046a4b71f8eee93a8ed479b75a18dba7e7ca874b3d289475baa939e5be5` | DIFFER (`72c6f3f3`) |
| `FinanceStatementController.php` | `f7663793ed02220ac8a68bb69075f1783b68c438c64a06b23119f06e8e86968e` | DIFFER |
| `AccountingLedgerController.php` | `c97b21cfe1ca2f56944d721200a70ea342b17ddcc6b0c46057e066c382dc4ce7` | DIFFER |
| `GuestBookingLookupController.php` | `9b2c25a855a3e77d018996f0154312626814c3699618d454672e7c3aca7e9d62` | DIFFER |
