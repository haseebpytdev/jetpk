# CQ42-R2.1 English return cue — local evidence

PREVIOUS_HEAD=1fa679bccdb4a607f4568cb633707912f9262230

## Gate
```
php vendor/bin/phpunit --filter "Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticBrain28"
```

LOCAL_TESTS_TOTAL=57
LOCAL_TESTS_PASS=57
LOCAL_TESTS_FAIL=0
ASSERTIONS=624

## Semantics
- EXPLICIT_RETURN_TRIP_CUE: English return / round-trip (not wapis/wapas)
- False open-jaw demotion: return if cue OR return_date else one_way
- Server rebuilds travel completeness missing[] (ignores stale Qwen slots)
- return without return_date → clarify, RETURN_DATE_REQUIRED, no confirmation/search
- Prior shopping return_date does not satisfy a fresh English return cue without a stated date
- Intent exposed on semantic chat payload

## Status
LOCAL_GATE=PASS
READY_FOR_MERGE=NO
READY_FOR_DEPLOY=NO
PRODUCTION_VERIFIED=NO
