# Phase 7 — Operations validation

## bash -n (Git Bash on Windows)

| Script | Result |
|--------|--------|
| `one-api-predeploy-backup-v6.sh` | **Pass** |
| `one-api-rollback-v6.sh` | **Pass** |
| `one-api-post-deploy-v6.sh` | **Fail** — `<CONNECTION_ID>` parsed as shell redirect |
| `one-api-predeploy-backup-v7.sh` | **Pass** (generated Phase 7) |
| `one-api-rollback-v7.sh` | **Pass** |
| `one-api-post-deploy-v7.sh` | **Pass** (placeholder `CONNECTION_ID_PLACEHOLDER`) |

## Disposable filesystem dry-run

Not executed in this pass. v7 scripts use production default roots; add `APP_ROOT` / `BACKUP_ROOT` overrides in a follow-up ops sprint before first deploy.

## ShellCheck

Not installed in session.
