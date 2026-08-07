# JETPK-RELEASE-02 — Final Predeploy Readiness

**Phase:** JETPK-RELEASE-02  
**Branch:** `phase/jetpk-release-02-final-predeploy-readiness`  
**Engineering-complete SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Merge subject:** `merge: close JETPK-UI-09`  
**Program:** JETPK-PREDEPLOY-CLOSURE / Loop B — PREDEPLOY_READINESS  
**Status:** **BLOCKED** (production SSH authentication unavailable)  
**Deployment executed:** No  
**Production mutations:** 0

---

## 1. Executive summary

Engineering closure is **complete** (22/22 UI gaps closed at `b95efd4`). Loop B release documentation is prepared on this branch, but **JETPK_PREDEPLOY_READY cannot be emitted** until a fresh read-only production SSH audit completes.

**Blocker:** `SSH_AUTH_UNAVAILABLE` — `pkjetp@185.215.166.176` rejects `jetpk_contabo_2026_v2` in non-interactive mode.

**Live site (HTTP-only):** `https://jetpakistan.pk/` remains **Blade-primary**; public Next and dashboard Next are **not** cut over.

---

## 2. Engineering evidence (SHA-bound — valid while application source unchanged)

| Gate | Disposition | Evidence |
|------|-------------|----------|
| UI gaps | 22/22 CLOSED | Controller seq 7; `docs/ui/JETPK-UI-*` |
| Frontend typecheck | PASS | Loop A final gate |
| Frontend lint | PASS | Loop A final gate |
| Frontend smoke/regression | PASS | `homepage.spec.ts`, `jetpk-ui-09-final-regression.spec.ts` |
| Dashboard typecheck | PASS | Loop A final gate |
| Dashboard lint | PASS | Loop A final gate |
| Dashboard smoke | PASS | `jetpk-ui-09-regression.spec.ts`, `overview.smoke.spec.ts` |
| Laravel JetPakistan | PASS | 21 passed, 2 skipped |

If `main` application source diverges from `b95efd4` before deploy, **re-run engineering regression** before cutover.

---

## 3. Readiness decisions

| ID | Decision | Status | Notes |
|----|----------|--------|-------|
| D-01 | Public Next port | **PROVISIONAL** | Candidate `3010` (Release-01); **must recheck** `ss -ltnp` at deploy window. Forbidden: `3000` (nghttpx). |
| D-01B | Dashboard Next port | **PROVISIONAL** | Candidate `3001`; **must recheck** availability. |
| D-02 | Reverse proxy method | **PROVISIONAL** | LiteSpeed/CyberPanel proxy `jetpakistan.pk` → `127.0.0.1:$PUBLIC_NEXT_PORT`; Laravel `/laravel/*` via Next rewrite. **Requires panel/config read access to confirm.** |
| D-03 | Dashboard first-cutover scope | **LOCKED** | Admin + Staff dashboard Next included in first controlled cutover. |
| D-04 | Process manager | **PROVISIONAL** | PM2 user-local install to `$HOME/.npm-global` or `$NPM_PREFIX`; **not installed** per Release-01. Install only at authorized deploy. |
| D-05 | Storage reconciliation | **PROVISIONAL** | Preserve `public_html/storage` symlink; do not blind `storage:link`. **Reverify on host.** |
| D-06 | Final asset copy set | **DRAFT** | See `JETPK-RELEASE-02-FILE-MANIFEST.md`; drift recompute **pending SSH**. |
| D-07 | Fixture-flag production policy | **LOCKED (repo)** | Never set `OTA_ALLOW_CONTENT_FIXTURE` / `OTA_ALLOW_SESSION_FIXTURE` in production. Never use `start-smoke.mjs`, `playwright-server.mjs`, or `npm run test:smoke` for production launch. **Host verification pending.** |
| D-08 | `OTA_DEVELOPER_CP_ENABLED` | **PROVISIONAL** | Middleware `developer.cp` returns 404 when disabled; requires `dev_cp_user_id` session when enabled → **SAFE_PROTECTED if enabled with active developer_users only**. **Verify production flag state on host.** |
| D-09 | Database backup method | **DRAFT** | MySQL `mysqldump` to timestamped path under `/home/pkjetp/backups/` — **method unproven without SSH.** |
| D-10 | Filesystem/proxy backup | **DRAFT** | Tar archives of `jetpk_app`, `public_html` critical paths, `.htaccess` — **capacity unverified.** |
| D-11 | Build strategy | **LOCKED (repo)** | Build `frontend/` and `dashboard/` on server after `npm ci`; production start via `next start -p <port>` only. |

---

## 4. Blockers (release-critical)

| # | Blocker | Resume condition |
|---|---------|------------------|
| B-01 | SSH authentication to `185.215.166.176` failed | Unlock key / restore authorized_keys; successful `ssh ... echo ok` |
| B-02 | Production fixture flags unverified | Read-only `.env` status scan confirms safe states |
| B-03 | Migration disposition unverified | `php artisan migrate:status` on verified PHP 8.3 CLI |
| B-04 | Port plan unverified | `ss -ltnp` confirms 3010/3001 free |
| B-05 | Asset drift manifest incomplete | Targeted hash/compare vs `b95efd4` |
| B-06 | DB backup path unproven | Confirm `mysqldump` availability and disk headroom |

---

## 5. Recommendation

**NOT READY — BLOCKED at PRODUCTION_CAPTURE.**

Proceed to explicit deployment authorization **only after** Loop B resumes, SSH capture completes, all D-01–D-11 resolve without PROVISIONAL/DRAFT status, and Release-02 docs merge to `main`.

---

## 6. Related documents

- [JETPK-RELEASE-02-PRODUCTION-CAPTURE.md](./JETPK-RELEASE-02-PRODUCTION-CAPTURE.md)
- [JETPK-RELEASE-02-FILE-MANIFEST.md](./JETPK-RELEASE-02-FILE-MANIFEST.md)
- [JETPK-RELEASE-02-DEPLOYMENT-PLAN.md](./JETPK-RELEASE-02-DEPLOYMENT-PLAN.md)
- [JETPK-RELEASE-02-ROLLBACK-PLAN.md](./JETPK-RELEASE-02-ROLLBACK-PLAN.md)

**Historical (read-only):** Release-01 @ `8dd32ce` — do not merge or deploy from that branch.
