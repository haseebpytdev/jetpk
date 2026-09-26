# CQ42-R3 Cross-Path Travel Authority — local evidence

START_SHA=eee09d61b16ad8c08fae7d25db7db219252bd854
(base production + R2 evidence)

## Combined gate
```
php vendor/bin/phpunit --filter "Cq42R3CrossPathTravelAuthority|Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticFallbackOrder39|QwenSemanticBrain28"
```

TESTS_TOTAL=72
TESTS_PASS=72
TESTS_FAIL=0
ASSERTIONS=745

## Closed residuals
1. Qwen support/handoff cannot hijack SERVER_EXPLICIT_TRAVEL_ROUTE → hybrid fallback
2. HELP-FIRST uses ServerTravelSignals / LocationResolver aliases (dubay/lahor)
3. Shared English return cue on Hybrid + Semantic; invalid_json → return clarify
4. Pair-aware DateExpressionResolver trip dates; dated return confirmation
5. GK empty_message: one bounded retry (GENERAL_MODEL_CALLS max 2)

## Status
CQ42_R3_LOCAL_CLOSURE=PASS
READY_FOR_MERGE=NO
READY_FOR_DEPLOY=NO
PRODUCTION_VERIFIED=NO
