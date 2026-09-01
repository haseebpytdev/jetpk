# Live chat R2

- Mode: INTERNAL_CANARY (server `.env` `OTA_AI_ASSISTANT_MODE=internal_canary`)
- Anonymous browser: Ask JetPakistan text count=0; POST `/api/public/ai/chat` → 503 unavailable
- Canary identity (user id 9 platform admin): orchestrator + controller chat PASS
- UNIFIED_FAB_AI_ENTRY=PASS (`PublicShell` + `AskJetPakistanChat` + `PublicFloatingActionDock`; enabled only when config `ai_assistant_enabled` for eligible user)
- SECOND_AI_FLOATING_BUTTON=NO
- LIVE_CHAT_TRANSPORT=laravel_public_ai_api
- CHAT_NAVIGATION_PERSISTENCE / REFRESH: conversation `public_id` + visitor cookie `jp_ai_vid` (orchestrator)
- CHAT_IDOR=PASS (suite)
