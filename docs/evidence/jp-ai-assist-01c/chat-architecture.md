# Chat architecture

- Public API: `/api/public/ai/{health,chat,messages,clear,handoff}`
- Cookie `jp_ai_vid` (httpOnly) → hashed `visitor_token_hash`
- Conversation `public_id` UUID (non-sequential)
- States: AI_ACTIVE, WAITING_FOR_HUMAN, HUMAN_ACTIVE, CLOSED
- Modes: AI_FULL | STRUCTURED_FALLBACK | AI_UNAVAILABLE
- Transport: short polling of `/messages` (LIVE_CHAT_TRANSPORT=short_poll)
- UI: `AskJetPakistanChat` via FAB `#ask-jetpakistan` (no second bubble)
- Staff queue: `/staff/.../ai-queue` Blade list/show + takeover/reply/return-to-AI
- InferenceProvider: LocalLlamaProvider | Null | OpenAICompatible stub (unused)

Load shedding: unhealthy provider or disabled → structured fallback / soft unavailable (no 500).
