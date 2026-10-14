# JP-OPS-READINESS-01 Wave 1A — Backup / Restore Readiness

**Mode:** READ-ONLY

## Inventory

```
BACKUP_PRESENT=YES
BACKUP_LOCATION=/home/pkjetp/backups
BACKUP_TOTAL_SIZE≈35G
FULL_APP_TARBALL_COUNT=27 (jetpk_app-*.tar.gz ≈30G)
LATEST_BACKUP_TIMESTAMP=2026-10-12T19:24:08Z (jetpk_app-20261012T192408Z.tar.gz ≈1.14G)
BACKUP_RETENTION=UNDOCUMENTED_IN_PRACTICE (many historical full dumps retained)
RESTORE_PROCEDURE_DOCUMENTED=PARTIAL (protected deploy/rollback docs exist; SHA targets may be stale)
RESTORE_TEST_PROVEN=NO
RESTORE_READINESS=UNPROVEN
```

## Assessment

- Backups **exist and are frequent**, but retention is unconstrained → primary disk consumer.
- Presence ≠ restore proof. No Wave 1A restore drill executed (forbidden).
- Recommend Wave 1B: off-host archive oldest tarballs; keep newest + milestone; document restore dry-run separately.
