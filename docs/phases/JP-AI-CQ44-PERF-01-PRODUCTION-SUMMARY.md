# JP-AI-CQ44-PERF-01-PRODUCTION-SUMMARY

## Status

`CQ44_PERF_01_2_STATUS=PASS` (evidence reconciliation)

Application runtime unchanged: `a0e616747a95d632f270a6edafc3f6e8d9230fca`  
No app code changes. No redeploy.

## RUN1 vs RUN2

| | RUN1 | RUN2 (authoritative) |
| --- | --- | --- |
| Resume AI after handoff | **0 (flawed)** | **1** |
| Session records | 41 | 42 |
| User messages | 41 | 41 |
| Qwen calls | 4 | 5 |
| User-message rate | 4/41 (noncomparable post-handoff) | **5/41** |
| Post-handoff state | stuck WAITING_FOR_HUMAN | AI_ACTIVE after resume |

## Comparable rates

- Baseline CQ43: **10/41** user-message Qwen rate (42 session records incl. 1 Resume)
- AFTER RUN2: **5/41**
- Do not use 10/42 as the primary normalized rate

## Percentiles

`PERCENTILE_METHOD=nearest-rank: rank=ceil(p/100*N), 1-indexed a[rank-1]`

User-message totals (Resume excluded):

| | Before | After RUN2 |
| --- | ---: | ---: |
| Total p50 | 31 | 40 |
| Total p95 | 12199 | 6885 |
| Total max | 22697 | 27176 |
| Semantic N | 8 | 3 |
| Semantic p95 | 22542 | 21826 (=max; small N) |

PERF-01 win = fewer Qwen calls, not faster individual Qwen inference.

## Other

- FROM_LAHORE tax removed: PASS
- QWEN_MODEL_ONLY_HANDOFF RUN1=2 was harness bug; RUN2=0
- `direct only` → lead_name residual: pre-existing in CQ43; not PERF regression
- Explicit-route ~36s invalid_json outlier: PERF-02 candidate (control scope)
