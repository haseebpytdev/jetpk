# CQ46 baseline — ranking/time lead-authority diagnostic

| Field | Value |
| --- | --- |
| Branch | `work/jp-ai-cq46-ranking-time-authority-diagnostic` |
| MAIN_SHA | `15ce02cc7b79e8dc14d32ec7f271039ee4e5cfa7` |
| APPLICATION_RUNTIME_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| CQ45 | PRODUCTION_CERTIFIED |
| Scope | DIAGNOSTIC ONLY — tests/docs/harness |
| Application changes | **NONE** |
| Deploy | **NONE** |

## Confirmed residual

With active simple flight + pending confirmation + lead name stage:

- `cheapest` → `lead_name=cheapest`, stage=`contact`
- `fastest` → `lead_name=fastest`, stage=`contact`
- `morning` → `lead_name=morning`, stage=`contact`

`CQ46_FALSE_LEAD_BASELINE=CONFIRMED`

## Non-goals

- No CQ46 implementation
- No ranking/time snapshot extension yet
- No PERF reopen
- No Qwen/runtime/iframe changes
