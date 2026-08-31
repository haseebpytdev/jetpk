# Worktree classification — JP-FINAL-CLOSURE-01-R6G

`UNKNOWN_WORKTREE_CHANGES=NO` — every remaining dirty/untracked item has a known category.

## Tracked dirty files

| PATH | PREEXISTING_BEFORE_R6 | R6_GENERATED | OWNER_WORK | SAFE_TO_LEAVE_UNTOUCHED | STAGED |
|---|---|---|---|---|---|
| `app/Console/Commands/JetpkEmailPreviewCommand.php` | YES | NO | YES | YES | NO |
| `app/Mail/GoogleCustomerWelcomeMail.php` | YES | NO | YES | YES | NO |
| `resources/views/emails/themes/jetpakistan/partials/blocks/group-reservation.blade.php` | YES | NO | YES | YES | NO |

## Untracked top-level categories

| Category | Classification | SAFE_TO_LEAVE |
|---|---|---|
| `tmp/` | local probes, deploy/perf scripts, checkpoints | YES |
| `frontend/tmp/` | frontend scratch | YES |
| `frontend/public/tesseract/` | self-hosted OCR assets (local) | YES |
| `dashboard/tmp/` | dashboard scratch | YES |
| `.claude/` | local agent skills/config | YES |
| `.playwright-cli/` | local Playwright CLI | YES |
| `.pnpm-store/` | local package store | YES |
| `docs/evidence/jp-production-e2e-cert-01/` | other phase evidence | YES |
| `docs/evidence/jp-ux-portal-perf-01/...` | other phase evidence | YES |
| `docs/phases/JP-BO-04-STAGE-B-FINAL.md` | other phase doc | YES |
| `agent-wallet-full.yml` | local fixture | YES |
| `frontend/tests/_wave9_tmp_ref.ts` | local test scratch | YES |

## Policy

- Do **not** `git clean` / `reset --hard` / `restore .`
- Pre-existing email owner work remains unstaged
- `WORKTREE_CLEAN=NO` is acceptable while `UNKNOWN_WORKTREE_CHANGES=NO`
