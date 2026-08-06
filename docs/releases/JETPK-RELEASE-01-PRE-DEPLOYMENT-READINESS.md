# JETPK-RELEASE-01 — Pre-Deployment Readiness

**Phase:** JETPK-RELEASE-01
**Branch:** `phase/jetpk-release-01-pre-deployment-readiness`
**Authoritative baseline SHA:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Merge subject:** `merge: close JP-FULLSTACK-01G`
**Program status:** JP-FULLSTACK-01 closed (01A–01G + 01A-R1)
**Audit date:** 2026-08-06
**01A inspection:** 2026-08-06T17:38–17:41Z (read-only; obsolete host — invalid evidence)
**ACCESS-01 inspection:** 2026-08-06T18:00Z (corrected host; passphrase blocked — superseded)
**ACCESS-01-R2 inspection:** 2026-08-06T18:18–18:22Z (corrected host; agent-unlocked SSH; full read-only capture)
**Authoritative production host:** `185.215.166.176` (obsolete: `65.109.34.176` — do not use)
**Deployment executed:** No
**Commit / push / merge:** Not authorized in this phase

---

## 1. Executive summary

This release packages the **complete JP-FULLSTACK-01 program** at `8d62db8`: Laravel API/session authority, **82-route Next.js public frontend**, Customer and Agent portals, guest lookup, CMS fixture hardening, branding audits, and expanded regression coverage.

**Production baseline status:** **No single reliable production deployment SHA exists in repository metadata.** Documented historical baselines conflict:

| Document | Recorded production / runtime SHA | Notes |
|----------|-----------------------------------|-------|
| `JETPK-FULL-PUBLIC-PAGE-CMS-PRODUCTION-READINESS-CLOSURE.md` | `45865a0aa39c037e375eba775d1cea182aa7cddb` | Pre-CMS Blade era |
| `JETPK_CANONICAL_UI_CMS_CORRECTION_SSH_COMMANDS.md` | Runtime `a9bc7826f4beb14deeaef29e17bbf4bfd195a737` | Canonical UI correction |
| `PHASE18-PRODUCTION-DEPLOYMENT-PLAN.md` | Prior HEAD `72c6f3f3…` | Incremental Sabre/search deploy |
| `clients/jetpk/deployment.json` | `last_deployed_at: null` | Never updated |

**Manifest strategy:** **Full-release manifest** (see `JETPK-RELEASE-01-FILE-MANIFEST.md`). Do **not** guess a delta deployment.

**Recommendation:** **NOT READY FOR CONTROLLED JETPAKISTAN DEPLOYMENT** until pre-flight blockers in §12 are resolved. Release documentation and command design are complete.

---

## 2. Deployment architecture (confirmed)

| Item | Value |
|------|-------|
| Laravel application root | `/home/pkjetp/jetpk_app` |
| Live public webroot | `/home/pkjetp/public_html` |
| Public JS mirror | `/home/pkjetp/public_html/js` |
| PHP CLI (JetPakistan) | `/opt/alt/php-fpm83/usr/bin/php` |
| Node (documented) | `/home/pkjetp/.nvm/versions/node/v24.18.0/bin/node` |
| PM2 (dashboard) | `jetpk-dashboard` → `127.0.0.1:3001` |
| Deploy method | SFTP changed files / controlled full sync — **no `git pull` on production** |

### Dual-root rule

`/home/pkjetp/jetpk_app/public` and `/home/pkjetp/public_html` are **independent directories** (not symlinked). Theme CSS, `client-assets/`, and `public/js/*` served to browsers from `public_html` must be **mirrored** when updated. Uploading only to `jetpk_app/public` does **not** update the live vhost.

### Runtime topology

```
Browser → public_html (Apache/Nginx vhost)
              ├─ static: themes/, client-assets/, js/, css/, build/
              └─ reverse proxy → Next.js public frontend (127.0.0.1:3000) [REQUIRED — see blocker]
                    └─ /laravel/* rewrite → Laravel (jetpk_app, PHP-FPM)

Laravel jetpk_app ← session, CSRF, API, admin/staff Blade, booking engines
Dashboard Next    ← 127.0.0.1:3001 (PM2 jetpk-dashboard, documented)
```

### Next.js public frontend disposition

- **Source:** `frontend/` (781 tracked files at baseline)
- **Build output:** `frontend/.next/` (generated on server — **do not upload** from local)
- **Runtime:** `next start -p 3000` (`frontend/package.json`)
- **Laravel proxy:** `frontend/next.config.ts` rewrites `/laravel/:path*` → `LARAVEL_URL` / `NEXT_PUBLIC_LARAVEL_URL`
- **Production CMS fixtures:** denied when `NODE_ENV=production` (JP-FULLSTACK-01G)

**Ambiguity / blocker:** Repository documents dashboard PM2 (`DASH-13`) but **does not document** an existing production PM2 process or vhost proxy for the **public** Next app on port **3000**. Operator must confirm or create before cutover.

### Laravel proxy / rewrite dependencies

