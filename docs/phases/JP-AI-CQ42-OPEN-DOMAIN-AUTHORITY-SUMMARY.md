# JP-AI-CQ42-OPEN-DOMAIN-AUTHORITY-SUMMARY

## Phase
JP-AI-CQ42-OPEN-DOMAIN-AUTHORITY

## Branch
`work/jp-ai-cq42-open-domain-authority`

## Objective
Make server `classifyOpenDomain` authoritative over Qwen booking/support/current mislabels; fix GENERAL_KNOWLEDGE Qwen prompt contract; replace weather-hardcoded CURRENT wording with topic-aware limitations. Preserve Order-39 travel and booking/handoff non-regression.

## Included
- SemanticBrain server-first open-domain precedence
- HybridTravelPipeline `detectHandoff` public API
- ConversationIntentRouter CURRENT pattern expansion + `classifyCurrentTopic`
- AiConversationalAgent open-domain identity/policy + rejection diagnostics + travel-refusal reject
- assistant-open-domain-system.txt GK contract
- OpenDomainResponseService topic-aware CURRENT + transparent GK fallback
- QwenOpenDomainAuthorityCq42Test adversarial suite
- Preserve local CQ41 production-closure evidence tree

## Excluded
- Semantic composer enablement
- Iframe/embed enablement
- Production merge/deploy
- Thinking UI redesign

## Investigation findings
CQ41 live PARTIAL: BTC→booking, news→weather template, gravity→travel refusal / OPEN_DOMAIN_QWEN_SUCCESS=0. Root causes as above.

## Exact files changed
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/ConversationIntentRouter.php`
- `app/Services/Ai/AiConversationalAgent.php`
- `app/Services/Ai/OpenDomainResponseService.php`
- `ai-assistant/prompts/assistant-open-domain-system.txt`
- `tests/Feature/Ai/QwenOpenDomainAuthorityCq42Test.php`
- `docs/evidence/jp-ai-cq42-open-domain-authority/`
- `docs/evidence/jp-ai-cq41-production-closure/` (preserve live canary evidence)
- `docs/phases/JP-AI-CQ42-OPEN-DOMAIN-AUTHORITY-SUMMARY.md`

## Tests executed
`php vendor/bin/phpunit --filter "QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41"` → 31 pass / 373 assertions  
Broader AI filter → 35 pass / 424 assertions

## Final status
CQ42_LOCAL_CLOSURE=PASS (scripted). READY_FOR_REVIEW=YES. READY_FOR_MERGE=NO. READY_FOR_DEPLOY=NO. PRODUCTION_VERIFIED=NO. PERMANENT_QWEN=HOLD. IFRAME_PILOT=HOLD.
