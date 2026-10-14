# JP-OPS-READINESS-01 Wave 1A — Log Health Audit

**Mode:** READ-ONLY  
**Probed:** 2026-10-14

## Laravel / app logs (`storage/logs`)

| Metric | Value |
|--------|-------|
| Directory size | **4.3M** |
| Active `laravel.log` | ~158K (1040 lines) |
| Rotation | Present (`laravel.log.1`, `.2.gz`…`.14.gz`) |
| Largest file | `pia-ndc-2026-09-19.log` **1.6M** (stale supplier probe log) |
| Oldest notable | `laravel-before-post-cutover-20260729…` |
| Notification worker log | `notification-worker-cron.log` 172K (last write Oct 8) |

## PM2 logs (`~/.pm2/logs`)

| File | Size | Notes |
|------|------|-------|
| jetpk-dashboard-out.log | 610K | active |
| jetpk-dashboard-out.log.1 | 1.3M | rotated |
| jetpk-public-frontend-out.log | 209K | active |
| error logs | small / gzipped | present |

Rotation evidence: dated `.log.N` / `.gz` files under both Laravel and PM2.

## Returns

```
LOG_ROTATION_CONFIGURED=YES (observed rotated files; not full logrotate.d proof)
UNBOUNDED_LOG_GROWTH_RISK=LOW
LOG_DISK_USAGE=≈7M combined app+pm2 (not disk driver)
TOP_LOG_ERROR_CLASSES=
  1. Historical SMTP recipient rejects (failed_jobs / mailer) — Sep only
  2. Residual PIA NDC daily logs (Sep; frozen channel — do not treat as live stream)
  3. No evidence of current No-space / permission storm in active laravel.log size
```

**Note:** Disk pressure is backups (35G), not logs.
