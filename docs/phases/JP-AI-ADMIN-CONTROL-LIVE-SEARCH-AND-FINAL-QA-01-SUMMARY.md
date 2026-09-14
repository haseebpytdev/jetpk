# JP-AI-ADMIN-CONTROL-LIVE-SEARCH-AND-FINAL-QA-01

## Phase identity

| Field | Value |
|---|---|
| Phase | JP-AI-ADMIN-CONTROL-LIVE-SEARCH-AND-FINAL-QA-01 |
| Branch | `phase/jp-ai-production-canary-01` |
| PRE_ADMIN_CONTROL_HEAD | `1996a05b` |
| CANARY_HARNESS_COMMIT | `d1c522ed` |
| ADMIN_CONTROL_COMMIT | `159e57cb` |
| REMOTE_HEAD | `159e57cb` |
| PRODUCTION_HEAD | `159e57cb` |
| SOURCE_PARITY | PASS |

## Objective

Upgrade `/admin/settings/ai-assistant` from read-only status to a safe admin control plane; wire `FLIGHT_SEARCH_READ_ONLY` through `FlightSearchService`; preserve env hard ceiling; keep public beta and write tools blocked.

## Architecture

### Existing settings system (Phase 1)

| Item | Finding |
|---|---|
| EXISTING_SETTINGS_SYSTEM | Domain-specific tables + services (no global KV store) |
| PERSISTENCE_MODEL | New `ai_assistant_settings` singleton (CommerceCheckout pattern) |
| CACHE_LAYER | None for AI admin settings (direct DB read) |
| AUDIT_LAYER | `audit_logs` via `AiAssistantSettingsService::update()` |
| AUTHORIZATION_PATTERN | `Gate::authorize('platform.admin')` + CSRF web middleware |

### Effective setting formula (Phase 3)

```
EFFECTIVE_CAPABILITY = ENV_HARD_ALLOW ∧ ADMIN_PERSISTED_SETTING ∧ MASTER (where applicable)
USER_ELIGIBILITY applied at PublicAiAssistantController / AiChatOrchestrator
```

`allow_live_supplier` remains hard-coded `false` in `config/ai_lab.php`.

## Files changed

- `database/migrations/2026_09_14_100000_create_ai_assistant_settings_table.php`
- `app/Models/AiAssistantSetting.php`
- `app/Services/Ai/AiAssistantSettingsService.php`
- `app/Services/Ai/AiAssistantEligibility.php`
- `app/Http/Controllers/Admin/AiAssistantStatusController.php`
- `app/Services/Ai/Lab/FlightSearchReadOnlyExecutor.php`
- `app/Services/Ai/Lab/AiLabAdapter.php`
- `app/Services/Ai/Lab/ConfirmationPolicyGate.php`
- `app/Services/Ai/AiChatOrchestrator.php`
- `config/ota.php`, `config/ai_lab.php`
- `resources/views/dashboard/admin/settings/ai-assistant.blade.php`
- `routes/admin.php`
- Tests under `tests/Feature/Ai/` and `tests/Unit/Ai/`
- Canary harness: `frontend/scripts/canary-matrix-helpers.mjs`, `run-canary-browser-matrix.mjs`

## Tests executed

```
phpunit tests/Feature/Ai/AiAssistantSettingsAdminTest.php
phpunit tests/Unit/Ai/AiAssistantSettingsEffectiveGateTest.php
phpunit tests/Feature/Ai/FlightSearchReadOnlyToolTest.php
phpunit tests/Feature/Ai/AiAssistantEligibilityTest.php
→ 12/12 PASS
```

Production:
- `ai:lab-shadow-certify --json` → 5/5 PASS
- `ai:lab-canary-certify --json` → 19/19 PASS, SUPPLIER_MUTATIONS=0

## Supplier search audit (Phase 11)

Active production connections with flight search path via `FlightSearchService`:

| Provider | SEARCH | READ_ONLY | DEPLOYED | SAFE_FOR_QA |
|---|---|---|---|---|
| Sabre | YES | YES | YES | YES |
| PIA NDC | YES | YES | YES | YES |
| AirBlue / Duffel / IATI / OneAPI | Not in active prod connections | — | — | — |

SMTP and group providers are not flight-search orchestration paths.

## Known limitations

- **Live read-only search on production**: `OTA_AI_FLIGHT_SEARCH_READ_ONLY_HARD_ALLOW=false` — tool implemented but env ceiling blocks live supplier calls until owner enables.
- **100-turn soak / full 55+ browser matrix**: not re-run in this session; prior certified evidence `30/30` browser + `19/19` artisan canary retained.
- **Browser admin master OFF/ON**: covered by PHPUnit persistence tests; live browser UAT deferred to next soak window.

## Rollback

1. Restore prior Laravel files from backup or Git SHA `b2465783`
2. `php artisan migrate:rollback` (drops `ai_assistant_settings` only)
3. `php artisan optimize:clear && php artisan view:clear`

## Final status

See closure report in agent output — **PUBLIC_BETA_READY=NO** by design.
