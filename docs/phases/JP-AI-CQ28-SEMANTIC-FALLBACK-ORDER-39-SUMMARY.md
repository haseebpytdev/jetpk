# JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39-SUMMARY

## Phase

JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39

## Branch

`work/jp-ai-cq28-semantic-fallback-order-39` (from `1602ec2969ce15d17dd3bb92750cece48d4c6de2`)

## Objective

Primary strong travel turn with semantic planner enabled must be QWEN_SEMANTIC confirm **or** STRUCTURED_FALLBACK hybrid confirm — never legacy LLM_ASSISTED / PII-first after a semantic attempt.

## Included

- SemanticBrain `kind=fallback` telemetry
- Orchestrator: merge fallback meta; skip `AiConversationalAgent::tryHandle` on strong travel after semantic fallback; preserve meta into hybrid `$meta`
- Exact primary regressions (valid + forced fail + lead_pending+invalid)
- Destination-only correction for “make that Doha instead”
- Local real-model 10× gate + evidence
- Commit only; **no deploy**; prod flags stay false

## Excluded

- Production Qwen enable
- Deploy / iframe
- Lead capture redesign
- Composer enable

## Files changed

- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/AiChatOrchestrator.php`
- `app/Services/Ai/Hybrid/LocationResolver.php`
- `tests/Feature/Ai/QwenSemanticFallbackOrder39Test.php`
- `docs/evidence/jp-ai-cq28-semantic-fallback-order-39/*`
- `docs/phases/JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39-SUMMARY.md`
- `docs/phases/JP-AI-CQ28-RELEASE-GUARDS.md`

## Tests

- `QwenSemanticFallbackOrder39Test` PASS
- Regression filter (159 tests) PASS
- Real-model local gate PASS (`r1-real-model-results.json`)

## Known limitations

- 0.8B may still emit invalid JSON; safe hybrid confirm is acceptance-complete
- Lab adapter path still precedes conversational skip when lab is on (tests disable lab)

## Rollback

Revert branch commit; prod never changed.

## Final status

ENGINEERING_PASS; DEPLOYED=NO; PRODUCTION_QWEN=OFF; READY_FOR_LIVE_QWEN_RETRY=YES
