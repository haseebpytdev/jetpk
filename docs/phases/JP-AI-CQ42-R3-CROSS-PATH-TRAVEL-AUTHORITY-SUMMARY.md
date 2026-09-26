# JP-AI-CQ42-R3-CROSS-PATH-TRAVEL-AUTHORITY — SUMMARY

## Phase name
JP-AI-CQ42-R3-CROSS-PATH-TRAVEL-AUTHORITY

## Branch name
`work/jp-ai-cq42-r3-cross-path-travel-authority`

## Objective
Close four CQ42-R2 production-blocking residuals: Qwen support hijack of wapas, HELP-FIRST alias lead capture, hybrid English return on semantic invalid_json, and dated return extraction — without merge/deploy/CQ43.

## Included scope
- Shared `ServerTravelSignals` (explicit route, English return cue, pair-aware trip dates)
- SemanticBrain: server explicit travel route → controlled hybrid fallback (no Qwen support/booking/general hijack)
- HELP-FIRST: master-data route signal for aliases (`dubay`/`lahor`)
- HybridTravelPipeline: shared return cue + return_date required clarify; dated return confirmation
- SemanticPlanValidator: user-message dates outrank Qwen omission
- Orchestrator: return trip cannot confirm without return_date; LEAD_CAPTURE_OVERRIDDEN telemetry
- Optional GK `empty_message` single retry
- Dedicated R3 feature tests + combined regression

## Excluded scope
- Merge / deploy / production re-canary
- CQ43 long conversation
- Composer / iframe enablement
- Supplier / booking mutation changes

## Root causes
1. Route authority was not dispatch authority — Qwen `support/handoff` still won after `SERVER_SINGLE_ROUTE`.
2. HELP-FIRST used hard-coded city spellings, missing LocationResolver aliases.
3. `EXPLICIT_RETURN_TRIP_CUE` lived only in semantic validation; Hybrid ignored it on fallback.
4. Semantic validator trusted Qwen `return_date=null` over explicit user “return 15 October”.

## Exact files changed
- `app/Services/Ai/Hybrid/ServerTravelSignals.php` (new)
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/Semantic/SemanticPlanValidator.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/ConversationIntentRouter.php`
- `app/Services/Ai/AiChatOrchestrator.php`
- `app/Services/Ai/AiConversationalAgent.php`
- `app/Data/Ai/HybridParseResult.php`
- `tests/Feature/Ai/Cq42R3CrossPathTravelAuthorityTest.php` (new)
- `tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php` (retry-aware latency assert)
- `docs/evidence/jp-ai-cq42-r3-cross-path-travel-authority/`
- `docs/phases/JP-AI-CQ42-R3-CROSS-PATH-TRAVEL-AUTHORITY-SUMMARY.md`

## Tests executed
```
php vendor/bin/phpunit --filter "Cq42R3CrossPathTravelAuthority|Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticFallbackOrder39|QwenSemanticBrain28"
```
72 passed / 0 failed / 745 assertions.

## Final status
READY_FOR_REVIEW=YES
READY_FOR_MERGE=NO
READY_FOR_DEPLOY=NO
PRODUCTION_VERIFIED=NO
CQ43_LONG_CONVERSATION_GATE=NOT_READY
