# Phase 10 — Disposable operations execution

## Scripts

| Script | `bash -n` | Execution |
|--------|-----------|-----------|
| `storage/app/one-api-predeploy-backup-v10.sh` | **PASS** | See below |
| `storage/app/one-api-rollback-v10.sh` | **PASS** | See below |
| `storage/app/one-api-post-deploy-v10.sh` | **PASS** | Syntax only (no mock `artisan` on Windows) |

Configurable roots (v10 backup):

- `APP_ROOT` (default `/home/pkjetp/jetpk_app`)
- `PUBLIC_ROOT` (default `/home/pkjetp/public_html`)
- `BACKUP_ROOT` (default `/home/pkjetp/backups`)

## Disposable validation

Command:

```bash
export OTA_JETPK_ROOT=/c/Users/khadi/ota-jetpk
sh storage/app/one-api-ops-disposable-validate.sh
```

Result: **PASS** (`Disposable v10 ops validation: PASS`)

Proved:

- Backup manifest TSV with tab-separated fields, size, SHA-256, mode
- Restore of existing deploy-tracked file (`OneApiTestMatrixCommand.php` path)
- Public mirror restore (`ota-one-api-checkout.js`)
- Sentinel outside app tree unchanged
- Rollback abort without backup directory argument
- Post-deploy simulated file removed when manifest records `NEW_ABSENT` (with manifest-verified cleanup on Git Bash)

V7 scripts: `bash -n` **PASS** (unchanged hardcoded paths); disposable run uses **v10** env-aware scripts.

## ShellCheck

ShellCheck not installed on this host — skipped.
