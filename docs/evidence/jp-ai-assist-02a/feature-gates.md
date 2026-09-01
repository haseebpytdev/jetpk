# Feature gates

Server-authoritative `ota.ai_assistant.mode`:

- `off` — unavailable  
- `internal_canary` — Staff SupportView or Platform Admin only  
- `public` — all visitors  

`AiAssistantEligibility` gates API + `publicConfig(Request)` (`Cache-Control: private, no-store`).  
No query-string / cookie-only bypass.

Final intended 02A state: `INTERNAL_CANARY` with public OFF — **not yet applied on production** (deploy pending).
