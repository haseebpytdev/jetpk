# PERF-01 performance results

## What changed

Deterministic refinements no longer invoke Qwen. Hybrid remains authoritative.

## Call reduction

| Metric | Before (CQ43 primary) | After (PERF-01) |
| --- | --- | --- |
| Unnecessary refinement Qwen calls | 5 (turns 2–6) | 0 (bypassed) |
| Essential planner/open-domain classes | 5 | remain active |
| Feature proof | — | destination-led = 1 inference; 8 refinements = 0 |

## Latency

| Metric | Value |
| --- | --- |
| Deterministic bypass total latency | Hybrid path (tens of ms class in CQ43 deterministic turns) |
| Real-Qwen SEMANTIC_P95 after | N/A until authorized re-soak |
| Projected removal of sole >20s turn | `"from Lahore"` no longer waits on invalid_plan |

## Essential controls

| Control | Result |
| --- | --- |
| GENERAL_KNOWLEDGE_QWEN | PASS (path remains) |
| CURRENT_UNVERIFIED_QWEN | PASS |
| AMBIGUOUS_SEMANTIC_TRAVEL_QWEN | PASS (dest-led / explicit route kept) |
| OPEN_JAW_NON_REGRESSION | PASS (multi-leg still calls planner) |
