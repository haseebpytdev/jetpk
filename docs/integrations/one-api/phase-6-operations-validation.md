# Phase 6 — Operations validation (v6 scripts)

**Artifacts:** `one-api-predeploy-backup-v6.sh`, `one-api-rollback-v6.sh`, `one-api-sftp-upload-v6.txt`, `one-api-post-deploy-v6.sh`, `one-api-required-config-v6.md`

## Static checks (2026-07-23)

| Check | Result |
|-------|--------|
| `bash -n` on backup script | **Not run** — Git Bash not invoked in this Windows pass |
| ShellCheck | **Not available** in session |
| Placeholder/TODO in scripts | **None** in generated v6 shell (grep) |
| Per-file SFTP `put` | **Pass** — no `put -r` |
| Remote mkdir | **Pass** — `-mkdir` per deploy directory |
| Tests/fixtures excluded from deploy list | **Pass** |
| Manifest mismatch abort in rollback | **Pass** — requires manifest.tsv |

## Disposable dry-run

Full server dry-run **not executed** (no deploy per phase rules).

## Recommendation

Scripts are **structurally ready for ops review**; execute `bash -n` and ShellCheck on Linux/macOS CI before first production use.