- Next depends on Laravel for session cookies, CSRF (`XSRF-TOKEN`), OTP, RBAC JSON, flight search, booking, payments, CMS `api.public.content.*`
- `CLIENT_ROUTE_PARITY_ENABLED=false` in `clients/jetpk/env.production.example` (JetPakistan canonical `/` root)
- Blade fallbacks remain on disk for agent/customer/guest/checkout — not deleted during deploy

### Public asset synchronization

| Path pattern | jetpk_app target | public_html mirror | Notes |
|--------------|------------------|--------------------|-------|
| `public/themes/frontend/jetpakistan/**` | Yes | **Yes** (same relative path) | Bump `$jpAssetVersion` in Blade when CSS changes |
| `public/client-assets/jetpk-assets/**` | Yes | **Yes** | Client branding uploads |
| `public/js/**` | Yes | **Yes** (`public_html/js/`) | One-API checkout, flight modals, etc. |
| `public/css/**` | Yes | **Yes** when referenced by live vhost | `ota-public.css` cache-bust |
| `public/build/**` | Yes | Per vhost mapping | Vite manifest — **rebuild on server** |
| `storage/app/public` | Via `storage:link` | Optional media URL | User uploads |

### storage:link

Required when `FILESYSTEM_DISK=public` and branded media uses `storage/app/public`. Run once per environment if link missing:

```bash
/opt/alt/php-fpm83/usr/bin/php artisan storage:link
```

Do not overwrite existing `public/storage` symlink without backup.

### Paths that must NOT be deleted or blind-synced

- `/home/pkjetp/jetpk_app/.env`
- `/home/pkjetp/jetpk_app/storage/` (except clearing framework cache subdirs)
- `/home/pkjetp/jetpk_app/storage/app/private/` (booking documents)
- `/home/pkjetp/public_html/client-assets/` user uploads
- `/home/pkjetp/public_html/.htaccess` (unless intentionally replaced)
- Production database

---

## 3. Release scope summary

| Category | Tracked files (approx.) | Deploy |
|----------|------------------------:|:------:|
| Laravel `app/` | 1,799 | Yes |
| `config/` | 43 | Yes |
| `routes/` | 10 | Yes |
| `database/migrations/` | 104 | Upload only; run pending |
| `resources/views/` | 639 | Yes (Blade fallbacks + admin) |
| `public/` (excl. generated) | 104 | Yes + mirror |
| `frontend/` source | 781 | Yes |
| `frontend/.next/` | 0 (generated) | Build on server |
| `dashboard/` | 468 | Yes (if not already at baseline) |
| `clients/jetpk/` metadata | 6 | Optional (no secrets) |
| `tests/` | 1,377 | **No** |
| `docs/` | 659 | Optional ops docs only |
| `vendor/` | composer lock | **composer install on server** |
| Root `node_modules/` + `public/build/` | generated | **npm run build on server** |

---

## 4. Migration audit summary

**Total migrations in repo:** 104
**Production pending set:** **Unknown** — operator must run `php artisan migrate:status` on server and diff against this list.

### High-attention migrations (likely pending on older production)

| Migration | Purpose | Risk | Backfill | Code order |
|-----------|---------|------|----------|------------|
| `2026_06_17_100000_add_must_change_password_to_users_and_developer_users.php` | Force-password columns | Low lock; additive | No | Migration before or with code |
| `2026_07_06_120000_create_client_page_builder_tables.php` | CMS page builder tables | Medium (new tables) | No | Migration before CMS routes |
| `2026_07_13_000001_backfill_jetpk_homepage_content_minimum_cards.php` | Homepage CMS card minimums | **Data mutation** | **Yes** | After builder tables |
| `2026_07_16_120000_create_client_page_setting_revisions_table.php` | CMS revision history | Low | No | Before defaults |
| `2026_07_16_130000_create_client_page_setting_defaults_table.php` | CMS defaults | Low | No | Before client pages |
| `2026_07_19_120000_create_client_pages_table.php` | Custom page registry | Low | No | Before custom page routes |
| `2026_07_10_150000_add_jetpk_dashboard_performance_indexes.php` | Index adds | Lock on large tables | No | Off-peak preferred |
| `2026_06_23_120000_rename_pia_provider_to_pia_ndc.php` | Provider rename | Data update | Yes | Before supplier code |

### Data-backfill migrations (rollback caution)

- `2026_05_11_120000_normalize_airport_catalog_for_autocomplete.php`
- `2026_06_23_120000_rename_pia_provider_to_pia_ndc.php`
- `2026_07_13_000001_backfill_jetpk_homepage_content_minimum_cards.php`

**Database rollback:** Not safe as a blanket strategy. Individual `down()` methods exist but production rollback should prefer **forward-fix** and **DB restore from backup** over `migrate:rollback` on data-bearing migrations.

### Seeders (production)

| Seeder | Production use |
|--------|----------------|
| `OtaFoundationSeeder` | Reference data only when explicitly authorized |
| `AirportAirlineReferenceSeeder` | Reference airports/airlines if catalog empty |
| `DefaultCmsPagesSeeder` | **Not** without separate CMS decision |
| `OtaFinanceDemoSeeder` | **Forbidden** on production |
| `ResponsiveAgentPortalAuditSeeder` | **Forbidden** on production |

