# Production source gap (R7D `0e747db2` → 02A eng `896f1e8a`)

Base AI chat (01C: controller, models, migration, FAB, staff queue) **is** in R7D ancestry (`e5f11213`).

Missing / updated on production relative to R7D:

- Hybrid model-free pipeline (`app/Services/Ai/Hybrid/*`)
- 01E canonicalizer / TravelIntent hardenings
- 02A eligibility mode (`off|internal_canary|public`)
- Admin AI status page
- Knowledge: `registration.md`, `cancellation.md`
- Frontend: session-aware config + quick actions

AI_REQUIRED_MIGRATIONS=`2026_09_01_010000_create_ai_conversations_tables` (in ancestry; **live apply not proven** — verify before canary)  
AI_REQUIRED_PUBLIC_SOURCE=AskJetPakistanChat + PublicConfigService  
AI_REQUIRED_LARAVEL_SOURCE=Hybrid + Eligibility + Controllers/Presenter  
AI_REQUIRED_STAFF_SOURCE=AiSupportQueue (existing) + Admin status  
AI_REQUIRED_CONFIG=`OTA_AI_ASSISTANT_MODE` (+ tools/knowledge/handoff flags)
