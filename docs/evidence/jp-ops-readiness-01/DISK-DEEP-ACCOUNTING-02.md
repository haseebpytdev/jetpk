# JP-OPS-INFRA-DISK-DEEP-ACCOUNTING-02

**Mode:** READ-ONLY / FORENSIC / NO-DELETION  
**Probed:** 2026-10-14T22:29–22:35Z  
**Host:** 185.215.166.176  

```
DISK_READ_ONLY=YES
FILES_DELETED=0
FILES_MOVED=0
PERMISSIONS_CHANGED=0
PROTECTED_MAIN_SHA=205db47646585d47a5b7d133372f1aefa0bbb6e1
PRODUCTION_RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
FAILED_JOBS=0
PENDING_DEFAULT=0
DEFAULT_WORKER_RUNNING=NO
```

## Filesystem totals

| Metric | Value |
|--------|------:|
| TOTAL_FS_BYTES | 102,888,095,744 (~96G) |
| USED_FS_BYTES | 47,056,502,784 |
| FREE_FS_BYTES | 55,814,815,744 |
| DISK_USED_PERCENT | **46%** |
| DISK_FREE_GB | **51.98** |
| INODE_USED_PERCENT | 4% |
| TOTAL_FS_USED_GB | **43.82** |

## Where the ~44G used space is

| Bucket | GB | % of df used | Notes |
|--------|---:|---:|-------|
| `/opt` (AI lab) | 16.0 | 36.5% | **Dominant post-cleanup consumer** |
| `/home/pkjetp` | 12.0 | 25.5% | app 2.9G + backups 5.3G + git 1.4G + caches |
| `/usr` | 5.9 | 13.5% | OS + CyberCP 1.2G + LiteSpeed 615M |
| `/tmp` | 3.4 | 7.8% | stale builds + ollama installer tar |
| `/cyberpanel.swap` | 2.0 | 4.6% | swap file at `/` |
| `/var` | 1.3 | 3.0% | mysql 185M + journal 329M |
| `/boot` + `/etc` | 0.2 | 0.5% | |
| **Top-level accounted** | **~41.0** | **93.6%** | |
| Unaccounted (df−du) | ~2.8 | 6.4% | metadata / non-root undercount |

```
ACCOUNTED_TOTAL_GB≈41.0
UNACCOUNTED_GB≈2.8
REMAINING_DISK_USAGE_EXPLAINED_PERCENT≈93.6
OPEN_DELETED_FILES_COUNT=2 (memfd opcache only)
OPEN_DELETED_SPACE_GB=0
```

`du -xhd1 /` = **40G**; df used = **43.82G**. Gap is **not** held-open deleted files. Likely ext4 metadata + paths unreadable to `pkjetp` (e.g. deeper `/root`). Justified below 95% target; no evidence of a hidden multi-GB mystery tree beyond `/opt` + `/home` + `/usr` + `/tmp` + swap.

## Why `jetpk_app` is ~3.2G

| Component | Size | Role |
|-----------|-----:|------|
| frontend/ (tree) | 1017M | source + **641M node_modules** + **330M .next** |
| dashboard/ (tree) | 980M | source + **624M node_modules** + **352M .next** |
| ai-assistant/ | 545M | includes **417M Qwen gguf** (live llama-server) |
| root node_modules | 266M | npm deps at app root |
| storage/ | 152M | uploads/audits/qa artifacts; logs only 4M |
| public/ | 98M | assets (+ storage material; OPS-DEF-0020) |
| vendor/ | 61M | Composer |
| app/+resources/+… | ~30M | Laravel first-party PHP |

```
JETPK_APP_TOTAL_GB=2.91 (du 3.2G human)
SOURCE_CODE_ONLY_MB=23.31
SOURCE_CODE_ONLY_METHOD=sum of first-party PHP/TS/manifest paths excluding vendor, node_modules, .next, storage, frontend/public, ai-assistant/models
SOURCE_PLUS_STATIC_ASSETS_MB≈163 (23.3 + frontend/public 41.9 + non-upload public estimate; public total 98M includes storage-linked material)
COMPOSER_VENDOR_MB=38.1
NODE_MODULES_MB=1372.9
NEXT_BUILD_MB=326.6
DASHBOARD_BUILD_MB=349.7
STORAGE_MB=142.3
APP_LOGS_MB=4.0
APP_CACHE_MB≈0.8 (storage/framework) + webpack caches inside .next
```

```
IS_JETPK_APP_3_2GB_REASONABLE=YES
WHY=Almost all size is Next node_modules (~1.3G) + .next builds (~0.7G) + on-box AI model (0.4G). Proprietary source is ~23MB.
COULD_JETPK_RUNTIME_BE_SMALLER=YES
```

PM2/`next-server` cwd proof:

- PID frontend → `/home/pkjetp/jetpk_app/frontend` (serves `.next`)
- PID dashboard → `/home/pkjetp/jetpk_app/dashboard`
- `llama-server` → model under `jetpk_app/ai-assistant/models/…gguf` (not `/opt`)

So `node_modules` are **BUILD_ONLY** for Next (keep for rebuilds); `.next` is **RUNTIME_REQUIRED**.

## Database

```
DB_DATA_DIR=/var/lib/mysql
DB_DATA_SIZE=185M (filesystem)
JETPK_DATABASE_SIZE_MB=126.66 (information_schema logical)
OTHER_DATABASE_SIZE_MB=0
BINLOG_SIZE=0
```

## Root-owned residuals (OPS-DEF-0023 still OPEN)

```
ROOT_OWNED_OBSOLETE_GB≈4.35
```

Includes jp-ask-ui + jp-perf-final dirs (~2.1G), release tree (~51M), `/tmp` root trees including `ollama-linux-amd64.tar.zst` (1.4G).

## Future optimization (classify only — not executed)

Highest leverage if ever approved later:

1. **REVIEW** `/opt/jetpakistan-ai-lab` (16G) — appears unused by live llama-server  
2. **SAFE_CLEANUP_LATER** root-owned `/tmp` build + ollama tarball (~3G)  
3. **SAFE_CLEANUP_LATER** OPS-DEF-0023 backup dirs (~2.1G) with sudo  
4. **REVIEW** `jetpk_git*` clones (1.4G)  
5. **OPTIMIZE_DEPLOYMENT** stop shipping `node_modules` on prod if build-elsewhere (saves ~1.3G at rebuild risk)

**Another cleanup pass is optional, not urgent** at 46% / 52G free. Prefer QA Wave A over more disk work unless AI-lab decommission is decided.

## Must remain untouched

- Current + pre-Wave1C checkpoints  
- Live `.next` trees + Qwen gguf  
- `storage/app/public` uploads  
- `/var/lib/mysql`, LiteSpeed, CyberPanel, `cyberpanel.swap`  
- OPS-DEF-0020 `public/storage` (no `storage:link`)

## Wave A

```
SAFE_TO_RESUME_WAVE_A=YES
```

Disk accounting complete; no infra blocker. Deep journeys still need QA-authenticated sessions (OPS-DEF-0018).

## Artifacts

- `ROOT-DISK-ACCOUNTING.csv`
- `HOME-PKJETP-ACCOUNTING.csv`
- `JETPK-APP-SIZE-BREAKDOWN.csv`
- `STORAGE-BREAKDOWN.csv`
- `VAR-DISK-ACCOUNTING.csv`
- `LARGEST-FILES.csv`
- `FUTURE-DISK-OPTIMIZATION-CANDIDATES.csv`
- `_raw-deep-02/` raw probe dumps
