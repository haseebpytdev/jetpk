# JP-NEXT-PERF-02C — worktree / kiwi reconciliation

## kiwi64-G9.png

`KIWI_G9_CLASSIFICATION=TEMP_VERIFICATION`

- Untracked repo-root file (4860 bytes) from logo verification work.
- Canonical tracked asset already present: `public/storage/airline-logos/G9.png` (22916 bytes).
- Also evidenced under `docs/evidence/jp-ux-polish-02b/…`.
- **Removed** exactly: `kiwi64-G9.png`.

## kiwi64-GF.png

`KIWI_GF_CLASSIFICATION=TEMP_VERIFICATION`

- Untracked repo-root file (5949 bytes) from logo verification work.
- Canonical tracked asset already present: `public/storage/airline-logos/GF.png` (5253 bytes).
- **Removed** exactly: `kiwi64-GF.png`.

## Other

`UNEXPLAINED_KIWI_FILES=0` for the required pair.

Remaining untracked `kiwi-9P.png` / `kiwi-PF.png` are the same class (TEMP_VERIFICATION vs canonical `9P.png` / `PF.png` in `public/storage/airline-logos/`) and were **not** in the mandated delete list; left untouched to avoid scope creep.

No `git clean`, no wildcards.
