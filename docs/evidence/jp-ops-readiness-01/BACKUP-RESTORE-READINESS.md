# Backup / Restore Readiness — Current Checkpoint

**Verification date (UTC):** 2026-10-14T21:59Z (integrity) / 2026-10-14T22:08Z (finalize)  
**Task:** JP-OPS-INFRA-DISK-RETENTION-01

## Checkpoint identity

```
RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
FRONTEND_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
BACKUP_TIMESTAMP=20261014T215304Z
APP_ARCHIVE=/home/pkjetp/backups/jetpk_app-20261014T215304Z.tar.gz
APP_ARCHIVE_SHA256=e1434049885fa3e828eaddcad6e6a23894614596513eb69cac2ccfc6077ca35e
DB_ARCHIVE=/home/pkjetp/backups/jetpk-db-20261014T215304Z.sql.gz
DB_ARCHIVE_SHA256=f42d29f9d256a24f5f8137e02d7c04b5751424bfddc0dadda13d2a4d5997cfc0
PUBLIC_HTML_ARCHIVE=/home/pkjetp/backups/public_html-20261014T215304Z.tar.gz
PUBLIC_HTML_ARCHIVE_SHA256=c8c78841c22edf35fa9da4c0ac31eab32e202c5ace4564c99cf329fab0b1d4fa
MANIFEST=/home/pkjetp/backups/CURRENT-CHECKPOINT-20261014T215304Z.manifest
DB_ENGINE=MariaDB (mysqldump)
```

## Integrity

```
RESTORE_ARTIFACT_INTEGRITY=PASS
APP_BACKUP_INTEGRITY=PASS
DB_BACKUP_INTEGRITY=PASS
CHECKSUMS_RECORDED=YES
VERIFIED_READABLE=PASS
FULL_RESTORE_DRILL=NOT_EXECUTED
RESTORE_PACKAGE_INTEGRITY=PROVEN
```

Non-destructive checks performed:

- `gzip -t` on app + DB archives
- `tar` list + extract of `jetpk_app/.jetpk-runtime-sha` (= a138eb57…)
- Core paths present: `artisan`, `composer.lock`, runtime marker
- DB: MariaDB dump header; 113 `CREATE TABLE`; size > 1KB; not imported

## Previous rollback (do not mislabel as current)

```
PRE_WAVE1C_APP=/home/pkjetp/backups/jetpk_app-20261014T192251Z.tar.gz
PRE_WAVE1C_DB=/home/pkjetp/backups/jetpk-db-20261014T192251Z.sql.gz
ROLE=PREVIOUS_VERIFIED_ROLLBACK (pre-Wave1C deploy)
```

## Required restore order (destructive — do not run without change window)

1. Confirm current incident warrants rollback; record reason + operator.
2. Enable maintenance mode if customer-facing writes must stop.
3. Stop queue workers / cron consumption for notifications during cutover.
4. Restore application tree from `APP_ARCHIVE` into a staging path; verify SHA markers.
5. Restore `public_html` from `PUBLIC_HTML_ARCHIVE` if webroot diverged.
6. Restore DB from `DB_ARCHIVE` into a **non-production** validate instance first when possible; production import only with explicit approval.
7. Point runtime at restored tree (existing deploy activation procedure).
8. Cache clears: `php artisan optimize:clear` (and view:clear/cache if Blade touched).
9. Post-restore validation: `/up`, homepage, `/login`, `/admin`, `/agent`, BUILD_ID, runtime SHA, `failed_jobs`, pending queues.
10. Abort criteria: SHA mismatch, health fail, BUILD_ID unexpected change, queue storm, payment/supplier mutation required to “fix” restore.

## Abort criteria

- Archive checksum mismatch vs manifest
- Runtime SHA inside archive ≠ authorized target
- DB dump unreadable / empty
- Post-restore `/up` or auth surfaces fail
- Any need to touch OPS-DEF-0020 `storage:link` during restore improvisation

## Explicit non-claims

- No isolated restore environment drill was executed.
- `FULL_RESTORE_DRILL` remains `NOT_EXECUTED`.
