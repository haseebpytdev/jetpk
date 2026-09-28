# CQ44-PERF — proposed next phase (not started)

## Objective

Reduce semantic latency without changing CQ43-certified behavior.

## Why separate

Permanent Qwen is approved on correctness/safety/usability grounds. Performance remains an open quality track, not a correctness reopen.

## Evidence-backed targets (proposals only)

| Target | Rationale |
| --- | --- |
| Cut deterministic-path Qwen invocations | 32/42 primary turns already prove hybrid can answer without model |
| Drive SEMANTIC_P95 toward ≤10–12s band | Current SEMANTIC_P95=22542 is the main UX pain; TOTAL_P95 already 13.1s due to hybrid mix |
| Eliminate multi-second `invalid_plan` waits when hybrid clarify is available | Sole primary >20s turn |
| Keep TURNS_OVER_30S=0 | Current floor must not regress |

Do **not** silently impose these as SLAs until product adopts a formal AI latency SLA (`FORMAL_AI_LATENCY_SLA=NONE` today).

## Out of scope for CQ44-PERF unless separately authorized

- Iframe / embed pilot
- Composer enablement
- Routing redesign
- Supplier/search behavior changes
- Permanent planner disable

## Entry condition

Owner accepts CQ44 decision PR; no application work in CQ44 owner-review PR.
