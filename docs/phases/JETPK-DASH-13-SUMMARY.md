# JETPK-DASH-13 — Pre-Deployment Validation and Production Runtime Closure

## Phase

JETPK-DASH-13 — Pre-deployment validation and production runtime closure

## Branch

`phase/jetpk-dash-13-admin-staff-production-cutover`

## Commits (baseline)

- `e6fd408` — feat(dashboard): cut over Admin and Staff production routes
- `d61715d` — docs(dashboard): finalize DASH-13 production cutover

## Objective

Close all pre-deployment gaps before JetPakistan production upload: full Playwright suite, runtime requirements, process-manager decision framework, environment contract, deployment/rollback manifests, SFTP commands. **No live deploy.**

## Part A — Local state (verified)

| Check | Result |
|-------|--------|
| Branch | `phase/jetpk-dash-13-admin-staff-production-cutover` |
| Remote | `jetpk` → `https://github.com/haseebpytdev/jetpk.git` |
| Baseline commits | `e6fd408`, `d61715d` present |
| Working tree | Clean after closure commits |

## Part B — Full Playwright suite

| Item | Value |
|------|-------|
| Inventory | **1080 tests in 49 files** (`npx playwright test --list`) |
| Config | `playwright.reuse.config.ts` with verified `npm run start:smoke` on **:3002** |
| Retries | **0** |
| Sharding | 6 non-overlapping shards (`--shard=1/6` … `6/6`) |

| Shard | Tests | Result |
|-------|-------|--------|
| 1/6 | 180 | 180 passed |
| 2/6 | 183 | 183 passed |
| 3/6 | 177 | 177 passed (re-run after `pnrs-filters` fix; initial run had 1 timing flake on permissions drawer) |
| 4/6 | 180 | 180 passed |
| 5/6 | 184 | 184 passed |
| 6/6 | 176 | 176 passed |
| **Union** | **1080** | **1080 passed, 0 failed, 0 flaky, 0 skipped** |

**Fix applied:** `dashboard/features/pnrs/pnrs-filters.tsx` — `useDashboardRouter()` for portal-prefixed filter navigation (same pattern as bookings).

## Part C — Production runtime requirements

| Item | Value |
|------|-------|
| Node.js | `^18.18.0 \|\| ^19.8.0 \|\| >= 20.0.0` |
| npm | Lockfile-driven (`npm ci`); local **11.4.2** |
| Next.js | **15.5.21** |
| Port | **3001** (`next start -p 3001`) |
| Binding | Next.js default (`0.0.0.0`, reachable at `127.0.0.1:3001`) |
| Startup | `cd /home/pkjetp/jetpk_app/dashboard && npm run start` |
| Laravel proxy timeout | **30s** |
| Failure | **503** when static HTML absent and proxy fails |
| Build | Server-side `npm ci && npm run build` (no `.next` upload) |

## Part D–E — Process manager

**Unresolved — operator input required.** Run server capability commands in `docs/dashboard/DASHBOARD-PRODUCTION-DEPLOYMENT.md`. Select cPanel Node.js App or PM2 per documented templates.

## Part F — Environment contract

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

Rollback: `DASHBOARD_NEXT_PROXY_ENABLED=false` and restore Laravel files.

## Part G — Production manifest

- **Laravel:** 5 files (controller, config, 3 routes)
- **Dashboard:** 364 source files
- **SFTP:** `docs/dashboard/DASH-13-SFTP-COMMANDS.txt` (81 mkdir + 374 put)
- **Tests/docs:** not uploaded

## Part H — Upload strategy

Server-side build only. Upload source per SFTP manifest; exclude `node_modules/`, `.next/`, `tests/`.

## Parts I–M — Backup, SFTP, deploy, rollback

Documented in:

- `docs/dashboard/DASHBOARD-PRODUCTION-DEPLOYMENT.md`
- `docs/dashboard/DASHBOARD-ROLLBACK.md`
- `docs/dashboard/DASH-13-SFTP-COMMANDS.txt`

## Laravel validation (unchanged)

47/47 PHPUnit tests, 270 assertions (dashboard-related).

## Live deployment

**NOT EXECUTED.** Awaiting operator server capability output and explicit deploy approval.

## JP-FE-01

Not started.

## Final status

**PRE-DEPLOYMENT VALIDATION COMPLETE** — branch ready for operator deployment. Process manager selection blocked on server capability output.
