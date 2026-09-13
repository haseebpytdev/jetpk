# JetPakistan AI Integration — Current State (Phase 0)

**Phase:** JP-AI-CONTROLLED-INTEGRATION-01  
**Captured:** 2026-09-13  
**Purpose:** Authoritative inventory before controlled lab-stack integration.

---

## Summary fields

| Field | Value |
|-------|-------|
| `ASK_UI_PATH` | `frontend/features/ai-assistant/components/AskJetPakistanChat.tsx` (+ `AskJetPakistanChat.module.css`) |
| `AI_ROUTES` | `GET /api/public/ai/health`, `POST /api/public/ai/chat`, `GET /api/public/ai/messages`, `POST /api/public/ai/clear`, `POST /api/public/ai/handoff` (`routes/web.php`) |
| `AI_CONTROLLER` | `App\Http\Controllers\Api\PublicAiAssistantController` |
| `AI_SERVICE` | `App\Services\Ai\AiChatOrchestrator` (+ hybrid `TravelIntentExtractor`, `AiConversationalAgent`, `AiAssistantToolExecutor`) |
| `AUTH_CONTEXT_AVAILABLE` | Yes — visitor cookie `jp_ai_vid` (SHA-256 hashed), optional `user_id` on `ai_conversations`, CSRF + session for logged-in users |
| `SUPPORT_HANDOFF_AVAILABLE` | Yes — `POST /api/public/ai/handoff`, staff queue `staff.support.ai-queue.*`, states `WAITING_FOR_HUMAN` / `HUMAN_ACTIVE` |
| `EXISTING_AI_BACKEND` | PHP hybrid parser (`HybridTravelPipeline`) + optional `LocalLlamaProvider` (`http://127.0.0.1:3921`); markdown FAQ grep (`KnowledgeSearchService`); live read-only flight/group search via `AiShoppingTools` |
| `CONFLICTS_WITH_NEW_LAB_STACK` | Dual orchestrators (PHP vs Python lab), dual parsers, tool naming mismatch, no shadow mode, no RAG in production, no learning queue in production, lab not wired to Laravel |

---

## Ask JetPakistan frontend

- **Component:** `frontend/features/ai-assistant/components/AskJetPakistanChat.tsx`
- **Mount:** `frontend/components/layout/PublicShell.tsx` — `<AskJetPakistanChat enabled={aiEnabled} />`
- **Config:** `frontend/features/public-content/services/public-config-service.ts` reads `ai_assistant_enabled` / `ai_assistant_mode`
- **Session:** `localStorage` key `jp_ai_conversation_id`
- **API calls:** `POST /api/public/ai/chat`, `POST /api/public/ai/handoff`, `POST /api/public/ai/clear`, `GET /api/public/ai/messages`
- **No Blade chat widget** — Next.js only

---

## Laravel routes & controller

| Route name | Method | Path |
|------------|--------|------|
| `api.public.ai.health` | GET | `/api/public/ai/health` |
| `api.public.ai.chat` | POST | `/api/public/ai/chat` |
| `api.public.ai.messages` | GET | `/api/public/ai/messages` |
| `api.public.ai.clear` | POST | `/api/public/ai/clear` |
| `api.public.ai.handoff` | POST | `/api/public/ai/handoff` |

**Admin:** `GET /admin/settings/ai-assistant` → `AiAssistantStatusController@show`

**Staff queue:** `routes/staff.php` — `staff.support.ai-queue.index|show|takeover|reply|return-to-ai`

---

## Services & models

| Layer | Classes |
|-------|---------|
| Orchestration | `AiChatOrchestrator`, `AiConversationalAgent`, `TravelIntentExtractor` |
| Hybrid parser | `HybridTravelPipeline` + 12 resolver nodes under `app/Services/Ai/Hybrid/` |
| Tools | `AiAssistantToolExecutor`, `AiShoppingTools`, `AiAssistantBookingLookupTool`, `KnowledgeSearchService` |
| Inference | `LocalLlamaProvider`, `NullInferenceProvider`, `OpenAICompatibleProvider` (stub) |
| Eligibility | `AiAssistantEligibility` — modes `off` / `internal_canary` / `public` |
| Models | `AiConversation`, `AiMessage`, `AiHandoffAudit` |
| Migration | `database/migrations/2026_09_01_010000_create_ai_conversations_tables.php` |

---

## Authentication & identity