---

## 5. Environment requirements (summary)

Full matrix in §7 below. Critical production gates:

| Variable | Production requirement |
|----------|---------------------|
| `NODE_ENV` | `production` on Next build/start |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | **Unset or false** — never `true` |
| `OTA_ALLOW_SESSION_FIXTURE` | **Unset or false** — Playwright only |
| `OTP_DEMO_*` | Preserve existing production values — do not disable without authorization |
| `SABRE_*_ENABLED` / `*_LIVE_CALL_ENABLED` | Do not auto-reset; verify before deploy |
| `APP_DEBUG` | `false` |
| `QUEUE_CONNECTION` | `database` (documented) |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `CLIENT_ROUTE_PARITY_ENABLED` | `false` for JetPakistan |

Secrets (`APP_KEY`, `DB_*`, `MAIL_PASSWORD`, supplier tokens in DB) — **never print or commit**.

---

## 6. Build and runtime commands (reference)

See `JETPK-RELEASE-01-DEPLOYMENT-PLAN.md` for ordered sequence. PHP path: `/opt/alt/php-fpm83/usr/bin/php`.

---

## 7. Environment variable matrix

| Variable | Required | Secret | Production | Notes |
|----------|:--------:|:------:|:----------:|-------|
| `APP_KEY` | Yes | Yes | Existing | Never rotate without plan |
| `APP_ENV` | Yes | No | `production` | |
| `APP_DEBUG` | Yes | No | `false` | **Forbidden:** `true` |
| `APP_URL` | Yes | No | `https://www.jetpakistan.com` | |
| `DB_*` | Yes | Yes | Existing | |
| `SESSION_DRIVER` | Yes | No | `database` | |
| `SESSION_SECURE_COOKIE` | Yes | No | `true` | HTTPS |
| `SESSION_DOMAIN` | Optional | No | Aligned with cookie domain | |
| `QUEUE_CONNECTION` | Yes | No | `database` | |
| `CACHE_STORE` | Yes | No | `database` | |
| `FILESYSTEM_DISK` | Yes | No | `public` | |
| `MAIL_*` | Yes | Partial | SMTP production | |
| `OTA_CLIENT_SLUG` | Yes | No | `jetpk` | |
| `OTA_ACTIVE_THEME` | Yes | No | `jetpakistan` | |
| `OTA_PUBLIC_ASSET_PROFILE` | Yes | No | `jetpk-assets` | |
| `OTA_MODULE_*` | Yes | No | Per `clients/jetpk/env.production.example` | |
| `CLIENT_ROUTE_PARITY_ENABLED` | Yes | No | `false` | |
| `DASHBOARD_NEXT_SERVER_URL` | If dashboard | No | `http://127.0.0.1:3001` | Existing DASH-13 |
| `DASHBOARD_NEXT_PROXY_ENABLED` | If dashboard | No | `true` | |
| `LARAVEL_URL` | Yes (Next) | No | `http://127.0.0.1` or internal FPM URL | Server-side proxy target |
| `NEXT_PUBLIC_APP_URL` | Yes (Next) | No | Public site URL | |
| `NEXT_PUBLIC_LARAVEL_URL` | Yes (Next) | No | Same-origin or `/laravel` base | |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | No | No | **Forbidden `true`** | JP-OPS-02 / 01G |
| `NEXT_PUBLIC_SESSION_PREVIEW` | No | No | **Forbidden** | Dev/Playwright only |
| `OTA_ALLOW_SESSION_FIXTURE` | No | No | **Forbidden `true`** | Playwright only |
| `OTP_DEMO_FIXED_ENABLED` | Optional | No | Preserve | Intentional demo patch |
| `OTP_DEMO_FIXED_CODE` | If demo on | Yes | Preserve | Never log |
| `OTP_DEMO_ALLOW_PRODUCTION` | Optional | No | Per ops policy | |
| `SABRE_BOOKING_ENABLED` | Yes | No | Default `false` | Do not auto-enable |
| `SABRE_TICKETING_ENABLED` | Yes | No | Default `false` | Hard safety gate |
| `SABRE_*_LIVE_CALL_ENABLED` | Yes | No | Default `false` | |
| `TURNSTILE_*` | If enabled | Partial | Per ops | |
| `NEXT_PUBLIC_DASHBOARD_MODE` | Dashboard only | No | `live` when dashboard live | Not public frontend |
| `NEXT_PUBLIC_USE_MOCK_DATA` | Dashboard only | No | `false` when live | Not public frontend |

---

## 8. Queue and scheduler

| Component | Requirement |
|-----------|-------------|
| Scheduler cron | `* * * * *` → `php artisan schedule:run` |
| Queue worker | `database` driver; Supervisor or cron `queue:work --stop-when-empty` |
| Post-deploy | `php artisan queue:restart` |
| Scheduled tasks | See `routes/console.php` — cleanup, reports, featured fares, group ticketing, branding cleanup |

---

