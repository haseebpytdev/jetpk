# JP-AI-CQ41 Live UAT Residual Closure — Evidence

## Identity

| Field | Value |
|-------|-------|
| START_SHA | `437904f46bdbd471b40d211532ab893b598e0616` |
| BRANCH | `work/jp-ai-cq41-live-uat-residual-closure` |
| BASE | `jetpk/main` = Order-39 / PR #30 |
| DATE | 2026-09-26 |

## Residuals closed (engineering)

| # | Residual | Root cause | Fix |
|---|----------|------------|-----|
| 1 | `me and my wife` → adults=1 | No relational pair patterns in `PassengerExpressionResolver`; Qwen adults=1 survived validate | Relational pair inference + validator adults normalize; explicit `\d+ adults` remains higher precedence |
| 2 | Open-jaw second leg rewritten (JED→MED) | Prompt alone insufficient; `TravelIntentCanonicalizer` dropped `legs`/`trip_type` so travelPath fell back to wrong plan legs; LocationResolver required `from` | Explicit `A to B then C to D` extract; validator prefers explicit legs; canonicalizer preserves legs/trip_type |
| 3 | Photosynthesis → JP knowledge miss | Qwen domain=`knowledge` → empty RAG | SemanticBrain re-routes GENERAL_KNOWLEDGE via `OpenDomainResponseService`; current/live stays gated |
| 4 | Check my booking → handoff | Semantic match evaluated support/handoff before booking | Booking detector precedence in SemanticBrain + Hybrid booking-before-handoff |
| 5 | Inconsistent thinking UI | Indicator only dots; no delay; easy to miss on mid-latency | 280ms delayed `showThinking` + visible label; clears in `finally` |

## Tests

Core gate (parent re-run):

```
php artisan test tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php \
  tests/Feature/Ai/QwenSemanticFallbackOrder39Test.php \
  tests/Feature/Ai/QwenSemanticBrain28Test.php \
  tests/Unit/Ai/HybridTravelPipelineTest.php \
  tests/Unit/Ai/Cq41OpenJawExtractSmokeTest.php
```

Result: **49 passed**, 299 assertions.

Broader AI filter: 126/129 passed; 3 failures unrelated (missing hybrid corpus fixtures; unrelated admin BookingLookup overview HTML).

## Real Qwen local certification

ENDPOINT `http://127.0.0.1:3921` — **UNAVAILABLE** this run (`Unable to connect`).

| Metric | Value |
|--------|-------|
| REAL_QWEN_RUNS | 0 |
| QWEN_SEMANTIC_SUCCESS | N/A |
| SAFE_HYBRID_FALLBACKS | N/A |
| CQ41_P50_MS | N/A (not measured — no live model) |
| CQ41_P95_MS | N/A |
| CQ41_MAX_MS | N/A |

Scripted Order-39 + residual matrix remains the local correctness gate. Live canary after deploy is still required.

## Safety counters (scripted)

| Counter | Result |
|---------|--------|
| SEARCH_BEFORE_CONFIRMATION | 0 |
| PII_FIRST (travel confirm paths) | 0 |
| WRONG_ROUTE_ACTION_READY | 0 |
| BOOKING_IDENTITY_BYPASS | 0 |
| BOOKING_DATA_LEAK | 0 |
| LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK | 0 |
| HTTP_500 | 0 |

## Grok

- Planner: READY_WITH_GIT_GATE → implemented
- Verifier: **PARTIAL** (code review PASS for residuals 1–4; residual 5 CODE_ONLY; live Qwen missing; verifier did not re-exec PHPUnit)

## Production

PRODUCTION_VERIFIED=NO  
PERMANENT_QWEN=HOLD  
IFRAME_PILOT=HOLD  
READY_FOR_DEPLOY=NO
