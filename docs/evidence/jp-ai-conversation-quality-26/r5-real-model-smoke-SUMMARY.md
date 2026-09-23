# R5 REAL_MODEL_SMOKE

HEAD (pre-commit): working tree R5 tenant-actions + grounding
Gateway: LocalLlama Qwen3.5-0.8B ctx=4096 via tunnel
Public JP actions observed on all turns:
Search Flights | Browse Groups | Manage Booking | Talk to Support

| Prompt | Result | Notes |
|---|---|---|
| What is JetPakistan? | PASS | LLM_ASSISTED grounded; JP default actions |
| What is E=mc2? | PASS | structured natural physics |
| Where can I buy a jet? | PASS | LLM_ASSISTED redirect |
| Tell me a joke | PASS | casual |
| Apple stock right now? | PASS | CURRENT_UNVERIFIED limitation; no price |
| Lahore→Dubai 2 adults | PASS | help-first travel |
| Variation ×3 cat jokes | PASS | all LLM_ASSISTED (temp=0 → identical OK) |

Generic tenant action isolation covered by PHPUnit
(test_generic_embed_tenant_redirect_excludes_jetpakistan → actions=[]).

Unsupported refund grounding covered by PHPUnit
(test_grounded_knowledge_rejects_unsupported_refund_claim_uses_structured_fallback).
