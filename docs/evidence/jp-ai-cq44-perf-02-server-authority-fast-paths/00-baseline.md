# CQ44-PERF-02 — server authority fast paths baseline

| Field | Value |
|---|---|
| PR47_EVIDENCE_SCOPE_LABELS_FIXED | YES |
| PR47_MERGED | YES |
| PR47_MERGE_SHA | d13411923ea75ba3a359d89946a56d45c550fbb2 |
| MAIN_SHA_AFTER_PR47 | d13411923ea75ba3a359d89946a56d45c550fbb2 |
| APPLICATION_RUNTIME_SHA | a0e616747a95d632f270a6edafc3f6e8d9230fca (unchanged; no deploy) |
| CQ44_PERF_02_DIAGNOSTIC | PASS |
| Branch | work/jp-ai-cq44-perf-02-server-authority-fast-paths |

## Root causes addressed

| Wait | Root cause |
|---|---|
| CURRENT | `CURRENT_UNVERIFIED` classified server-side but still entered Qwen planner; `generalAnswer(plan=null)` fell back instead of refusing |
| Explicit A→B | `deterministicAuthorityComplete` returned `no_active_travel` / `explicit_route_keep_qwen` even when OD+current-turn date were fully resolved |

## Non-goals (held)

- No model/quant/prompt/timeout/iframe changes
- No classifier expansion for gold/FX
- Explicit return-route fast path NOT enabled
- `direct only` lead residual OPEN_SEPARATE_TRACK