- Anonymous visitors: `jp_ai_vid` cookie → `visitor_token_hash` in DB
- Logged-in users: `user_id` stored on conversation when `$request->user()` present
- Conversation ownership enforced on poll/clear/handoff
- Rate limit: `anonymous_per_minute` per visitor hash
- No separate AI API key or JWT

---

## Support / handoff

- User: quick action "Talk to Support", `POST /api/public/ai/handoff`, LLM tool `human_handoff`, hybrid intent `handoff`
- States: `AI_ACTIVE` → `WAITING_FOR_HUMAN` → `HUMAN_ACTIVE` → `CLOSED`
- Staff Blade views: `resources/views/dashboard/staff/support/ai-queue/`
- Chatwoot/WhatsApp: contract docs only (`ai-assistant/adapters/`)

---

## Audit & logging

| Mechanism | Location |
|-----------|----------|
| Handoff audit | `ai_handoff_audits` table |
| Message log | `ai_messages` with JSON `meta` |
| Shopping state | `ai_conversations.shopping_state` JSON |
| App logs | `LocalLlamaProvider` warnings only |
| Learning queue | **Not in production** — lab only (`tmp/ai-lab/app/learning/`) |

---

## Environment / config keys

Authoritative block: `config/ota.php` → `ai_assistant`:

| Key | Env | Default |
|-----|-----|---------|
| `mode` | `OTA_AI_ASSISTANT_MODE` | `off` |
| `enabled` | `OTA_AI_ASSISTANT_ENABLED` | `false` |
| `gateway_url` | `OTA_AI_GATEWAY_URL` | `http://127.0.0.1:3921` |
| `timeout_seconds` | `OTA_AI_TIMEOUT_SECONDS` | `45` |
| `anonymous_per_minute` | `OTA_AI_ANON_PER_MINUTE` | `24` |
| `max_message_chars` | `OTA_AI_MAX_MESSAGE_CHARS` | `2000` |
| `model_id` | `OTA_AI_MODEL_ID` | `local` |
| `flight_search_enabled` | `OTA_AI_FLIGHT_SEARCH_ENABLED` | `true` |
| `groups_enabled` | `OTA_AI_GROUPS_ENABLED` | `true` |
| `knowledge_enabled` | `OTA_AI_KNOWLEDGE_ENABLED` | `true` |
| `human_handoff_enabled` | `OTA_AI_HUMAN_HANDOFF_ENABLED` | `true` |
| `conversational_enabled` | `OTA_AI_CONVERSATIONAL_ENABLED` | `true` |
| `optional_llm_assist` | `OTA_AI_OPTIONAL_LLM_ASSIST` | `false` |

**Note:** `.env.example` does not yet document `OTA_AI_*` keys.

---

## AI lab stack (external, pinned)

| Item | Value |
|------|-------|
| Path | `tmp/ai-lab/` |
| HEAD | `7977f19f5e35eedfca27504a53cfdff78e35f0ba` |
| Lab-03 | `CLOSED_WITH_DOCUMENTED_RESIDUAL_LANGUAGE_DEBT` |
| Lab-04A | `CLOSED` |
| Lab-04B | `CLOSED` |
| Pipeline | `app/pipeline.py` → `run_consultant_turn` |
| RAG | `app/rag/pipeline.py` |
| Learning | `app/learning/enqueue.py` |

---

## Conflicts with controlled lab integration

1. **Dual orchestrators** — PHP `AiChatOrchestrator` vs Python `run_consultant_turn`
2. **Dual parsers** — `HybridTravelPipeline` vs `app/travel/*` + `app/nodes/*`
3. **Tool naming** — PHP: `flight_search`, `faq_lookup`; lab manifest: `search_flights`, `search_jetpakistan_knowledge`
4. **Knowledge** — token grep vs certified RAG with grounding
5. **Handoff** — immediate staff queue vs consent-gated mock handoff in lab
6. **No shadow mode** — production path can call live `AiShoppingTools::searchFlights`
7. **Gateway gap** — `ai-assistant/gateway/` documented but not in repo; separate from lab consultant gateway
8. **Learning queue** — designed in lab, not deployed in Laravel

---

## Integration seam (planned)

```
Ask JetPakistan UI (unchanged)
        ↓
PublicAiAssistantController (unchanged)
        ↓
AiChatOrchestrator (feature-flag seam)
        ↓
AiLabAdapter (new)
        ↓
AI Lab Gateway (localhost :8765)
        ↓
04A Consultant Policy → Lab-03 Parser → 04B RAG → Shadow Tools
```

Laravel remains authoritative for auth, authorization, booking ownership, commercial data, supplier access, audit trail, and support actions.
