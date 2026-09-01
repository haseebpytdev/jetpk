# Worktree reconciliation — JP-AI-ASSIST-02A-R2 predeploy

## Contradiction resolution

Prior 02A matrix reported `UNKNOWN_WORKTREE_CHANGES=0` / `UNEXPLAINED_WORKTREE_FILES=0`
while `git status` showed untracked paths. Those paths were **classified historical /
owner evidence leftovers**, not unknown AI-02A runtime drift. They remain untracked
and are **not staged** for deploy.

## Classification (`git ls-files --others --exclude-standard`)

| Path | Class |
|------|-------|
| `docs/evidence/jp-final-closure-01/live-final/r6/perf-r6e.raw.txt` | R7/R6_EVIDENCE |
| `docs/evidence/jp-production-e2e-cert-01/**` | HISTORICAL |
| `docs/evidence/jp-ux-portal-perf-01/live-final-r2/17-review-traveler-compact-FAIL.png` | R7/R6_EVIDENCE |
| `docs/phases/JP-BO-04-STAGE-B-FINAL.md` | HISTORICAL |

## Counts

- PREDEPLOY_UNTRACKED_PATH_COUNT=12 (expanded file list under untracked dirs)
- PREDEPLOY_CLASSIFIED_UNTRACKED_PATH_COUNT=12
- UNKNOWN_WORKTREE_CHANGES=0
- UNEXPLAINED_WORKTREE_FILES=0
- STAGED_UNRELATED_FILES=0
- `git diff --cached` empty

No deletion of untracked evidence performed.
