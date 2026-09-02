# JP-NEXT-PERF-02A — Rollback retention

## Requirement

`FINAL_VERIFIED_ROLLBACK_COUNT=2`

## Verified packs (production)

Under protected `jetpk-production-run` lock (`jp-next-perf-02a-rollback`):

1. `/home/pkjetp/backups/jp-next-perf-02-20260902T072000Z`
2. `/home/pkjetp/backups/jp-next-perf-02-20260902T074907Z`

```
BACKUP_ID=jp-next-perf-02-20260902T074907Z
BACKUP=PASS
CANDIDATES=2
FINAL_VERIFIED_ROLLBACK_COUNT=2
```

No new source deploy. Second pack created/verified under established governance when count was previously 1.
