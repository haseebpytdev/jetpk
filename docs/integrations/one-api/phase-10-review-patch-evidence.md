# Phase 10 — Review patch evidence

## Artifact

`storage/app/one-api-phase-10-review.patch` — generated from `git diff HEAD` over shared + runtime paths (tracked files only).

## `git apply --check` (current working tree)

```text
git apply --check storage/app/one-api-phase-10-review.patch
```

Result: **FAIL** — hunks already present in the working tree (e.g. `SupplierProvider.php`, `routes/web.php`, shared routers). This is **expected** when apply-check runs on the same tree that produced the diff.

## Isolated review procedure

1. Use `storage/app/one-api-phase-10-stage-new-files.ps1` for dedicated new paths.
2. Use `storage/app/one-api-phase-10-stage-shared-files.ps1` + `git add -p` for mixed files listed in `one-api-phase-10-mixed-files.txt`.
3. For a clean-room check: create a **fresh worktree at merge base**, apply patch there, or commit staged One API slice and diff `HEAD~1..HEAD`.

## Excluded from patch

- Unrelated Sabre/PIA/IATI/CMS/UI_test changes
- `storage/app/one-api-phase-10-*` evidence (except ops scripts if ops policy requires)
- Vendor documentation
- Untracked new files (listed in `one-api-phase-10-new-files.txt` — stage explicitly)
