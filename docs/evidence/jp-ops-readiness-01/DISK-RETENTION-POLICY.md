# Disk / Backup Retention Policy — JetPakistan Production

**Effective:** 2026-10-14 (JP-OPS-INFRA-DISK-RETENTION-01)  
**Scripts:** `scripts/jetpk/backup-retention.sh`, `scripts/jetpk/disk-health-check.sh`  
**Server install:** `/home/pkjetp/bin/*.sh`

## Backup retention rules

1. Never delete newest verified `jetpk_app-*.tar.gz` (+ paired DB + public_html + manifest).
2. Never delete pinned previous rollback (`RETENTION-PINS.txt` + automatic 2nd-newest app archive).
3. Never delete when no verified newer checkpoint exists.
4. Never auto-delete `UNKNOWN` / non-pattern paths.
5. Protect DB/app pairs by timestamp.
6. Log every dry-run and apply action to `storage/logs/backup-retention.log`.
7. Default mode: `--dry-run`. Apply only with `--apply`.
8. Failure must not affect app availability (standalone cron; no artisan side effects).
9. Free-space context logged; pruning does not require low disk.

### Baseline keep set

| Class | Keep |
|-------|------|
| CURRENT_VERIFIED_CHECKPOINT | 1 (newest) |
| PREVIOUS_VERIFIED_ROLLBACK | 1 (2nd newest + pins) |
| REQUIRED_MILESTONE | manual pin only (e.g. jetpakistan-recovered-20260921) |
| REDUNDANT app/DB/public_html | prune after newer verified checkpoint |
| UNKNOWN | manual review |

### Cron (production)

```
15 */6 * * *  disk-health-check.sh   # every 6 hours
30 3 * * 0    backup-retention.sh --dry-run  # weekly report; apply manually
```

`jetpk-backup.sh` continues to create pre-deploy backups; retention dry-run surfaces candidates; operators run `--apply` after review.

## Disk monitoring thresholds (96G root)

| Level | Used % | Free GB |
|-------|--------|---------|
| NORMAL_TARGET | ≤65% | ≥25GB preferred |
| WARNING | ≥70% OR | <25GB |
| HIGH | ≥80% OR | <15GB |
| CRITICAL | ≥90% OR | <8GB |

Implementation: `disk-health-check.sh` logs to `storage/logs/disk-health.log` and exits 2/3 on HIGH/CRITICAL.  
Also records `failed_jobs` count (GAP-014 companion) to `failed-jobs-health.log`.

### Notification gap

```
DISK_ALERTING=ACTIVE (local log + cron)
ALERT_DELIVERY_EXTERNAL=NOT_WIRED (email/Slack still open under GAP-014)
```

## Root-cause of prior retention failure

```
RETENTION_POLICY_DEFINED=YES (now)
RETENTION_AUTOMATION_EXISTS=YES (now)
RETENTION_AUTOMATION_ENABLED=YES (dry-run cron)
RETENTION_AUTOMATION_RUNNING=YES (disk check every 6h)
RETENTION_AUTOMATION_EFFECTIVE=PARTIAL (apply is manual by design)

ROOT_CAUSE=DEPLOY_BACKUP_ALWAYS_CREATED_BUT_NEVER_PRUNED
EVIDENCE=/home/pkjetp/jetpk-backup.sh has NO retention/prune; crontab had no retention job pre-task
SECONDARY=MONITORING_MISSING for disk until disk-health-check.sh
```