## 9. Writable directories

| Path | Purpose |
|------|---------|
| `storage/` | Logs, cache, sessions, uploads |
| `bootstrap/cache/` | Config/route/view cache |
| `storage/app/private/` | Booking documents |
| `storage/framework/cache`, `sessions`, `views` | Framework |

---

## 10. Post-deployment smoke matrix (design)

Non-destructive checks — see `JETPK-RELEASE-01-DEPLOYMENT-PLAN.md` §Smoke.

| Area | Check | Forbidden action |
|------|-------|-------------------|
| Homepage `/` | 200, JetPakistan branding, CMS from Laravel | — |
| CMS static `/about-us`, `/faq`, `/contact`, `/support` | Render or honest empty | — |
| CMS dynamic `/pages/{slug}`, `/legal/{slug}` | Known slug 200; unknown 404 | — |
| Fixture CMS | No demo marketing copy | — |
| Login `/login` | Shell loads | No real OTP abuse |
| Force-password | Route exists for flagged users | — |
| Flight search | Form renders; search returns results UI | **No booking submit** |
| Results | Cards render | **No PNR** |
| Customer portal | Auth gate; dashboard shell | No mutations |
| Agent portal | Auth gate; RBAC nav | No mutations |
| Travelers | List shell (authorized) | No create/delete |
| Finance read | Statement/ledger read surfaces | — |
| Guest lookup | Form only | **No real PNR disclosure** |
| Payment return pages | Static shell / honest state | **No provider call** |
| Static assets | `themes/`, `client-assets/`, `js/` 200 | — |
| Branding | No Parwaaz/Master/YD visible | — |
| Queue | `queue:failed` empty or stable | — |
| Scheduler | `schedule:list` matches console.php | — |
| Logs | No new `production.ERROR` in tail | — |

---

## 11. Protected areas (verified unchanged in this audit)

- `.env`, `.env.example`, `.env.production.example` — not modified
- `config/` — not modified
- `dashboard/` source — not modified (deploy may sync if behind)
- OTP demo (`config/ota_otp_demo.php`, `OTP_DEMO_*`) — not modified
- RBAC / `AgentPermission` — not modified
- Supplier / booking / payment provider code — not modified in this audit
- Application source — not modified
- Tests — not modified

---

## 12. Release gate blockers (ACCESS-01-R2 reconciled)

| ID | Status | Classification | Evidence |
|----|--------|----------------|----------|
| B-01 | Open | **RESOLVED_APPROXIMATE** | Remote fingerprints on `185.215.166.176` match **`72c6f3f3` era** for `routes/web.php`, `routes/agent.php`, `SavedTravelerController.php`; `composer.json`/`composer.lock` also match `8d62db8`. **`frontend/` absent**; JP-FULLSTACK paths missing. Exact deployed SHA **not proven**; not `8d62db8`. |
| B-02 | Open | **RESOLVED_NOT_DEPLOYED** | Live `jetpakistan.pk` = Laravel Blade via `public_html/index.php` (no Next proxy in `.htaccess`). `frontend/` **missing** on server. PM2 `jetpk-public-frontend` / `jetpk-dashboard` **not found**. Port **3000** listener returns **404** (nghttpx, not Next). Port **3001** closed. Portal routes **404**. |
| B-03 | Closed | **RESOLVED_NO_PENDING** | `migrate:status` via `/usr/local/lsws/lsphp83/bin/php`: **104/104 Ran**, **0 pending**. All JP-FULLSTACK candidate migrations already applied. |
| B-04 | Open | **REQUIRED_FULL_CUTOVER** | `frontend/` absent; partial fingerprint match to pre-`8d62db8` generation; dual-root drift in `themes`/`client-assets`/`js`. Full-release scope required. |
| B-05 | Open | **MISSING** | `GIT_METADATA_PRESENT=no`; all four deployment-marker JSON candidates **missing** on server; repo `clients/jetpk/deployment.json` still `last_deployed_at: null`. |
| B-06 | Open | **DRIFT_CAPTURED** | `css` + `build` **MATCH**; `themes`, `client-assets`, `js` show **CONTENT_DRIFT** (file-count and byte deltas). |

**Operator decisions required:** (1) confirm cutover plan for net-new `frontend/` + Next proxy; (2) reconcile dual-root drift during deploy; (3) create deployment metadata during deploy; (4) update deploy docs to use **`/usr/local/lsws/lsphp83/bin/php`** (documented `/opt/alt/php-fpm83` path **not present**).

---

## 13. Deployment recommendation

**NOT READY FOR CONTROLLED JETPAKISTAN DEPLOYMENT**

Read-only production capture on `185.215.166.176` is **complete** (ACCESS-01-R2). B-03 closed (no pending migrations). B-01/B-02/B-04/B-05/B-06 remain open. Production is a **Blade-primary `72c6f3f3`-era partial baseline** without `frontend/` or public Next cutover. Deploy requires full JP-FULLSTACK upload, Next PM2 setup, vhost proxy configuration, dual-root asset sync, and deployment-metadata creation.

