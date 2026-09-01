# Architecture — Hybrid model-free core

```
MESSAGE
  → LanguageNormalizer
  → Domain parse (HybridTravelPipeline)
  → Location / Airline / Date / Budget / Passenger / Constraint resolvers
  → IntentConfidenceGate + ClarificationBuilder
  → ConversationStatePatcher (structured follow-up)
  → AiShoppingTools (read-only Flight / Group) OR KnowledgeRouter OR Human handoff
  → ResponseTemplateService (no LLM for routine phrasing)
```

Core Flight/Group/knowledge/handoff does **not** require a local or hosted LLM.  
Optional LLM assist remains behind `ota.ai_assistant.optional_llm_assist` (default false).

Preserved from 01E: app-owned location/airline/date/budget canonicalization; inference harness certified; 1.7B/4B quality FAIL (not activated).
