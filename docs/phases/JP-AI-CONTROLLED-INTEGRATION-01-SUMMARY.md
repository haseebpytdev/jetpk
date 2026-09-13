# JP-AI-CONTROLLED-INTEGRATION-01 — Shadow Integration Summary

**Status:** SHADOW CERTIFIED (local)  
**Date:** 2026-09-13  
**Branch:** (working tree)

## Objective

Controlled integration of certified AI lab stack into JetPakistan via Laravel adapter + localhost gateway, without live supplier tools, UI redesign, or public AI ports.

## Architecture

```
Ask JetPakistan UI (unchanged)
        ↓
PublicAiAssistantController (unchanged)
        ↓
AiChatOrchestrator (feature-flag seam)
        ↓
AiLabAdapter
        ↓
AI Lab Gateway (127.0.0.1:8765)
        ↓
04A Consultant Policy → Lab-03 Parser → 04B RAG → Shadow Tools
```

## Files created

- `docs/ai-integration/current-state.md`
- `config/ai_lab.php`
- `app/Contracts/Ai/Lab/AiLabConsultantGateway.php`
- `app/Data/Ai/Lab/V1/ConsultantTurnRequest.php`
- `app/Data/Ai/Lab/V1/ConsultantTurnResponse.php`
- `app/Services/Ai/Lab/*` (adapter, gateway, gates, learning queue)
- `ai-lab-gateway/server.py`
- `tests/Unit/Ai/Lab/*`
- `tests/Feature/Ai/AiLabControlledIntegrationPhase13Test.php`
- `tests/Fixtures/ai/lab-03-residual-5.json`

## Files modified

- `app/Services/Ai/AiChatOrchestrator.php` — lab adapter seam
- `app/Providers/AppServiceProvider.php` — gateway binding
- `.env.example` — lab adapter keys documented

## Config (defaults safe)

| Key | Default |
|-----|---------|
| `OTA_AI_LAB_ADAPTER_ENABLED` | `false` |
| `OTA_AI_LAB_GATEWAY_URL` | `http://127.0.0.1:8765` |
| `OTA_AI_LAB_SHADOW_FLIGHT` | `true` |
| `allow_live_supplier` | hard `false` |

## Certification gates (test-backed)

| Gate | Result |
|------|--------|
| `CONFIRMATION_BEFORE_ACTION` | 100% (enforced in PHP + lab) |
| `ACTION_WITHOUT_CONFIRMATION` | 0 |
| `ROUTE_VISIBLE_BEFORE_ACTION` | 100% (residual-5 fixtures) |
| `CORRECTION_INVALIDATION` | snapshot mismatch blocks |
| `HANDOFF_WITHOUT_CONSENT` | 0 |
| `LIVE_DATA_FROM_RAG` | 0 (blocked at Laravel) |
| `SILENT_RESPONSES` | 0 |
| `MALFORMED_RESPONSE_ACTION` | 0 |
| `SUPPLIER_MUTATIONS` | 0 |
| `OLLAMA_PUBLIC` | NO |
| `AI_GATEWAY_PUBLIC` | NO (localhost bind) |
| `LIVE_SUPPLIER_TOOLS` | DISABLED |

## Excluded

- Production deployment / flag enablement
- Live supplier flight search through lab path
- Ask JetPakistan UI changes
- Real support ticket creation

## Rollback

1. `OTA_AI_LAB_ADAPTER_ENABLED=false`
2. Stop `ai-lab-gateway/server.py`
3. Legacy PHP orchestrator resumes immediately

## Local smoke

```bash
# Terminal 1
OTA_AI_LAB_REPO_PATH=tmp/ai-lab python ai-lab-gateway/server.py

# Terminal 2 — enable in .env for local only
OTA_AI_LAB_ADAPTER_ENABLED=true
OTA_AI_ASSISTANT_MODE=public
```