When operator approves cutover, follow `JETPK-RELEASE-01-DEPLOYMENT-PLAN.md` (updated PHP path) and `JETPK-RELEASE-01-ROLLBACK-PLAN.md`.

---

## 14. JETPK-RELEASE-01A — Production state capture (superseded host)

> **Superseded by §16 (ACCESS-01).** The 01A SSH attempt used obsolete host `65.109.34.176`. That `Permission denied (publickey)` result is **invalid evidence** about current JetPakistan production. No production mutation occurred during the obsolete-host attempt.

### 14.1 SSH access (obsolete — invalid evidence)

| Item | Result |
|------|--------|
| Target | `pkjetp@65.109.34.176:22` (**obsolete — do not use**) |
| Outcome | Permission denied — **not authoritative** |

### 14.2–14.6

Retained for audit trail only. All production conclusions must come from corrected host `185.215.166.176` (§16).

---

## 15. JETPK-ACCESS-01 — Corrected-host production state capture

### 15.1 Obsolete host prohibition

| Item | Record |
|------|--------|
| Obsolete host | `65.109.34.176` — **must not be contacted, inspected, or modified** |
| Prior 01A auth failure | **Invalid evidence** for current production |
| Authoritative production host | **`185.215.166.176`** |
| Production mutations during ACCESS-01 | **None** |
| Server-side temp files | **None created** |

### 15.2 Canonical SSH invocation

```powershell
ssh -F NUL `
  -i "$env:USERPROFILE\.ssh\jetpk_contabo_2026_v2" `
  -p 22 `
  -o IdentitiesOnly=yes `
  pkjetp@185.215.166.176
```

### 15.3 Local SSH key verification

| Item | Result |
|------|--------|
| Key path | `%USERPROFILE%\.ssh\jetpk_contabo_2026_v2` |
| Key type | ED25519 |
| SHA256 fingerprint | `SHA256:5kZUoZcXc4M8YtneyXYrr0xk15jf0Uid6iQx8GcVUws` |
| Private key parseable | Yes (`ssh-keygen -lf` succeeds) |
| Encryption | **Passphrase-protected** (`aes256-ctr` / `bcrypt` KDF in OpenSSH key blob) |
| `.pub` file match | Yes (fingerprint matches derived public key) |

### 15.4 Corrected-host authentication test (2026-08-06T18:00Z)

| Item | Result |
|------|--------|
| Target | `pkjetp@185.215.166.176:22` |
| TCP connect | **Success** |
| Server host key | `ssh-ed25519 SHA256:BRsCva5gFtGdvmKfO4aZUqjapvUb9TkV6pT1SLMVogc` (known_hosts match) |
| Key offered | ED25519 `SHA256:5kZUoZcXc4M8YtneyXYrr0xk15jf0Uid6iQx8GcVUws` (IdentitiesOnly=yes) |
| Server key acceptance | **Yes** — `Server accepts key` in SSH debug |
| Client signing | **Failed** — `we did not send a packet, disable method` (passphrase required; BatchMode=yes) |
| Shell access | **Denied** — `Permission denied (publickey,password)` |
| Classification | **UNKNOWN_AUTH_FAILURE** (passphrase-protected key without ssh-agent) |
| Password prompt | No |
| `JETPK_CURRENT_HOST_LOGIN_OK` | **Not emitted** |

**Important:** Server-side `authorized_keys` appears to include this public key on the corrected host. Blocker is **local key unlock**, not wrong host or rejected key on server.

### 15.5 Production host identity (expected — not shell-verified)

| Field | Value |
|-------|-------|
| SSH user (expected) | `pkjetp` |
| Authoritative host | `185.215.166.176` |
| Laravel root (expected) | `/home/pkjetp/jetpk_app` |
| Public webroot (expected) | `/home/pkjetp/public_html` |
| PHP CLI (expected) | `/opt/alt/php-fpm83/usr/bin/php` |
| Live OTA vhost | **`https://jetpakistan.pk`** (Laravel/Blade — verified HTTP) |
| `www.jetpakistan.com` | **Different property** (not this OTA codebase) |

Disk, inode, path permissions, hostname, kernel — **not captured** (shell blocked).

### 15.6 Live HTTP evidence (`jetpakistan.pk`, 2026-08-06T18:00Z)

| URL | Status | Disposition |
|-----|--------|-------------|
| `/` | 200 | Laravel Blade (`data-hero-search`); `jetpakistan-session` + `XSRF-TOKEN` cookies; LiteSpeed/CyberPanel |
| `/login` | 200 | Laravel Blade auth shell |
| `/about-us` | 200 | Laravel CMS/Blade page |
| `/themes/.../theme.css` | 200 | Static theme asset |
| `/_next/static/` | 301 | Next build artifacts **not** primary site authority |
| `/faq` | 200 | Laravel CMS/Blade page |
| `/agent/dashboard` | 404 | Next agent portal **not routed** |
| `/customer/dashboard` | 404 | Next customer portal **not routed** |

No forms submitted. No authentication. No supplier search executed. Renderer: **Laravel Blade** (`data-hero-search` present; no `__NEXT_DATA__`).

