# CQ44-PERF-02 — baseline

## Merge / runtime

| Field | Value |
|---|---|
| PR46_MERGED | YES |
| PR46_MERGE_SHA | `b2cb09df44dd089fe1cf1c2c812cbad7ff896c3e` |
| MAIN_SHA_AFTER_PR46 | `b2cb09df44dd089fe1cf1c2c812cbad7ff896c3e` |
| APPLICATION_RUNTIME_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| APPLICATION_CODE_PARITY | PASS |
| APPLICATION_CODE_CHANGED | NO |
| DEPLOY_OCCURRED | NO |
| IFRAME_PILOT | HOLD |
| PERMANENT_QWEN_OWNER_DECISION | APPROVED |

## PERF-01 production reference (unchanged)

| Metric | Before | After |
|---|---|---|
| QWEN_CALL_RATE | 10/41 | 5/41 |
| TOTAL_P95_MS | 12199 | 6885 |

## This phase

Diagnostic-only. Evidence + harness under `docs/evidence/jp-ai-cq44-perf-02-tail-diagnostic/`.
No application/runtime/config changes. No iframe. No supplier search.
