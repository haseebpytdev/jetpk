# JP-AI-CQ43-FINAL-CLOSURE-SUMMARY

## Phase

CQ43-R2 / R2.1 merge, exact-SHA production deploy, focused browser/API closure, final re-soak.

## Branch / PR

| Item | Value |
| --- | --- |
| Application PR | #42 (merged) |
| Reviewed head | `d1f72ee565afc81285420fd9c0e1d8f22e708f83` |
| MERGE_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| Evidence branch | `work/jp-ai-cq43-r2-production-closure-evidence` |
| Evidence paths | `docs/evidence/jp-ai-cq43-r2-production-closure/`, `docs/evidence/jp-ai-cq43-final-resoak/` |

## Objective

Close the production widget omit blocker (message_id identity), prove lead/prompt-order non-regression on production, complete final CQ43 long-conversation soak, leave permanent Qwen/iframe decisions to owner.

## Included

- Pre-merge verify + merge PR #42
- Exact-SHA deploy AI runtime + Next frontend
- Focused in-proc + production browser closure
- Final 41-turn primary re-soak + rate-limit probe
- Evidence-only documentation

## Excluded

- Application code changes in this loop
- Permanent Qwen enablement
- Iframe enablement
- Supplier/payment mutations
- Performance architecture changes

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| DEPLOYED_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| DEPLOY_MARKER | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| APPLICATION_CODE_PARITY | PASS |

## Config

Conversational + semantic planner ON; composer OFF; embed/iframe OFF; `brain_enabled=true`.

## Focused production closure

| Gate | Result |
| --- | --- |
| SAME_BODY_DISTINCT_IDS_BROWSER | PASS (IDs 6196 ≠ 6202, identical confirm body, 3 bubbles) |
| SERVER_200_WIDGET_OMIT_COUNT | 0 |
| CLIENT_PARSE_ERROR_COUNT | 0 |
| POST_POLL_SAME_ID_DUPLICATES | 0 |
| DESTINATION_LED / ORIGIN_ONLY / ROUTE_COMPLETE | PASS |
| Bare name / contact / Closure29 / explicit name+travel | PASS |
| SHORT_CONTINUITY_SMOKE | PASS |
| CQ43_R2_PRODUCTION_CLOSURE | PASS |

## Final re-soak

| Gate | Result |
| --- | --- |
| PRIMARY_LONG_SESSION_TURNS | 41 |
| State continuity / detours / handoff | PASS |
| BROWSER_TURNS | 15 (≥ PASS continuity |
| RATE_LIMIT_GRACEFUL | PASS via assertRateLimit probe FIRST=31 |
| Safety counters | all 0 |
| CQ43_LONG_CONVERSATION_GATE | PASS |

## Performance (owner review still pending)

| Metric | Value |
| --- | --- |
| SEMANTIC_P50/P95/MAX | 11185 / 22542 / 22542 |
| OPEN_DOMAIN_P50/P95/MAX | 6815 / 6815 / 6815 |
| TOTAL_P50/P95/MAX | 8006 / 13150 / 22696 |
| TURNS_OVER_20S / 30S | 1 / 0 |

Improved vs prior baseline (SEMANTIC_P95 35336 / TOTAL_MAX 43240). No architecture change.

## Owner decisions

| Decision | Status |
| --- | --- |
| PERMANENT_QWEN_OWNER_DECISION | READY_FOR_REVIEW |
| PERFORMANCE_OWNER_REVIEW_PENDING | YES |
| IFRAME_PILOT | HOLD |

## Residuals

- Full-chat LAB burst may not trip 30/min due to 60s decay when turns are slow (probe proves limiter).
- Performance owner review still required before calling Qwen permanently enabled.

## Rollback

Redeploy previous application SHA `e5640c103b40c90a6a34e5f668ccf0865ffbc391` via protected AI + frontend release path; restore matching deploy marker.

## Final status

`CQ43_R2_PRODUCTION_CLOSURE=PASS`  
`CQ43_LONG_CONVERSATION_GATE=PASS`  
No permanent Qwen config change. No iframe enablement.
