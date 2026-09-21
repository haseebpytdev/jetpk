# JP-AI-POST-RECOVERY-CLOSURE-08 — AI Capability Inventory (main `cf8fd79d`)

| Area | PRESENT_IN_MAIN | IMPLEMENTATION_PATH | TREE_EQUIVALENT_TO_PRE_RECOVERY | ACTION_REQUIRED |
|------|-----------------|---------------------|--------------------------------|-----------------|
| A. Admin AI control | YES | `routes/admin.php`, `AiAssistantStatusController`, `AiAssistantSettingsService`, `ai_assistant_settings` | PARTIAL | NO_ACTION (settings UI present) |
| B. AI runtime | YES | `AiServiceProvider`, `InferenceProvider`, `HttpAiLabConsultantGateway`, `AiLabAdapter`, `PublicAiAssistantController`, `AiChatOrchestrator`, canary middleware | SUPERSEDED (main adds middleware boot) | NO_ACTION |
| C. Gateway 8765 | YES | `HttpAiLabConsultantGateway`, production gateway health 200 | YES | NO_ACTION |
| D. RAG / 04A | YES | `AiChatOrchestrator`, confirmation/correction policies in AI services | PARTIAL | NO_ACTION (verify in matrix) |
| E. Read-only search | YES | `FlightSearchReadOnlyExecutor`, `FlightSearchService` integration | PARTIAL (`8c01ca4f` supplier fan-out) | MANUAL_REVIEW after matrix |
| F. Lead capture | YES (backend) | `CustomerQuery`, `CustomerQueryLeadService`, `AiCommercialIntentClassifier`, `/api/public/ai/lead`, `AskJetPakistanChat` | PARTIAL (frontend lead UI delta on old branch) | RESTORE_TO_MAIN (frontend + harness) |
| G. Customer queries admin | **NO on main** / YES on live | Missing routes/views on main; `CustomerQueryController` on production | MISSING_FROM_MAIN | **RESTORE_TO_MAIN** (done on closure branch) |
| H. Visitor identity | YES | `jp_ai_vid` cookie, `CustomerQueryLeadService` visitor association | YES | NO_ACTION |
| I. Frontend | YES | `AskJetPakistanChat`, `PublicShell`, `PublicContentApiPresenter` AI flags | PARTIAL | RESTORE_TO_MAIN (chat lead UI) |
| J. Deployment safety | YES (main) | `scripts/jetpk/*`, SEO allowlist, `test-seo-activate-ai-runtime-guard.sh` | Main **ahead** of checkpoint | Restore `verify-ai-runtime-after-seo-activate.sh` to git (closure branch) |

## Deployment guard audit (Phase 7)

| Check | Result |
|-------|--------|
| SEO_CAN_OVERWRITE_AI_RUNTIME | **NO** — allowlist + guard self-test PASS |
| PROTECTED_DEPLOY_INCLUDES_FRONTEND | **YES** — `deploy-frontend-canonical.sh` on main |
| POST_DEPLOY_AI_VERIFY_PRESENT | **YES on live** / **was missing from main git** — restored on closure branch |