### 15.7 Local reference fingerprints (repository baseline `8d62db8`)

Full hash table in `JETPK-RELEASE-01-FILE-MANIFEST.md` §13. Remote compare **not performed** (shell blocked).

| File | SHA-256 @ `8d62db8` |
|------|---------------------|
| `composer.json` | `5da10e335e7074ccae2be803c853188db2f5f78dd5588e5e716c3e54b3c0cc68` |
| `composer.lock` | `b0bb7df2c865d4e4b8a9af3d836ca776d466b60d2a623e30ec17507eecac015c` |
| `package.json` | `915b4ea9d6ad54cbfdfab29d18b5d3815ce69feeeb2b6cf8f7d5ce627cad86d6` |
| `package-lock.json` | `21a8498cee07f0609ddd0153d8c60b6f4331cce1d89e122e346a51553a23c54f` |
| `frontend/package.json` | `4399606abe56658446cbbb34f7b8c11c3efae2c8412ea240feb7b41c28ff5ff1` |
| `frontend/package-lock.json` | `7ad9a6c9c989196b83f1a7f9ba60c805958576726d09913982d334615cbc2b9b` |
| `routes/web.php` | `a670f20ca81ef47c4fcc5fde3a220b9b2ccaf3e6a3644c0f7aa5d90c6d0e0b27` |
| `routes/agent.php` | `21ba57e7f06efe54dfb2e6159e56e420b63a4b6cd87edbb3d36ec3e339f02501` |
| `content-policy.ts` | `5d85038a07b4b5d78ca3516f7f23ef426631317b53bf0cc1f0ffbd991af45444` |
| `content-policy-core.mjs` | `dc5211287d35dc9e4c7692625bc58f220f37b18397598a55255fdb044692d06f` |
| `SavedTravelerController.php` | `2721b2641b64eb1a4ccf63f5a3a28076cff591793d5ac2289ef721d5d684b633` |
| `FinanceStatementController.php` | `8b977d690f3b9ebe625fd5aaea81cf22259ac0ddcfbd3d92b170fec4581850e7` |
| `AccountingLedgerController.php` | `fac9cb0f6c7d47d01be114da68234f442ad7236223ee309c7a0cbc55c0d47799` |
| `GuestBookingLookupController.php` | `ff20060fcb60fafcdbed9ea9cc31bd6d9a79c3359b813c45d45a02d13bc358ab` |

**Deployed revision disposition (B-01):** **UNRESOLVED** — live Blade behaviour suggests pre-FULLSTACK generation; exact commit **not proven** without remote file hashes.

### 15.8 Items not captured (shell access required)

Re-run after `ssh-add` unlocks the private key:

- `whoami`, `hostname`, `df -h`, `df -i`, path existence checks
- Git metadata / deployment markers
- Remote fingerprint loop (§7)
- PHP/Composer/Node/npm/PM2 versions
- PM2 `jetpk-public-frontend` / `jetpk-dashboard` and ports 3000/3001
- Reverse-proxy / ecosystem config
- `php artisan migrate:status`, `about`, `schedule:list`
- Allowlisted `.env` gates (OTP/Sabre boolean only)
- `storage:link` state
- Dual-root asset drift (B-06)
- Cron / queue worker / Supervisor state

---

## 17. JETPK-ACCESS-01-R2 — Complete read-only production capture

### 17.1 SSH authentication (agent-unlocked)

| Item | Result |
|------|--------|
| Agent identity | `SHA256:5kZUoZcXc4M8YtneyXYrr0xk15jf0Uid6iQx8GcVUws` (ED25519) — **loaded** |
| Target | `pkjetp@185.215.166.176:22` |
| Outcome | **Success** — `JETPK_CURRENT_HOST_LOGIN_OK`; user `pkjetp`; hostname `vmi3400777`; cwd `/home/pkjetp` |
| Production mutations | **None** |

Canonical invocation (Windows System32 OpenSSH):

```powershell
& "$env:WINDIR\System32\OpenSSH\ssh.exe" `
  -F NUL `
  -i "$env:USERPROFILE\.ssh\jetpk_contabo_2026_v2" `
  -p 22 `
  -o IdentitiesOnly=yes `
  pkjetp@185.215.166.176
