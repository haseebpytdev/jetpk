# CQ44 — Permanent Qwen owner review baseline

## Scope

Owner performance/permanency review only. Not a correctness-fix loop.

## Verified state

| Item | Value |
| --- | --- |
| Application runtime | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| APPLICATION_CODE_PARITY | PASS |
| PR #42 | MERGED (application) |
| PR #43 | MERGED (evidence/docs) → `5c06c3facfa2ce3b88e114024bdec41fb5ce0d45` |
| CURRENT_MAIN_SHA | `5c06c3facfa2ce3b88e114024bdec41fb5ce0d45` |
| APPLICATION_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| Note | Evidence merge does **not** change application runtime |

## CQ43 closure inputs

| Gate | Result |
| --- | --- |
| CQ43_R2_PRODUCTION_CLOSURE | PASS |
| CQ43_LONG_CONVERSATION_GATE | PASS |
| PERMANENT_QWEN_OWNER_DECISION (pre-CQ44) | READY_FOR_REVIEW |
| IFRAME_PILOT | HOLD |

## Production AI config (unchanged this phase)

```
CONVERSATIONAL_ENABLED=true
SEMANTIC_PLANNER_ENABLED=true
SEMANTIC_COMPOSER_ENABLED=false
AI_EMBED_ENABLED=false
brain_enabled=true
```

No separate `permanent_qwen` setting exists in repo config. Permanency is a product designation over the already-enabled semantic planner.

## OBSERVED_FINAL_SOAK_PERFORMANCE (CQ43 certified)

Source: `docs/evidence/jp-ai-cq43-final-resoak/out/`

| Metric | Value |
| --- | --- |
| SEMANTIC_P50/P95/MAX | 11185 / 22542 / 22542 |
| OPEN_DOMAIN_P50/P95/MAX | 6815 / 6815 / 6815 |
| TOTAL_P50/P95/MAX | 8006 / 13150 / 22696 |
| TURNS_OVER_20S / 30S | 1 / 0 |

Prior soak observation (not a controlled A/B): SEMANTIC_P95=35336, TOTAL_MAX=43240. Do not claim causal improvement.
