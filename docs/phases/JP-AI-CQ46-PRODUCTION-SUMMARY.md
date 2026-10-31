# JP-AI-CQ46 — PRODUCTION MERGE, DEPLOY & SOFT-PREFERENCE CERTIFICATION

## Phase

CQ46 production certification (merge PR #53, protected AI deploy, soft-preference UAT).

## Branch

`work/jp-ai-cq46-production-evidence` (evidence/docs only)

## Objective

Production-certify CQ46 ranking/time soft preferences:

- never become lead names
- persist as soft conversation state
- avoid unnecessary Qwen on simple travel preferences
- preserve pending flight confirmation and active flight state
- retain Qwen for open-jaw / multi-leg ambiguity
- do **not** wire preferences into search/deep-link (CQ47 forbidden)

## Included scope

- Squash-merge PR #53
- Protected AI runtime deploy of exact merge SHA
- Config parity verification
- Production in-process certification harness (cases 5–29)
- Browser UAT ≥15 turns on `https://jetpakistan.pk`
- Evidence + phase summary

## Excluded scope

- CQ47 preference execution wiring
- PERF reopen
- Qwen/runtime configuration changes
- iframe enablement
- Manual production file edits

## Deploy evidence

| Field | Value |
| --- | --- |
| PR53_MERGED | YES |
| MERGE_SHA | `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` |
| PREVIOUS_RUNTIME_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| DEPLOYED_RUNTIME_SHA | `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` |
| PROTECTED_AI_DEPLOY | PASS |
| APPLICATION_CODE_PARITY | PASS |
| Files deployed | `AiChatOrchestrator.php`, `ServerTravelSignals.php` |
| Backup | `/home/pkjetp/backups/jetpk-ai-runtime-20260929T125701Z` |
| Rollback | `c115cc5712ad0fca0ab9729d6d34907220959a93` |

## Certification result

**CQ46_PRODUCTION=PASS**

Authoritative in-proc: `docs/evidence/jp-ai-cq46-production/SUMMARY-inproc.json`  
Browser: `docs/evidence/jp-ai-cq46-production/09-browser.json`  
Matrix: `docs/evidence/jp-ai-cq46-production/11-final-matrix.md`

## Known observe-only residuals

Explicit name collisions (`My name is Morning/Fast/Best`) remain soft-preference owned — characterized, not fixed in this phase.

## Next optional phase

`CQ47_PREFERENCE_EXECUTION_WIRING`
