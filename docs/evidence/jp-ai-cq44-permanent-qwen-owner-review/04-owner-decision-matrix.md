# CQ44 — Owner decision matrix

Question: Is the production system sufficiently correct, safe, stable, and operationally usable to keep Qwen as the permanent semantic brain while performance optimization continues separately?

## Certification checklist

| Criterion | Result |
| --- | --- |
| CORRECTNESS_CERTIFIED | YES |
| SAFETY_CERTIFIED | YES |
| LONG_CONVERSATION_CERTIFIED | YES |
| BROWSER_CONTINUITY_CERTIFIED | YES |
| RATE_LIMIT_CERTIFIED | YES |
| MODEL_STABILITY_CERTIFIED | YES (0 crashes, 0 OOM, 0 legacy LLM after semantic travel fallback) |
| NO_30S_TURNS | YES |
| TOTAL_P95_WITHIN_CURRENT_OPERATIONAL_TOLERANCE | YES (13150ms; no formal SLA) |
| SEMANTIC_P95_WITHIN_CURRENT_OPERATIONAL_TOLERANCE | ACCEPTABLE_WITH_FOLLOWUP (22542ms; model-bound) |
| FORMAL_AI_LATENCY_SLA | NONE |

## Decision matrix

| Dimension | Rating |
| --- | --- |
| Correctness | PASS |
| Safety | PASS |
| State continuity | PASS |
| Browser reliability | PASS |
| Rate limiting | PASS |
| Model stability | PASS |
| Performance | ACCEPTABLE_WITH_FOLLOWUP |
| Operational risk | ACCEPTABLE_WITH_FOLLOWUP |
| Rollback readiness | PASS (prior app SHA `e5640c10` available; planner flag can be disabled without iframe/composer expansion) |

## Config semantics

| Item | Value |
| --- | --- |
| PERMANENT_QWEN_CONFIG_CHANGE_REQUIRED | NO |
| Current planner | already ON in production |
| Redeploy for designation | NO |
| Composer / iframe | remain OFF / HOLD |

## Decision

```
PERMANENT_QWEN_OWNER_DECISION=APPROVED
CQ44_PERFORMANCE_TRACK_REQUIRED=YES
CQ43_FORMALLY_CLOSED=YES
IFRAME_PILOT=HOLD
```

### OWNER_DECISION_REASON

CQ43 closed correctness, safety, long-session continuity, browser identity/render, and rate-limit certification. Qwen is already the live semantic planner; no config toggle is required to designate it permanent. Observed latency is model-dominated (~99% of Qwen-turn total), with one >20s fallback turn and zero >30s turns. Deterministic hybrid turns stay sub-130ms, and UX busy/thinking states already cover wait time. Latency is therefore accepted for continued production use **with** an explicit separate CQ44-PERF optimization track — not treated as a reason to reopen CQ43 or disable the brain.
