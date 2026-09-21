# JetPakistan pre-cleanup server inventory

**Captured:** 2026-09-21T09:49Z–10:00Z UTC  
**Host:** `185.215.166.176` (`vmi3400777`) / `pkjetp`  
**Work branch:** `ops/post-recovery-cleanup-20260921`  
**Machine-readable:** [`pre-cleanup-inventory.json`](./pre-cleanup-inventory.json)

## Disk (before cleanup)

| Metric | Value |
|---|---|
| Filesystem | `/dev/sda1` 96G |
| Used | 63G (66%) |
| Available | 33G (`DISK_BEFORE_AVAIL=34996514816` bytes) |

## Top disk consumers (`/home/pkjetp`)

| Path | Size |
|---|---|
| `/home/pkjetp` total | 25G |
| `backups/` | 9.3G |
| `jetpk_app/` | 8.6G |
| `releases/` | 4.4G (**666 entries**) |
| `.npm` / `.cache` / `jetpk_git` | ~2.2G combined |

## Active authority (verified)

```
ACTIVE_LARAVEL_PATH=/home/pkjetp/jetpk_app
ACTIVE_PUBLIC_NEXT_PATH=/home/pkjetp/jetpk_app/frontend
ACTIVE_DASHBOARD_PATH=/home/pkjetp/jetpk_app/dashboard
ACTIVE_PUBLIC_BUILD=wmNT0P2lJftqG2n9tM6gp
ACTIVE_DASHBOARD_BUILD=3TyyvdcNScpOGj0oCkQuT
ACTIVE_RUNTIME_SHA=78dadc7b330ca24a6183241458dd8d1f6aa0d454
ACTIVE_AUTHORIZED_SHA=78dadc7b330ca24a6183241458dd8d1f6aa0d454
ACTIVE_ROLLBACK_SHA=cbd7686feadd35773fd0b597117538b8b99b59fa
```

### PM2

| App | Status | exec cwd | Ports observed |
|---|---|---|---|
| `jetpk-public-frontend` | online | `/home/pkjetp/jetpk_app/frontend` | `127.0.0.1:3010` |
| `jetpk-dashboard` | online | `/home/pkjetp/jetpk_app/dashboard` | `127.0.0.1:3001` |

Laravel private listener: `127.0.0.1:8088`. MySQL: `127.0.0.1:3306`. Redis: `127.0.0.1:6379`.

### OLS

- Vhost conf directory not readable by `pkjetp` without sudo (`OLS_SUDO=NO` during backup).
- Assert log `/tmp/jp-ols-assert-provenance.log` reports `ROUTE_OWNERSHIP_GUARD=PASS`, short-URL → public Next.
- Logrotate: `/etc/logrotate.d/jetpakistan` present.

## Environment file locations (names only)

- `/home/pkjetp/jetpk_app/.env` (+ historical `.env.bak*` / `.env.before*` — **KEEP**, never commit)
- `/home/pkjetp/jetpk_app/frontend/.env.production.local`
- `/home/pkjetp/jetpk_app/dashboard/.env.production.local`
- Multiple `.env.example` under `releases/` and `jetpk_git/`

## Storage / uploads

- Laravel `storage/` present and owned by `pkjetp` (cache/flight-search trees active).
- Public uploads metadata captured in restricted backup manifests only.

## Large archives (sample)

Multiple ~1GB `backups/jetpk_app-*.tar.gz` and many `releases/*.tar.gz` staged trees — primary cleanup pressure.

## Temporary / harness dirs

Home `jp-*` / `jetpk-dash-03-*` / acceptance / profile-dropdown backups present; `cbd7686f` harness dirs classified **ROLLBACK_KEEP**.

## Logs

- Laravel: `storage/logs/laravel.log` (~31M) + rotated `.gz` siblings
- PM2 logs under `~/.pm2/logs`
- Notification worker cron log present
- Cron: queue worker every minute against `/home/pkjetp/jetpk_app/artisan`

## Classification seed

See [`CLEANUP-CANDIDATES.md`](./CLEANUP-CANDIDATES.md). No deletes performed during this inventory capture.
