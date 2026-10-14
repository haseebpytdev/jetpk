# JP-OPS-INFRA-DISK-RETENTION-01 — Disk Forensic Snapshot

**Host:** 185.215.166.176 (`jetpakistan.pk`)  
**Probed:** 2026-10-14T21:49Z → cleanup 2026-10-14T22:08Z  
**Mode:** PRODUCTION-SAFE / FORENSIC-FIRST  

## Phase 0 health (pre-mutation)

```
CANONICAL_MAIN_SHA=a138eb570b05910046df7fb251beeef839b25395
DEPLOYED_RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
RUNTIME_SHA_MATCH=YES
DASHBOARD_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
FAILED_JOBS=0
PENDING_DEFAULT=0
DEFAULT_WORKER_RUNNING=NO
HTTP: /up / /login /admin /agent => 200
DEPLOY_SHA_TXT_PRE=ab26661f0107d9bd8f9889dda1d59567d93be683 (STALE)
```

## Disk before / after

| Metric | PRE (forensic) | PRE-DELETE (after checkpoint) | POST |
|--------|----------------|-------------------------------|------|
| Used % | 74% | 76% | **46%** |
| Free | 26G | 24G | **52G** |
| Reclaimed | — | — | **≈28.1G** |

Filesystem: `/dev/sda1` 96G. Inodes healthy (~6%).

## Top consumers (PRE)

| Path | Size | Class |
|------|------|-------|
| `/tmp` (staging builds) | **15G** | TEMP / SAFE_REGENERABLE |
| `/home/pkjetp/backups` | **22G** | BACKUP (mostly jetpk_app-*.tar.gz) |
| `/home/pkjetp/jetpk_app` | 3.2G | CURRENT_RUNTIME |
| `/home/pkjetp/.cache` + `.npm` | ~1.3G | CACHE |
| `jetpk_git*` clones | ~1.5G | UNKNOWN_REVIEW (retained) |
| `/var/log` | 428M | SYSTEM |

See `DISK-TOP-CONSUMERS.csv`.

## Root cause of disk growth

1. `jetpk-backup.sh` creates full app+DB+public_html on every deploy with **no retention**.
2. Historical phase dirs + `/tmp` Next.js build trees never pruned.
3. No scheduled disk alert until this task.

## Residual (requires root)

Root-owned paths pkjetp cannot delete (~2.1G backups + leftover `/tmp` + one release tree):

- `/home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z` (root)
- `/home/pkjetp/backups/jp-perf-final-02d/02e-*` (root)
- `/tmp/jp-frontend-build-1790242108` and related root `/tmp` trees
- `/home/pkjetp/releases/jetpk-20260930T080534Z` (partial permission denied)

Disk target **≤65% already met** at 46% without root cleanup.

## Safety

```
OPS_DEF_0020_TOUCHED=NO
SUPPLIER_PAYMENT_MUTATIONS=0
HISTORICAL_NOTIFICATION_REPLAY=0
FRONTEND_REGRESSION=NO
```
