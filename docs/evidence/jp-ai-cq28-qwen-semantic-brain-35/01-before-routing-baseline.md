# CQ28 BEFORE routing baseline (production runtime)

Runtime: QWEN_RUNTIME_ACTIVE=NO (conversational_enabled=false, gateway :3921 down).
Production turns are hybrid / structured — LLM_CALLED=NO for normal chat.

| USER_TEXT | LLM_CALLED | HYBRID_CALLED | FINAL_MODE | FINAL_RESPONDER |
|-----------|------------|---------------|------------|-----------------|
| Lahore to Doha on 5 November | NO | YES | STRUCTURED_FALLBACK | hybrid |
| Make that business class | NO | YES | STRUCTURED_FALLBACK | hybrid |
| Is it business class? | NO | YES/active follow-up | STRUCTURED_FALLBACK | orchestrator |
| Come back from Medina to Lahore | NO | YES | STRUCTURED_FALLBACK | hybrid |
| What's the weather in Jeddah right now? | NO | open-domain | CURRENT_UNVERIFIED | open-domain fallback |
| What is JetPakistan? | NO | knowledge | STRUCTURED_FALLBACK | RAG |
| What is gravity? | NO | open-domain | GENERAL | open-domain fallback |
| Talk to support | NO | explicit handoff | HUMAN_QUEUE | orchestrator |
| Check booking ABC123 | NO | booking | STRUCTURED_FALLBACK | booking tool |
| Sure go ahead | NO | affirmative guard | STRUCTURED_FALLBACK | orchestrator |

Inference: INFERENCE_PROVIDER=null; MODEL_LATENCY_MS=n/a.