```

### 17.2 Host and filesystem

| Item | Value |
|------|-------|
| Inspection timestamp (UTC) | `2026-08-06T18:18:00Z` |
| Kernel | Linux 6.8.0-136-generic (Ubuntu) |
| Disk `/` | 96G total, 17G used, **80G avail** (18%) |
| Inodes `/` | 12,976,128 total, 367,000 used, **12,609,128 free** (3%) |
| Laravel root | `/home/pkjetp/jetpk_app` — exists (`drwxr-xr-x pkjetp:pkjetp`) |
| Public webroot | `/home/pkjetp/public_html` — exists (`drwxr-xr-x pkjetp:pkjetp`) |
| Documented PHP `/opt/alt/php-fpm83/usr/bin/php` | **Missing** |
| Working PHP CLI | `/usr/local/lsws/lsphp83/bin/php` (8.3.31, pdo_mysql) |
| `/usr/bin/php` | 8.3.6 — **no pdo_mysql** (unsuitable for artisan DB) |

### 17.3 Deployed revision evidence

| Item | Result |
|------|--------|
| Git metadata | **Absent** (`GIT_METADATA_PRESENT=no`) |
| Deployment markers | All four candidates **missing** |
| Closest fingerprint match | **`72c6f3f3` era** (routes + agent controllers); `composer.json`/`lock` also match `8d62db8` |
| `frontend/` on server | **Absent** |
| B-01 classification | **RESOLVED_APPROXIMATE** |

#### Remote SHA-256 fingerprints

| File | Remote SHA-256 | vs `8d62db8` |
|------|----------------|--------------|
| `composer.json` | `5da10e33…0cc68` | **MATCH** |
| `composer.lock` | `b0bb7df2…ac015c` | **MATCH** |
| `package.json` | `3c180926…2ca068` | **DIFFER** |
| `package-lock.json` | — | **MISSING** |
| `frontend/package.json` | — | **MISSING** (dir absent) |
| `routes/web.php` | `907b621d…fb434a` | **DIFFER** (matches `72c6f3f3`) |
| `routes/agent.php` | `beff7353…4e009e` | **DIFFER** (matches `72c6f3f3`/`45865a0`/`a9bc782`) |
| `content-policy.ts` | — | **MISSING** |
| `SavedTravelerController.php` | `e5d96046…e5be5` | **DIFFER** (matches `72c6f3f3`/`45865a0`/`a9bc782`) |
| `FinanceStatementController.php` | `f7663793…86968e` | **DIFFER** |
| `AccountingLedgerController.php` | `c97b21cf…dc4ce7` | **DIFFER** |
| `GuestBookingLookupController.php` | `9b2c25a8…7e9d62` | **DIFFER** |

### 17.4 Runtime versions

| Component | Version | Path |
|-----------|---------|------|
| PHP (artisan) | 8.3.31 | `/usr/local/lsws/lsphp83/bin/php` |
| Laravel | 13.7.0 | — |
| Composer | 2.10.1 | `/usr/bin/composer` |
| Node | v22.23.1 | `/usr/local/bin/node` |
| npm | 10.9.8 | `/usr/local/bin/npm` |
| PM2 | **Not installed** | nvm `v24.18.0` path **missing** |
| Documented nvm Node v24.18.0 | **Absent** | — |

### 17.5 PM2, ports and proxy

| Item | Status |
|------|--------|
| `jetpk-public-frontend` | **NOT_RUNNING_OR_NOT_FOUND** |
| `jetpk-dashboard` | **NOT_RUNNING_OR_NOT_FOUND** |
| Port 3000 | **Listener present** (`127.0.0.1:3000`) — localhost HTTP **404** via nghttpx (not Next public frontend) |
| Port 3001 | **No listener** |
| `public_html/.htaccess` | Standard Laravel rewrite to `index.php` — **no Next/proxy rules** |
| `ecosystem.config.*` | **All missing** |
| `frontend/` directory | **Missing** |
| B-02 classification | **RESOLVED_NOT_DEPLOYED** |
| Vhost config | **VHOST_CONFIGURATION_NOT_READABLE** (account-local `.htaccess` only) |

### 17.6 Live and localhost routing (2026-08-06T18:18Z)

| URL | Status | Authority |
|-----|--------|-----------|
| `http://127.0.0.1:3000/` | 404 | nghttpx (not Next) |
| `http://127.0.0.1:3001/` | unavailable | — |
| `http://127.0.0.1/` | 404 | LiteSpeed |
| `https://jetpakistan.pk/` | 200 | **Laravel Blade** |
| `https://jetpakistan.pk/login` | 200 | **Laravel Blade** |
| `https://jetpakistan.pk/about-us` | 200 | **Laravel Blade** |
| `https://jetpakistan.pk/faq` | 200 | **Laravel Blade** |
| `https://jetpakistan.pk/agent/dashboard` | 404 | unavailable |
| `https://jetpakistan.pk/customer/dashboard` | 404 | unavailable |
| `https://jetpakistan.pk/admin/dashboard` | 404 | unavailable |

### 17.7 Migrations (B-03)

| Metric | Value |
|--------|-------|
| Total migrations | **104** |
| Completed (Ran) | **104** |
| Pending | **0** |
| Classification | **RESOLVED_NO_PENDING** |

All candidate migrations present and **Ran**:

- `2026_06_17_100000` — Ran (batch 3)
- `2026_07_06_120000` — Ran (batch 3)
- `2026_07_13_000001` — Ran (batch 6, **data backfill**)
- `2026_07_16_120000` / `2026_07_16_130000` — Ran (batches 7–8)
- `2026_07_19_120000` — Ran (batch 9)
- `2026_07_10_150000` — Ran (batch 4, index migration)

**Rollback note:** data-bearing `2026_07_13_000001` already applied — DB restore required for content rollback.

### 17.8 Laravel runtime (`artisan about`)

