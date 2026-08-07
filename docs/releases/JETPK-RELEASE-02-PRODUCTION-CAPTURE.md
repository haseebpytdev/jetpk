# JETPK-RELEASE-02 — Production Capture (Partial)

**Phase:** JETPK-RELEASE-02  
**Branch:** `phase/jetpk-release-02-final-predeploy-readiness`  
**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Capture status:** **PARTIAL — SSH AUTHENTICATION BLOCKED**  
**Capture started:** 2026-08-07T20:00Z (UTC)  
**Authoritative host:** `185.215.166.176` (obsolete host `65.109.34.176` — do not use)

---

## 1. Capture blocker

| Item | Result |
|------|--------|
| SSH key path | `C:\Users\khadi\.ssh\jetpk_contabo_2026_v2` (exists) |
| Connection | `ssh -F NUL -i ... -o BatchMode=yes pkjetp@185.215.166.176` |
| Outcome | **`Permission denied (publickey,password)`** |
| Retries | 3 attempts with `BatchMode=yes` (no interactive passphrase) |

**Resume condition:** Operator unlocks SSH key in agent session **or** restores `pkjetp@185.215.166.176` authorized_keys for `jetpk_contabo_2026_v2` public key, then re-run Loop B from `PRODUCTION_CAPTURE`.

**Not performed (requires SSH):** disk/memory, PHP path, migrate:status, `.env` flag states, port listeners, process list, storage topology, asset drift hashes, DB size, cron, queue workers.

---

## 2. External HTTP observations (read-only, unauthenticated)

Captured from public internet without authentication. **Does not replace** host-level audit.

| URL | Observed stack | Notes |
|-----|----------------|-------|
| `https://jetpakistan.pk/` | **Blade/Laravel** primary | Hero copy “Every flight from Pakistan, one honest fare”; classic JetPakistan Blade homepage sections (group packages, featured deals, destinations). **Not** Next.js production cutover. |
| Routing inference | LiteSpeed → `public_html` → Laravel | No evidence of public Next proxy active on live vhost. |

**Production Git SHA:** **UNKNOWN** (no host access).

---

## 3. Historical baseline (Release-01 — READ ONLY, revalidate required)

From `phase/jetpk-release-01-pre-deployment-readiness` @ `8dd32ce` (ACCESS-01-R2 audit 2026-08-06). **Do not treat as current fact.**

| Item | Historical value |
|------|------------------|
| Laravel root | `/home/pkjetp/jetpk_app` |
| Public webroot | `/home/pkjetp/public_html` |
| PHP CLI (working) | `/usr/local/lsws/lsphp83/bin/php` |
| PHP CLI (disproven) | `/opt/alt/php-fpm83/usr/bin/php` |
| Node | `/usr/local/bin/node` v22.23.1 |
| PM2 | **Not installed** |
| Port 3000 | **nghttpx** (occupied — forbidden for public Next) |
| `public_html/storage` | Symlink → `jetpk_app/storage/app/public` |
| `jetpk_app/public/storage` | Directory (not symlink) |
| `QUEUE_CONNECTION` | `sync` (historical — reverify) |
| Migrations pending | **0** (historical — reverify) |
| `frontend/` on server | Absent |
| `dashboard/` on server | Absent |

---

## 4. Fixture-flag safety (repository policy — production state UNVERIFIED)

UI-09 introduced gated smoke override `OTA_ALLOW_CONTENT_FIXTURE` (server env, set only by `start-smoke.mjs`). Production **must** have:

| Variable | Required production state | Verified on host |
|----------|---------------------------|------------------|
| `OTA_ALLOW_CONTENT_FIXTURE` | UNSET or FALSE | **NO — SSH blocked** |
| `OTA_ALLOW_SESSION_FIXTURE` | UNSET or FALSE | **NO — SSH blocked** |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | UNSET or FALSE | **NO — SSH blocked** |

`clients/jetpk/env.production.example` documents `OTA_DEVELOPER_CP_ENABLED=false` and `CLIENT_ROUTE_PARITY_ENABLED=false` as recommended production defaults.

---

## 5. Next capture checklist (when SSH resumes)

1. Host identity, `df -h`, `df -i`, `free -m`
2. Path topology + ownership under `/home/pkjetp`
3. PHP/Node/npm/Composer/PM2 versions and paths
4. `ss -ltnp` for ports 3000–3003 and candidates 3010/3100
5. `.htaccess` and readable proxy config
6. `.env` **status-only** scan (no secret values)
7. `php artisan migrate:status`
8. Cron / scheduler / queue disposition
9. Storage symlink reconciliation
10. Targeted asset drift manifest vs `b95efd4` main
