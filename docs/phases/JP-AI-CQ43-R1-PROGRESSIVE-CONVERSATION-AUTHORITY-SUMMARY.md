# JP-AI-CQ43-R1 — Progressive Conversation Authority

## Status

`CQ43_R1_LOCAL_CLOSURE=PASS`

Branch: `work/jp-ai-cq43-r1-progressive-conversation-authority`  
Base: `46e70041` (main after evidence-only PR #39 squash-merge)  
No deploy. No permanent Qwen. No iframe. No CQ43 re-soak.

## Objective

Server-owned progressive travel authority so destination-led starts, origin-only follow-ups, refinements, booking detours, and contextual returns do not depend on Qwen labels or HELP-FIRST lead monopolization.

## Root causes closed

| Defect | Root cause | Fix |
|--------|------------|-----|
| Progressive lead hijack | Commercial travel → blocking name FSM | Soft-pending + travel authority before lead extract |
| LHE→LHE | `normalized+' '+original` self-pair on "from Lahore" | Single-scan haystack + origin-only / dest-only roles |
| Make it Doha → lead_name | Opportunistic/name stage before travel override | Travel authority first; bare-name denylist |
| kal → WAITING_FOR_HUMAN | Qwen-only support/handoff state transition | `QWEN_SUPPORT_HANDOFF_AUTHORITY=NO` |
| Check my booking swallowed | Pending confirm before booking lookup | Booking before pending; travel snapshot isolation |
| come back / wapis Sunday | Missing weekday return cues | Shared `returnDateCueRegex` + priorDepart weekday |
| hum dono | Missing RELATIONAL_PAIR phrase | PassengerExpressionResolver |

## Architectural change

Extended `ServerTravelSignals::progressiveTravelAuthority()` as the single shared progressive signal. Role-aware extraction lives in `LocationResolver` (`extractProgressiveOd` / fixed `extractRoute`). Consumers: HybridTravelPipeline, SemanticPlanValidator, ConversationIntentRouter, SemanticBrain, orchestrator lead/booking precedence.

## Precedence (orchestrator)

SECURITY → EXPLICIT HANDOFF → EXPLICIT BOOKING LOOKUP → PENDING CONFIRM → TRAVEL AUTHORITY / LEAD soft → Qwen advisory

## Tests

```
php vendor/bin/phpunit \
  tests/Feature/Ai/Cq43R1ProgressiveConversationAuthorityTest.php \
  tests/Feature/Ai/Cq42R3CrossPathTravelAuthorityTest.php \
  tests/Feature/Ai/Cq42R2ProdResidualsTest.php \
  tests/Feature/Ai/QwenOpenDomainAuthorityCq42Test.php \
  tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php \
  tests/Feature/Ai/QwenSemanticFallbackOrder39Test.php \
  tests/Feature/Ai/QwenSemanticBrain28Test.php
```

Result: **91 passed / 0 failed / 952 assertions**

Evidence: `docs/evidence/jp-ai-cq43-r1-progressive-conversation-authority/`

## Shopping-state snapshots (no PII)

**Progressive English (final):** LHE→DXB, adults=3, cabin=business, one_way  
**Roman Urdu (final):** LHE→DXB, depart=2026-09-28, adults=2, return=2026-10-04  
**Booking detour:** O/D/date/adults identical across check-booking and resume; pending confirmation preserved

## Residuals

- `CQ43_LONG_CONVERSATION_GATE=FAIL` until production re-UAT after merge/deploy  
- `BROWSER_NETWORK_RESIDUAL=DEFERRED_FOR_PRODUCTION_REPRO` (no frontend timeout invent)  
- `REAL_QWEN_AVAILABLE=NO` locally  
- `PERMANENT_QWEN_OWNER_DECISION=HOLD`  
- `IFRAME_PILOT=HOLD`

## Rollback

Revert this PR branch / commit. No production mutation performed.

## Files changed

- `app/Services/Ai/Hybrid/LocationResolver.php`
- `app/Services/Ai/Hybrid/ServerTravelSignals.php`
- `app/Services/Ai/Hybrid/DateExpressionResolver.php`
- `app/Services/Ai/Hybrid/PassengerExpressionResolver.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/Hybrid/ClarificationBuilder.php`
- `app/Services/Ai/ConversationIntentRouter.php`
- `app/Services/Ai/CustomerQueryLeadService.php`
- `app/Services/Ai/AiChatOrchestrator.php`
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/Semantic/SemanticPlanValidator.php`
- `app/Services/Ai/TravelIntentExtractor.php`
- `tests/Feature/Ai/Cq43R1ProgressiveConversationAuthorityTest.php`
- `docs/evidence/jp-ai-cq43-r1-progressive-conversation-authority/*`
- `docs/phases/JP-AI-CQ43-R1-PROGRESSIVE-CONVERSATION-AUTHORITY-SUMMARY.md`
