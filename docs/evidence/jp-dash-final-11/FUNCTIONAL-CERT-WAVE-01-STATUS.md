# JP-DASH-FINAL-11 Functional Certification Wave 01

**Checkpoint:** 2026-10-10 Wave-01 auto-passive batches + form-validation pilot (in progress)

## Baseline SHAs

| Gate | Value |
|------|--------|
| `jetpk/main` | `ab26661f0107d9bd8f9889dda1d59567d93be683` |
| Production runtime | `ab26661f0107d9bd8f9889dda1d59567d93be683` |
| Dashboard `BUILD_ID` | `6ebAnRkxe40Lkq_GJwV5G` |

## Lock / writers

- PID 27468: **not running**; `tmp/jp-functional-cert-matrix.lock`: **absent**
- `LOCK_RESOLUTION=STALE_LOCK_CLEARED`
- `MATRIX_CONCURRENT_WRITERS=0`

## Auth

- `AUTH_STATES_PASS=13` / `AUTH_STATES_FAIL=0` (`probe-auth-states.mjs`)
- `QA_AUTH_PASSWORD_SYNCS=9` recorded in `MUTATION-MATRIX.csv` (`MUT-SETUP-001` … `009`)
- `AUTH_FAILURE_CAN_CREATE_FUNCTIONAL_FAIL=NO` (runner preflight)

## Classification (semantic v2 applied)

| Class | Cases |
|-------|------:|
| READ_OPERATION | 5623 |
| NAVIGATION | 8230 |
| SEARCH_FILTER_SORT | 208 |
| PAGINATION | 46 |
| FORM_VALIDATION | 634 |
| DOWNLOAD_EXPORT | 23 |
| QA_REVERSIBLE_MUTATION | 234 |
| QA_CREATE_DELETE_LIFECYCLE | 118 |
| EXTERNAL_ACTION_GATED | 38 |
| READ_ONLY_BY_DESIGN | 1 |
| **CLASSIFICATION_TOTAL** | **15155** |
| **PASSIVE_LIGHT_CASES_TOTAL** | **14780** |

Reconciliation: `FUNCTIONAL-CLASSIFICATION-RECONCILIATION.csv` (**585** semantic fixes; **173** results reset on class change).

**Note:** A mistaken re-run of `reconcile-functional-results.mjs` with a stale contaminated-identity list cleared ~1.9k results; script now treats all **13** identities as valid. Recovery via foreground **30-group** batch (+ prior retained rows).

## Terminal accounting (Phase 1)

`15155 = PASS + NEGATIVE_RBAC_PASS + QA_MUTATION_PASS + READ_ONLY_BY_DESIGN + EXTERNAL_ACTION_GATED + NOT_APPLICABLE + FAIL + UNTESTED`

The prior **1813 tested / 1811 PASS** gap is **`2 × EXTERNAL_ACTION_GATED`** (`FC-004795`, `FC-006203` Submit deposit). **`UNACCOUNTED_TERMINAL_CASES=0`**.

## Auto-passive vs form split (Phase 2)

| Bucket | Total | Tested | Untested |
|--------|------:|-------:|---------:|
| `AUTO_PASSIVE_CASES` | 14097 | 4614 | 9483 |
| `FORM_VALIDATION` | 678 | (pilot only) | 632+ |
| `DEEP_CASES` (download/QA mutation) | 380 | 65 legacy | not this wave |

Runner: `run-functional-cert-wave.mjs` (`AUTO_PASSIVE_ONLY=YES`). Form: `run-form-validation-cert.mjs` (pilot `JP_FORM_VALIDATION_PILOT=25`).

## Execution coverage (latest summarize)

| Metric | Value |
|--------|------:|
| `FUNCTIONAL_CASES_TESTED_VALID` | see `FUNCTIONAL-CERT-COVERAGE.json` |
| `FUNCTIONAL_CASES_FAIL_CANDIDATES` | 0 after triage |
| `COVERAGE_ACCOUNTING` | PASS |

## Fail triage

- `FUNCTIONAL-FAIL-TRIAGE.csv` — **64** initial + **22** post-batch; **0** untriaged
- `CONFIRMED_APPLICATION_DEFECTS=0` — **no repair wave stop**
- Tooling: skip-link click timeout → **presence probe** in runner (`functional-cert-nav` pattern)
- `FORM_VALIDATION`: deferred in wave runner until real validation evidence phase; `WEAK_FORM_VALIDATION_PASSES=0`

## Runner safety

- `JP_FUNCTIONAL_CERT_BATCH_GROUPS=30` only; rejects `JP_FUNCTIONAL_CERT_MAX_GROUPS`
- Batch metrics: `BATCH_START_GROUP`, `BATCH_END_GROUP`, `BATCH_GROUP_COUNT`, `BATCH_CASES_*`
- `ATOMIC_CHECKPOINT_SAVE=YES`

## Status

- `LIVE_ALL_PORTALS_CERTIFIED=NO`
- `FINAL_STATUS=IN_PROGRESS`
- `RUNTIME_CODE_CHANGED=NO` (Laravel); cert scripts updated only
- `PRODUCTION_DEPLOY_REQUIRED=NO`
