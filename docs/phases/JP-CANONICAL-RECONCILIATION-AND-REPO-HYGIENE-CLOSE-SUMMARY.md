# JP-CANONICAL-RECONCILIATION-AND-REPO-HYGIENE-CLOSE

**Phase:** JP-CANONICAL-RECONCILIATION-AND-REPO-HYGIENE-CLOSE  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Status:** PASS (P0), PARTIAL (P2 hygiene — tracked legacy dirs retained with rationale)

## Objective

Reconcile diverged Git histories into one canonical integration branch, verify production authority, deploy only required Authority-06 runtime delta, and audit repository hygiene.

## Git reconciliation

| Field | Value |
|---|---|
| PREVIOUS_INTEGRATION_HEAD | `19023bae22518fa60c41def4aaeffaa594dfaabb` |
| PREVIOUS_AUTHORITY06_HEAD | `67510da49dfaeb5bef676cd4c11981c6b287dc02` (expected checkpoint `2c521c73` superseded) |
| MERGE_BASE | `a48f6e1591c09c2e0a886be58e76f7fc57b8ba7f` |
| RECONCILED_CANONICAL_HEAD | `fafb6c18899e719cc89bc2d153cda8550a7d6101` |
| INTEGRATION_ONLY_COMMITS | 3 (`7a0a56da`, `1eb20a2e`, `19023bae`) |
| AUTHORITY06_ONLY_COMMITS | 17 (`e6dbf03f`…`67510da4`) |
| BEHIND/AHEAD | integration 3 ahead / authority 17 ahead of merge-base |

Merge: `merge(authority-06): reconcile homepage CMS authority into canonical integration` — automatic merge, no conflicts.

Proof flags: `AUTHORITY06_REQUIRED_SOURCE_PRESENT=YES`, `MAIL_SABRE_FIX_PRESENT=YES`, `LATEST_INTEGRATION_WORK_PRESENT=YES`, `NO_REQUIRED_COMMIT_LOST=YES`.

## Production

| Field | Value |
|---|---|
| PRODUCTION_SHA_BEFORE | `1eb20a2e31f6c13ffaf6e09a02051d19ac61c0d0` (release `jetpk-20260912T043724Z`) |
| PRODUCTION_SHA_AFTER | `fafb6c18899e719cc89bc2d153cda8550a7d6101` (release `jetpk-20260912T085843Z`) |
| DEPLOYMENT_REQUIRED | YES (Authority-06 runtime delta; not mail redeploy) |
| PUBLIC_BUILD_ID | `GD1TfR699ZoenojFa4tg-` (was `DQNbshvrgnA1p0vduCsSU`) |
| DASHBOARD_BUILD_ID | rebuilt with deploy |
| PRE_PROXY_GATE | PASS |
| PUBLIC_HTTP | 200 |
| DASHBOARD_HTTP | 307 (admin redirect — expected) |
| LARAVEL_HEALTH | 200 |

## Closed scopes (not reopened)

- MAIL_SABRE_DEPLOYMENT=CLOSED (`7a0a56da` preserved, not redeployed for acceptance)
- GMAIL_REAL_CLIENT_ACCEPTANCE=CLOSED

## Tests

- Laravel affected filter suite: 38 passed / 113 assertions
- Route page health audit: pass=55 fail=0
- Frontend typecheck: PASS
- Local frontend production build: FAIL (homepage SSG timeout without production Laravel — server build PASS)

## P2 hygiene

See `docs/evidence/jp-canonical-reconciliation-close/hygiene-audit.md`.
