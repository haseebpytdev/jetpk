# JETPK-DASH-13 — Final Operator Deployment Package

## Branch

`phase/jetpk-dash-13-admin-staff-production-cutover`

## HEAD

`b80d6e6` (successor commits document operator package)

## Objective

Operator-ready production deployment package for Admin/Staff Next.js dashboard cutover. **No Cursor deployment.**

## Verified production runtime

| Item | Value |
|------|-------|
| OS | AlmaLinux 8.10 (x86_64, glibc 2.28) |
| Node.js | v24.18.0 |
| npm | 11.16.0 |
| PM2 | 7.0.3 (fork mode) |
| Bind | 127.0.0.1:3001 |
| Persistence | PM2 + one-minute cron watchdog + pm2 save |
| PHP 8.3 | reaches 127.0.0.1:3001 |

## Playwright

| Metric | Result |
|--------|--------|
| Inventory | 1080 tests, 49 files |
| Passed | 1080 |
| Failed | 0 |
| Flaky | 0 |
| Skipped | 0 |
| Retries | 0 |
| Shard 3 | 177/177 |
| Drawer focus | 5/5 |

## Process manager (final)

**PM2 7.0.3 fork mode** — process name `jetpk-dashboard`, cwd `/home/pkjetp/jetpk_app/dashboard`, `npm run start`.

Watchdog: `/home/pkjetp/bin/ensure-jetpk-dashboard.sh` via one-minute cron.

## Environment

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

Rollback: `DASHBOARD_NEXT_PROXY_ENABLED=false`

## Production manifest

- Laravel: 5 files
- Dashboard: 369 source files (SFTP in `DASH-13-SFTP-COMMANDS.txt`)
- Server build: `npm ci && npm run build`
- Watchdog: `docs/dashboard/ensure-jetpk-dashboard.sh` → `/home/pkjetp/bin/`

## Deployment status

**NOT EXECUTED** from Cursor. Operator executes per `DASHBOARD-PRODUCTION-DEPLOYMENT.md`.

## JP-FE-01

Not started.

## Final status

**OPERATOR PACKAGE COMPLETE** — awaiting live deploy and post-sign-off merge.
