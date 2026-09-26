# CQ42 Open-Domain Authority — Evidence

## Root causes fixed

1. **SERVER_PRECEDENCE** — `SemanticBrain` match trusted Qwen `domain=booking|support|current` / `operation=lookup|handoff` over `ConversationIntentRouter::classifyOpenDomain`. Explicit user booking/handoff detectors remain; Qwen labels are advisory after server open-domain families.
2. **GENERAL_QWEN** — open-domain identity/prompt implied travel-only scope; travel-only refusals are now rejected (`OPEN_DOMAIN_REJECT_REASON=travel_refusal`) and the GK contract permits direct model answers.
3. **CURRENT_TEMPLATE** — hardcoded weather limitation for all CURRENT topics replaced with topic-aware wording (`weather|news|market|sports|generic`).

## Local gate

```
php vendor/bin/phpunit --filter "QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41"
→ 31 passed, 373 assertions
```

Broader AI filter (incl. Order39): 35 passed, 424 assertions.

## Adversarial coverage

| Case | Scripted Qwen | Expected | Status |
|------|---------------|----------|--------|
| Bitcoin price now | domain=booking lookup | CURRENT market | PASS |
| News today | domain=support handoff | CURRENT news | PASS |
| Gravity | domain=current | GENERAL + QWEN_OPEN_DOMAIN | PASS |
| Sky blue | domain=support | GENERAL | PASS |
| Wi-Fi | domain=booking | GENERAL | PASS |
| Check my booking | domain=general | booking verify | PASS |
| Talk to support | domain=general | handoff | PASS |

## Real Qwen

REAL_QWEN_AVAILABLE=UNAVAILABLE (local inference not certified in this environment). Scripted path proves authority + contract; live Qwen success rate is a post-deploy canary metric.

## Thinking UX

No frontend redesign in CQ42. Backend silent-empty residual treated as response-completion; thinking UI remains CODE_PASS from CQ41.

## Config unchanged

SEMANTIC_COMPOSER_ENABLED=false  
AI_EMBED_ENABLED=false
