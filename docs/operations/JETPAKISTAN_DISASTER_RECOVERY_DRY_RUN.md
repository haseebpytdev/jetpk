# Disaster recovery dry-run (no production downtime)

**Date:** 2026-09-21  
**Mode:** read-only / offline verification — production processes were **not** stopped.

| Check | Result |
|---|---|
| `git rev-parse jetpakistan-recovered-final-20260921` | annotated tag object `bb427529…` peels to `78dadc7b…` |
| Checkpoint branch resolves | `checkpoint/jetpakistan-app-release-20260921` → `78dadc7b…` |
| Metadata backup branch resolves | `backup/jetpakistan-post-recovery-20260921` → `8c5d79da…` |
| `git bundle verify JetPakistan-Recovered-20260921.bundle` | PASS (complete history) |
| Bundle SHA256 match (local + server copy) | `364d9b04d4f54e054536dd56124725c65238a69848a82f31f432e96edc33d5ca` |
| Rollback SHA resolves | `cbd7686feadd35773fd0b597117538b8b99b59fa` |
| Restricted backup dir exists (mode 700) | `/home/pkjetp/backups/jetpakistan-recovered-20260921/` |
| DB dump exists + `gzip -t` | YES (private path under `$BACKUP/db/`) |
| OLS assert evidence present | PASS route ownership (live conf copy limited without sudo) |
| Active env paths documented | Laravel/frontend/dashboard `.env*` locations recorded |
| PM2 cwd ownership | frontend + dashboard under `jetpk_app` |

```
DISASTER_RECOVERY_DRY_RUN=PASS
```

No production cutover, no PM2 stop of live apps, no DB overwrite.
