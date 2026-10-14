# JP-OPS-READINESS-01 Wave 1A — Disk Usage Forensics

**Host:** 185.215.166.176  
**Mode:** READ-ONLY  
**Probed:** 2026-10-14 UTC

## Summary

```
DISK_USED_PERCENT_BEFORE=89
DISK_FREE_BEFORE=12G
FILESYSTEM=/dev/sda1 Size=96G Used=85G
INODES_USED_PERCENT=6
SAFE_RECLAIMABLE_GB≈28-32 (policy-gated; see candidates)
TARGET_DISK_USED_PERCENT_AFTER≈55-65 (if Wave 1B archive/delete approved)
```

## Top consumers (`/home/pkjetp`)

| Path | Size | Class |
|------|------|-------|
| `/home/pkjetp/backups` | **35G** | BACKUPS |
| `/home/pkjetp/jetpk_app` | 3.2G | CURRENT_RELEASE |
| `/home/pkjetp/.cache` | 656M | CACHE |
| `/home/pkjetp/.npm` | 629M | CACHE |
| `/home/pkjetp/jetpk_git` | 489M | OTHER (git worktree/clone) |
| `/home/pkjetp/jetpk_git_align37` | 427M | OTHER |
| `/home/pkjetp/jetpk_git_cq40` | 427M | OTHER |
| `/home/pkjetp/jetpk_git_writable` | 175M | OTHER |
| `/home/pkjetp/releases` | 62M | OLD_RELEASES |
| `/var/log` | 430M (journal 332M) | SYSTEM_LOGS |
| `/var/lib/mysql` | 185M | DATABASE |

## Application breakdown (`jetpk_app`)

| Path | Size | Class |
|------|------|-------|
| `frontend/` | ~1017M | CURRENT_RELEASE / BUILD_ARTIFACTS / NODE_MODULES |
| `dashboard/` | ~980M | CURRENT_RELEASE / BUILD_ARTIFACTS / NODE_MODULES |
| `ai-assistant/` | 545M | OTHER |
| `node_modules/` (root) | 266M | NODE_MODULES |
| `storage/` | 153M | APP (uploads/logs/cache) |
| `public/` | 98M | CURRENT_RELEASE (+ `public/storage` 91M) |
| `vendor/` | 61M | CURRENT_RELEASE |
| `storage/logs` | **4.3M** | APPLICATION_LOGS (not dominant) |

## Backup concentration

- **27** files matching `jetpk_app-*.tar.gz`
- Aggregate size of those tarballs: **~30G**
- Newest observed: `jetpk_app-20261012T192408Z.tar.gz` (~1.14G, 2026-10-12)
- Also large historical dirs: SEO/perf frontend trees with nested `.next`/`node_modules`

## Classification notes

- Disk pressure is **backup retention**, not live app logs.
- Inodes healthy (6%).
- Live release `/home/pkjetp/jetpk_app` is modest vs backups.
- Do **not** delete current release, `.env`, DB, or newest required backup without Wave 1B approval.
