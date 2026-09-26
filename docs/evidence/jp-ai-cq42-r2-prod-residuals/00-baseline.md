# CQ42-R2 production residuals — local evidence

## Scope
- GENERAL_KNOWLEDGE plain-text Qwen contract + optional planner bypass (`GENERAL_MODEL_CALLS=1`)
- Server-authoritative single-route demotion for `X se Y wapis/wapas` (no invented reciprocal open-jaw leg)

## Baseline (production CQ42)
See preserved pack: `docs/evidence/jp-ai-cq42-production-closure/`

Production GK fail mode: `OPEN_DOMAIN_REJECT_REASON=invalid_json` (JSON wrapper vs small Qwen prose).
Production wapis: open-jaw `DXB-LHE then LHE-DXB`.

## Root causes
1. **invalid_json** — `tryOpenDomainRespond` required `{"message":...}`; malformed/`{`-prefixed prose → reject.
2. **false open-jaw** — `SemanticPlanValidator` trusted Qwen multi-leg when server extracted only one explicit route; prior open-jaw legs could re-merge via canonicalizer.

## Fixes (this branch)
- Plain-text accept path for GK/CASUAL/OUT_OF_DOMAIN_SAFE; JSON retained for CURRENT_UNVERIFIED.
- SemanticBrain early bypass when server classifies those categories (booking/handoff detectors still win).
- `extractRoute` + validator demotion + canonicalizer clears prior legs on one_way/return.
- Soft handoff phrasing excluded from terse GK classifier; `detectHandoff` expanded.

## Local PHPUnit
```
php vendor/bin/phpunit --filter "Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticBrain28"
→ 53 passed, 554 assertions
```

## Real Qwen
Not run in this loop (local lab not asserted available). Scripted certification only. Production re-canary deferred (`PRODUCTION_VERIFIED=NO`).

## Gates
READY_FOR_MERGE=NO  
READY_FOR_DEPLOY=NO  
CQ43=NOT_READY  
PERMANENT_QWEN=HOLD  
IFRAME_PILOT=HOLD  
