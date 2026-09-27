# CQ43 FINAL — Production Long-Conversation Soak Baseline

Date: 2026-09-27  
Production: https://jetpakistan.pk/  
Branch: `work/jp-ai-cq43-final-production-soak`

## Start identity

| Item | Value |
|------|-------|
| START_SHA | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| PR #40 | MERGED |
| CQ43_R1_PRODUCTION_REUAT | PASS |
| CQ43_FULL_SOAK_GATE | READY |
| Scope | Evidence / harness only — **no application code changes** |

## Config (must remain)

- CONVERSATIONAL_ENABLED=true
- SEMANTIC_PLANNER_ENABLED=true
- SEMANTIC_COMPOSER_ENABLED=false
- AI_EMBED_ENABLED=false
- brain_enabled=true
- PERMANENT_QWEN=HOLD
- IFRAME_PILOT=HOLD

## Preserved prior evidence

`docs/evidence/jp-ai-cq43-r1-production-reuat/` (not rewritten).

## Safety

No supplier/booking/payment mutations. SEARCH_BEFORE_CONFIRMATION must stay 0. Affirmation/search execution skipped.
