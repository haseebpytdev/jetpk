# JP-AI-CQ42-R2-PROD-RESIDUALS — SUMMARY

## Phase name
JP-AI-CQ42-R2-PROD-RESIDUALS

## Branch name
`work/jp-ai-cq42-r2-prod-residuals`

## Objective
Close two confirmed CQ42 production residuals without starting CQ43: (1) GENERAL_KNOWLEDGE `invalid_json` rejects from forced JSON open-domain contract; (2) Roman-Urdu `wapis/wapas` single explicit route falsely becoming open-jaw.

## Included scope
- Plain-text Qwen contract for GENERAL_KNOWLEDGE / CASUAL / OUT_OF_DOMAIN_SAFE
- Optional semantic-planner bypass for those server categories (`GENERAL_MODEL_CALLS=1`)
- Server single-route authority + demotion of Qwen-invented reciprocal legs
- Canonicalizer: one_way/return must not inherit prior open-jaw legs
- Soft handoff phrasing excluded from terse GK classification
- Local PHPUnit regression (CQ41/CQ42/CQ42-R2/CQ28 semantic)
- Preserve `docs/evidence/jp-ai-cq42-production-closure/`

## Excluded scope
- Merge / deploy / production re-canary
- CQ43 long conversation
- Permanent Qwen / iframe pilot
- Composer / embed enablement
- CURRENT_UNVERIFIED JSON contract changes (kept fail-closed JSON)
- HIGH_RISK remains deterministic

## Investigation findings
- Production canary showed majority GK rejects as `invalid_json` while prose answers often existed.
- `"Dubai se Lahore wapis"` produced open-jaw clarify `DXB-LHE then LHE-DXB` because Qwen invented the reciprocal leg and the validator trusted Qwen legs when server open-jaw extract was null.
- Contaminated prior open-jaw shopping state could reappear via `TravelIntentCanonicalizer` merging prior `legs` when current `legs=[]`.

## Root causes
1. JSON-only open-domain parse for GENERAL_KNOWLEDGE on a small Qwen that frequently returns plain text / malformed JSON-looking blobs.
2. Missing server single-route constraint + prior-leg merge on demotion.

## Exact files changed
- `ai-assistant/prompts/assistant-open-domain-system.txt`
- `app/Services/Ai/AiConversationalAgent.php`
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/Semantic/SemanticPlanValidator.php`
- `app/Services/Ai/Semantic/QwenSemanticPlanner.php`
- `app/Services/Ai/Hybrid/LocationResolver.php`
- `app/Services/Ai/TravelIntentCanonicalizer.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/ConversationIntentRouter.php`
- `tests/Feature/Ai/Cq42R2ProdResidualsTest.php` (new)
- `tests/Feature/Ai/QwenOpenDomainAuthorityCq42Test.php`
- `tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php`
- `docs/evidence/jp-ai-cq42-r2-prod-residuals/`
- `docs/evidence/jp-ai-cq42-production-closure/` (preserved local pack)
- `docs/phases/JP-AI-CQ42-R2-PROD-RESIDUALS-SUMMARY.md`

## Routes / database
None.

## Backend changes
As above — category-specific open-domain response format; planner bypass; route demotion.

## Frontend changes
None.

## Tests executed
```
php vendor/bin/phpunit --filter "Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticBrain28"
```
Assertion count: 554. Result: 53 passed / 0 failed.

## Known limitations
- Real Qwen not exercised in this loop; production certification pending post-merge deploy.
- Contaminated-state chat may take hybrid pending-correction path; still must not open-jaw (asserted on body).

## Risks
- Over-broad handoff detector false positives (mitigated to soft team/handle phrasing).
- Planner bypass must keep booking/handoff/CURRENT/HIGH_RISK off the plain path (covered by detectors + classifier).

## Rollback
Revert this branch / revert merge commit; restore prior open-domain JSON-only prompt and validator behavior.

## Final status
CQ42_R2_LOCAL_CLOSURE=PASS (scripted)  
READY_FOR_REVIEW=YES  
READY_FOR_MERGE=NO  
READY_FOR_DEPLOY=NO  
PRODUCTION_VERIFIED=NO  
