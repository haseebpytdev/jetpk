# Agent License E2E — Homepage CMS Authority 06

## Migration

| Check | Value |
|-------|-------|
| AGENT_LICENSE_MIGRATION_FILE | **EXISTS** |
| Path | `database/migrations/2026_09_09_140000_add_license_number_to_agent_applications_table.php` |
| AGENT_LICENSE_MIGRATION_TRACKED | **YES** (included in WIP checkpoint commit) |
| Column | nullable `license_number` string(80) after `iata_number` when absent |

## Tests

Path: `tests/Feature/Agent/AgentApplicationLicensePersistenceTest.php`

| Test | Result |
|------|--------|
| `test_public_agent_registration_persists_license_number` | PASS |
| `test_dashboard_read_service_exposes_license_number` | PASS |

Command: `php artisan test --filter=AgentApplicationLicensePersistenceTest`  
Result: **2/2 PASS** (terminal evidence 330495)

| Check | Value |
|-------|-------|
| AGENT_LICENSE_SCHEMA_TEST | PASS (migration applies in test DB) |
| AGENT_LICENSE_REQUEST_VALIDATION | PASS (`StoreAgentApplicationRequest` required max:80) |
| AGENT_LICENSE_MODEL_FILLABLE/PERSISTENCE | PASS (DB column populated on POST) |
| AGENT_LICENSE_FRONTEND_FIELD | PASS (`AgentRegistrationForm.tsx` + types + registration-service) |
| AGENT_LICENSE_API_PAYLOAD | PASS (JSON `license_number` → model) |
| AGENT_LICENSE_ADMIN_DISPLAY | PASS (`DashboardAgentApplicationsReadService` + `agent-applications-workspace.tsx`) |

## Wiring map

- `StoreAgentApplicationRequest` — `license_number` required, max 80
- `AgentApplication` — `$fillable` includes `license_number`
- `AgentRegistrationController::store()` — mass-assign via `applicationAttributes()`
- `DashboardAgentApplicationsReadService` — `licenseNumber` in API record
- `AgentRegistrationForm.tsx` — visible input bound to payload
- `agent-applications-workspace.tsx` — admin review column

## Grok reconciliation

Prior Grok FAIL (`461a64b5`) cited missing migration — **incorrect**; file existed but was untracked. Tracking + tests close the defect.

AGENT_LICENSE_VERIFIER=**PASS** (engineering source)
AGENT_LICENSE_E2E=**PASS**