| Item | Value |
|------|-------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | **OFF** |
| `APP_URL` | `jetpakistan.pk` |
| Config cache | CACHED |
| Routes cache | NOT CACHED |
| Views cache | CACHED |
| `QUEUE_CONNECTION` | **`sync`** (not database) |
| `SESSION_DRIVER` | **`file`** (not database) |
| `CACHE_STORE` | **`file`** (not database) |
| `public/storage` (artisan) | LINKED |

### 17.9 Environment gates (allowlisted)

| Gate | Production value |
|------|------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://jetpakistan.pk` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `FILESYSTEM_DISK` | `public` |
| `NODE_ENV` | **not set** in `.env` |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | **not set** (safe) |
| `OTA_ALLOW_SESSION_FIXTURE` | **not set** (safe) |
| `CLIENT_ROUTE_PARITY_ENABLED` | `false` |
| `OTA_DEVELOPER_CP_ENABLED` | `true` |

**OTP demo (preserved — names only for codes):**

| Gate | State |
|------|-------|
| `OTP_DEMO_FIXED_ENABLED` | enabled |
| `OTP_DEMO_ALLOW_PRODUCTION` | enabled |
| `OTP_DEMO_FIXED_CODE` | present (not printed) |
| `OTP_DEMO_ALLOWED_EMAILS` | present (not printed) |

**Sabre gates (boolean only — unchanged):**

| Gate | State |
|------|-------|
| `SABRE_BOOKING_ENABLED` | enabled |
| `SABRE_BOOKING_LIVE_CALL_ENABLED` | enabled |
| `SABRE_TICKETING_ENABLED` | disabled |
| `SABRE_CANCEL_ENABLED` | enabled |
| `SABRE_CANCEL_LIVE_CALL_ENABLED` | enabled |
| `SABRE_CANCEL_ALLOW_PRODUCTION_SEND` | enabled |
| `SABRE_CANCEL_ALLOW_PRODUCTION_HOST` | enabled |
| `SABRE_TICKETING_LIVE_CALL_ENABLED` | enabled |
| `SABRE_PUBLIC_TICKETING_ENABLED` | disabled |
| `SABRE_CHECKOUT_AUTO_TICKETING_ENABLED` | disabled |
| (remaining `SABRE_*_ENABLED` gates) | per server — mostly disabled except booking/cancel/NDC-search subset |

### 17.10 Queue and scheduler

| Item | Value |
|------|-------|
| Cron | `* * * * * /usr/local/lsws/lsphp83/bin/php …/artisan schedule:run` |
| Active scheduler | **Yes** — `schedule:run` observed running |
| Queue driver | `sync` — **no persistent `queue:work` worker** |
| Supervisor | unavailable |
| `queue:restart` impact | **Low** — no persistent worker; sync driver |

### 17.11 Storage links and permissions

| Path | State |
|------|-------|
| `jetpk_app/public/storage` | **Directory** (not symlink) — artisan reports LINKED |
| `public_html/storage` | **Symlink** → `/home/pkjetp/jetpk_app/storage/app/public` |
| `storage/`, `bootstrap/cache` | writable (`drwxrwxr-x pkjetp:pkjetp`) |

### 17.12 Dual-root asset drift (B-06)

| Directory | Files (src→dst) | Bytes (src→dst) | Classification |
|-----------|-----------------|-----------------|----------------|
| `themes` | 33 → 32 | 649,520 → 642,486 | **CONTENT_DRIFT** |
| `client-assets` | 16 → 15 | 1,951,838 → 3,634 | **CONTENT_DRIFT** |
| `js` | 18 → 19 | 488,091 → 496,958 | **CONTENT_DRIFT** |
| `css` | 20 → 20 | 1,502,882 → 1,502,882 | **MATCH** |
| `build` | 5 → 5 | 45,350 → 45,350 | **MATCH** |

**Overall B-06:** **DRIFT_CAPTURED** — mirror `themes`, `client-assets`, `js` during deploy.

### 17.13 Scheduled commands (`schedule:list`)

`ota:cleanup-expired-access`, `ota:send-daily-report`, `ota:send-agency-booking-activity-summary`, `ota:send-weekly-report`, `ota:send-monthly-report`, `ota:send-monthly-ledgers`, `homepage:refresh-featured-fares`, `jetpk:homepage-route-fares-refresh`, `ota:process-abandoned-flight-searches`, `ota:send-abandoned-flight-searches`, `group-ticketing:sync-inventory`, `group-ticketing:release-expired`, `jetpk:branding-background-cleanup`.

---

## 16. Related documents

| Document | Purpose |
|----------|---------|
| `JETPK-RELEASE-01-FILE-MANIFEST.md` | Full-release file classification |
| `JETPK-RELEASE-01-DEPLOYMENT-PLAN.md` | Ordered deploy sequence |
| `JETPK-RELEASE-01-ROLLBACK-PLAN.md` | Rollback procedure |
| `docs/operations/JP-OPS-02-PRODUCTION-RUNTIME-REQUIREMENTS.md` | Runtime authority |
| `docs/frontend/JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.md` | 82 production routes |
