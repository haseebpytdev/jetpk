# CQ44-PERF-01 baseline

## Merge bookkeeping

| Item | Value |
| --- | --- |
| PR44_MERGED | YES |
| PR44_MERGE_SHA | `a599dab2392dca561f240c4273fd5e9d85044160` |
| APPLICATION_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` (unchanged; no deploy) |
| CQ43_PRIMARY_TURN_COUNT_RECONCILED | 42 |

## Certified performance baseline (CQ43 final soak)

| Metric | Value |
| --- | --- |
| SEMANTIC_P50/P95/MAX | 11185 / 22542 / 22542 |
| TOTAL_P50/P95/MAX | 8006 / 13150 / 22696 |
| TURNS_GT_20S / GT_30S | 1 / 0 |
| QWEN_MODEL_CALLS_TOTAL | 13 |
| Primary Qwen-required (SEMANTIC_BRAIN_CALLED) | 10 / 42 |

## ROOT_CAUSE_PRIMARY_LATENCY

`SemanticBrain::tryHandle` always called `QwenSemanticPlanner::plan()` for travel turns, including refinements where `ServerTravelSignals` + resolvers already had complete authority. Worst case: `"from Lahore"` after DXB → 22.5s `invalid_plan`/`open_jaw` then hybrid correctly asked date.
