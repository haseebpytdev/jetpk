# JETPK-RELEASE-02 — Final Predeploy Readiness

**Phase:** JETPK-RELEASE-02  
**Branch:** `phase/jetpk-release-02-final-predeploy-readiness`  
**Engineering-complete SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Status:** **JETPK_PREDEPLOY_READY** (pending docs merge to `main`)  
**Production host:** `185.215.166.176`  
**Production audit:** 2026-08-08T03:56Z UTC (read-only SSH)  
**Deployment executed:** No  
**Production mutations:** 0

---

## 1. Executive summary

Engineering closure complete (22/22 gaps). Fresh read-only production audit completed. All readiness decisions **D-01 through D-11** are **LOCKED**. Fixture flags safe on host. Migrations pending = 0. Backup tooling proven. Process manager plan defined (user-local PM2). Reverse proxy method documented for CyberPanel deploy window.

**Recommendation:** **READY FOR EXPLICIT CONTROLLED DEPLOYMENT AUTHORIZATION** (deployment itself remains a separate authorized operation).

---

## 2. Readiness decisions (resolved)

| ID | Decision | Locked value |
|----|----------|--------------|
| **D-01** | Public Next port | **`3010`** (verified free; 3000 forbidden — nghttpx) |
| **D-01B** | Dashboard Next port | **`3001`** (verified free) |
| **D-02** | Reverse proxy | **CyberPanel LiteSpeed External App + Context** proxy `jetpakistan.pk` → `127.0.0.1:3010`; dashboard admin/staff paths → `127.0.0.1:3001`; Laravel API via Next `/laravel/*` rewrite (`LARAVEL_URL=http://127.0.0.1`) |
| **D-03** | Dashboard cutover | **LOCKED IN SCOPE** — Admin + Staff dashboard Next in first cutover |
| **D-04** | Process manager | **PM2 user-local** install to `$HOME/.npm-global` (home writable; global npm prefix not writable) |
| **D-05** | Storage | **PRESERVE** `public_html/storage` symlink; do not blind `storage:link` |
| **D-06** | Asset copy set | **LOCKED** per file manifest; minor themes/js drift only |
| **D-07** | Fixture flags | **SAFE** — all smoke fixture env vars UNSET on host; production must never use smoke launchers |
| **D-08** | Developer CP | **SAFE_PROTECTED** — `OTA_DEVELOPER_CP_ENABLED=true`; `/dev/cp` → 302 login; `developer.cp` middleware + `dev_cp_user_id` session |
| **D-09** | DB backup | **READY** — `mysqldump` MariaDB 10.11.18 at `/usr/bin/mysqldump` |
| **D-10** | Filesystem backup | **READY** — tar to `/home/pkjetp/backups/` (80G free) |
| **D-11** | Build strategy | **BUILD_ON_SERVER** — `npm ci` + `npm run build`; `next start -p <port>` only |

---

## 3. Engineering evidence (SHA-bound)

Valid while `main` application source remains `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`.

| Gate | Result |
|------|--------|
| UI gaps | 22/22 CLOSED |
| Frontend typecheck/lint/smoke | PASS |
| Dashboard typecheck/lint/smoke | PASS |
| Laravel `JetPakistan` filter | 21 passed, 2 skipped |

---

## 4. Production snapshot

| Item | Verified value |
|------|----------------|
| PHP | `/usr/local/lsws/lsphp83/bin/php` 8.3.31 |
| Laravel | 13.7.0 |
| Node/npm | v22.23.1 / 10.9.8 |
| PM2 | Not installed (install at deploy) |
| Migrations pending | **0** / 104 |
| Queue | `sync` + cron scheduler |
| Live routing | Blade/Laravel (no public Next cutover) |
| `frontend/` on server | Absent |
| `dashboard/` on server | Present (stale build — rebuild at deploy) |
| Production Git SHA | UNKNOWN |

---

## 5. Blockers

**0** release-critical blockers remaining.

---

## 6. Related documents

- [JETPK-RELEASE-02-PRODUCTION-CAPTURE.md](./JETPK-RELEASE-02-PRODUCTION-CAPTURE.md)
- [JETPK-RELEASE-02-FILE-MANIFEST.md](./JETPK-RELEASE-02-FILE-MANIFEST.md)
- [JETPK-RELEASE-02-DEPLOYMENT-PLAN.md](./JETPK-RELEASE-02-DEPLOYMENT-PLAN.md)
- [JETPK-RELEASE-02-ROLLBACK-PLAN.md](./JETPK-RELEASE-02-ROLLBACK-PLAN.md)
