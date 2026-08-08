# JETPK-RELEASE-02 — Production Capture

**Phase:** JETPK-RELEASE-02  
**Branch:** `phase/jetpk-release-02-final-predeploy-readiness`  
**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Capture status:** **COMPLETE** (read-only SSH)  
**Capture timestamp (UTC):** 2026-08-08T03:56Z  
**Host:** `185.215.166.176` (`vmi3400777`)  
**User:** `pkjetp`

---

## 1. Host identity and capacity

| Item | Value |
|------|-------|
| Hostname | `vmi3400777` |
| Kernel | Linux 6.8.0-136-generic x86_64 Ubuntu |
| Disk `/` | 96G total, 17G used, **80G avail** (18%) |
| Inodes | 3% used |
| RAM | 11960 MB total, **10981 MB available** |
| Swap | 2047 MB (unused) |

**Assessment:** Sufficient disk/RAM for staging, backups, and on-server Next builds.

---

## 2. Runtime (verified)

| Tool | Path / version |
|------|----------------|
| PHP CLI (authoritative) | `/usr/local/lsws/lsphp83/bin/php` — **PHP 8.3.31** |
| PHP CLI (disproven) | `/opt/alt/php-fpm83/usr/bin/php` — **not present** |
| Laravel | **13.7.0** |
| Node | `/usr/local/bin/node` — **v22.23.1** |
| npm | `/usr/local/bin/npm` — **10.9.8** |
| npm prefix | `/opt/node-v22.23.1-linux-x64` (**not user-writable**) |
| Composer | `/usr/bin/composer` — **2.10.1** |
| PM2 | **NOT INSTALLED** (`command -v pm2` empty) |
| mysqldump | `/usr/bin/mysqldump` — **MariaDB 10.11.18** |

---

## 3. Application paths

| Path | Status |
|------|--------|
| `/home/pkjetp/jetpk_app` | EXISTS — owner `pkjetp`, ~1.2G |
| `/home/pkjetp/public_html` | EXISTS — owner `pkjetp`, ~4.4M |
| `jetpk_app/frontend/` | **ABSENT** |
| `jetpk_app/dashboard/` | **PRESENT** (has `.next` ~225M, `node_modules` ~624M) |
| Git metadata | **ABSENT** (SFTP deploy model) |
| Production Git SHA | **UNKNOWN** |

---

## 4. Ports and listeners

| Port | Status | Notes |
|------|--------|-------|
| **3000** | **IN USE** | `127.0.0.1:3000` — nghttpx (forbidden for Next) |
| **3001** | **FREE** | Dashboard Next candidate — **LOCKED** |
| **3002** | FREE | |
| **3003** | FREE | |
| **3010** | **FREE** | Public Next candidate — **LOCKED** |
| **3100** | FREE | |
| 3306 | IN USE | MySQL localhost |
| 80/443 | IN USE | LiteSpeed |

---

## 5. HTTP routing (read-only)

| Route | HTTP | Stack |
|-------|------|-------|
| `/` | 200 | Laravel Blade via `public_html/index.php` |
| `/login` | 200 | Laravel Blade |
| `/about-us` | 200 | Laravel Blade |
| `/faq` | 200 | Laravel Blade |
| `/dev/cp` | 302 → login | Developer CP gated (login required) |

**`.htaccess`:** Standard Laravel front-controller rewrite to `index.php`. No Next proxy active.

**Vhost config (`/usr/local/lsws/conf/vhosts/`):** **NOT READABLE** by `pkjetp`. Proxy cutover requires **CyberPanel operator access** at deploy window (D-02).

---

## 6. Environment safety (status only — no secrets printed)

| Variable | Production state |
|----------|------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://jetpakistan.pk` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `DB_CONNECTION` | `mysql` |
| `CLIENT_ROUTE_PARITY_ENABLED` | `false` |
| `OTA_DEVELOPER_CP_ENABLED` | `true` |
| `OTA_ALLOW_CONTENT_FIXTURE` | **UNSET** |
| `OTA_ALLOW_SESSION_FIXTURE` | **UNSET** |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | **UNSET** |
| `OTP_DEMO_*` | **SET** (values REDACTED — preserved intentional demo exception) |

**Fixture safety gate:** **PASS**

---

## 7. Migrations

```
php artisan migrate:status → PENDING_COUNT=0
```

All **104** repository migrations show **Ran**.  
**MIGRATION ACTION:** `SKIP` (recheck at deployment window; run only if new pending appear).

---

## 8. Queue and scheduler

| Item | State |
|------|-------|
| `QUEUE_CONNECTION` | `sync` |
| Cron | `* * * * * /usr/local/lsws/lsphp83/bin/php .../artisan schedule:run` |
| Queue workers | None required (sync) |
| Supervisor | Not observed |

**Deploy implication:** No `queue:restart` mandatory step.

---

## 9. Storage topology (D-05)

| Path | Type | Target |
|------|------|--------|
| `public_html/storage` | **Symlink** | `/home/pkjetp/jetpk_app/storage/app/public` |
| `jetpk_app/public/storage` | **Directory** | Not a symlink |

**Disposition:** **PRESERVE** `public_html/storage` symlink. Do **not** blind-run `php artisan storage:link`.

---

## 10. Asset drift (D-06 snapshot)

| Asset | `jetpk_app/public` vs `public_html` |
|-------|--------------------------------------|
| `css/ota-public.css` | **MATCH** (md5 `f6cfcd92…`) |
| `build/manifest.json` | **MATCH** (md5 `97fd8536…`) |
| `themes/` | Minor delta: `login.js` only in app themes |
| `js/` | Minor delta: `ota-one-api-checkout.js` only in `public_html/js` |

**Disposition:** Full-release copy + mirror per manifest at deploy. No `--delete` wildcard sync.

---

## 11. Backup feasibility

| Type | Tool / path | Status |
|------|-------------|--------|
| Database | `mysqldump` + gzip → `/home/pkjetp/backups/jetpk-db-<TIMESTAMP>.sql.gz` | **READY** (tool proven; credentials from `.env` at deploy) |
| Filesystem | `tar czf` → `/home/pkjetp/backups/jetpk_app-<TIMESTAMP>.tar.gz`, `public_html-<TIMESTAMP>.tar.gz` | **READY** (80G free) |
| Proxy config | CyberPanel vhost export before cutover | **REQUIRED at deploy** (not readable pre-deploy) |

---

## 12. Sabre gate states (boolean flags only)

| Flag | State |
|------|-------|
| `SABRE_BOOKING_ENABLED` | `true` |
| `SABRE_BOOKING_LIVE_CALL_ENABLED` | `true` |
| `SABRE_TICKETING_ENABLED` | `false` |
| `SABRE_TICKETING_LIVE_CALL_ENABLED` | `true` |
| `SABRE_CANCEL_ENABLED` | `true` |
| `SABRE_CANCEL_LIVE_CALL_ENABLED` | `true` |

(Additional `SABRE_*` keys present; full inventory in deployment env checklist.)
