# JP-AI-CQ44-PERMANENT-QWEN-OWNER-REVIEW-SUMMARY

## Phase

CQ44 — Performance owner review & permanent Qwen decision.

## Branch / PR

| Item | Value |
| --- | --- |
| Branch | `work/jp-ai-cq44-permanent-qwen-owner-review` |
| Base | `main` @ `5c06c3fa` (post PR #43 evidence merge) |
| Evidence | `docs/evidence/jp-ai-cq44-permanent-qwen-owner-review/` |
| Application changes | NONE |
| Deploy | NONE |

## CQ43 formal close

| Item | Value |
| --- | --- |
| PR43_MERGED | YES |
| PR43_MERGE_SHA | `5c06c3facfa2ce3b88e114024bdec41fb5ce0d45` |
| APPLICATION_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| APPLICATION_CODE_PARITY | PASS (docs-only main tip vs app runtime) |
| CQ43_FORMALLY_CLOSED | YES |

## Certification

CORRECTNESS / SAFETY / LONG_CONVERSATION / BROWSER_CONTINUITY / RATE_LIMIT / MODEL_STABILITY = YES.

## Observed final soak performance

| Metric | Value |
| --- | --- |
| SEMANTIC_P50/P95/MAX | 11185 / 22542 / 22542 |
| OPEN_DOMAIN_P50/P95/MAX | 6815 / 6815 / 6815 |
| TOTAL_P50/P95/MAX | 8006 / 13150 / 22696 |
| Primary bands LT5 / 5–10 / 10–20 / GT20 | 32 / 4 / 5 / 1 |
| Qwen-required turns | 10 (p50≈11.3s) |
| Deterministic fast turns | 32 (p50≈28ms) |

FORMAL_AI_LATENCY_SLA=NONE.

## Owner decision

```
PERMANENT_QWEN_OWNER_DECISION=APPROVED
PERMANENT_QWEN_CONFIG_CHANGE_REQUIRED=NO
CQ44_PERFORMANCE_TRACK_REQUIRED=YES
IFRAME_PILOT=HOLD
```

Reason: certified correctness/safety/continuity with model-dominated but operationally acceptable latency (no 30s turns; UX busy state proven). Keep Qwen as permanent semantic brain; optimize latency in CQ44-PERF without reopening CQ43. No config redeploy required — planner already ON.

## Explicit non-actions

- No application patch
- No production config change
- No redeploy
- No iframe enablement
- No routing redesign
