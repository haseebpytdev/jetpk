# JP-AI-CQ28-QWEN-SEMANTIC-BRAIN-35 — Phase Summary

## Objective
Make local Qwen the primary conversational semantic brain; JetPakistan app remains final authority for validation, permissions, confirmation, tools, tenant isolation, and supplier safety.

## Branch
`work/jp-ai-cq28-qwen-semantic-brain-35` from `386b3aa78ef5aef89aed4341eac9f3eb548ef5f9`

## Runtime diagnosis
`QWEN_RUNTIME_ACTIVE=NO` on production (conversational disabled; gateway :3921 down). Architecture + stubbed tests delivered. Real-model ≥100-turn eval = TEMPORARILY_BLOCKED.

## Architecture
```
SECURITY → HANDOFF STATE → PENDING YES/NO → QWEN SEMANTIC PLANNER
→ SERVER VALIDATOR → SERVER POLICY → TOOL/RAG/ANSWER
→ RESPONSE COMPOSER (optional) → HYBRID FALLBACK
```

### New classes
- `App\Data\Ai\SemanticPlan`
- `App\Services\Ai\Semantic\QwenSemanticPlanner`
- `App\Services\Ai\Semantic\SemanticPlanValidator`
- `App\Services\Ai\Semantic\SemanticResponseComposer`
- `App\Services\Ai\Semantic\SemanticBrain`

Hybrid (`HybridTravelPipeline`) remains validator support + fallback. Legacy `AiConversationalAgent::tryHandle` tool-naming retained only when semantic plan is absent.

## Config
- `OTA_AI_SEMANTIC_PLANNER_ENABLED` (default true)
- `OTA_AI_SEMANTIC_COMPOSER_ENABLED` (default true)
Requires `OTA_AI_CONVERSATIONAL_ENABLED=true` + healthy localhost gateway for planner activation.

## Tests
- `tests/Feature/Ai/QwenSemanticBrain28Test.php`
- `tests/Feature/Ai/QwenSemanticBrain28FallbackTest.php`
- CQ27 + Closure29 regression PASS

## Deploy / iframe
DEPLOYED=NO · AI_EMBED_ENABLED=false · IFRAME_ACTIVATION=NO
