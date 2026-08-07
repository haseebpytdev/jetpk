# JETPK-UI-06 — Customer and Agent Portal Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-06 |
| Branch | `phase/jetpk-ui-06-customer-agent-portal-closure` |
| Baseline | `b131ae9d6a5988b1b4f11c4919ae80c7fa017005` |
| Gaps | JETPK-UI-009, JETPK-UI-010, JETPK-UI-022 |
| Deployment | NOT PERFORMED |

## Root cause

Portal Playwright suites failed when the smoke Next.js process did not receive `OTA_ALLOW_SESSION_FIXTURE=true` (especially with `reuseExistingServer` reusing a stale `:3002` server). Visual audits captured login gates instead of authenticated portal interiors.

## Changes

- `start-smoke.mjs`: enables gated `OTA_ALLOW_SESSION_FIXTURE` for Playwright smoke runs.
- `playwright.config.ts`: `reuseExistingServer: false` for deterministic fixture env.
- `tests/helpers/portal-session-fixtures.ts`: shared honest customer/agent session + API mocks.
- Refactored `customer-dashboard.spec.ts` and `agent-dashboard.spec.ts` to use shared fixtures.
- `jetpk-ui-06-portal-interiors.spec.ts`: mobile/tablet/desktop interior assertions (gap 022).

## Session authority

- Fixture cookie `ota_session_fixture` + `OTA_ALLOW_SESSION_FIXTURE=true` only (never production).
- Laravel JSON route mocks supply portal data; no fabricated operational balances beyond fixture payloads.
- Agent Staff covered via `agent_staff` fixture + existing `jp-ops-04` RBAC suite.

## Gap closure

| Gap | Status |
|-----|--------|
| JETPK-UI-009 | **CLOSED** |
| JETPK-UI-010 | **CLOSED** |
| JETPK-UI-022 | **CLOSED** |

**Remaining open gaps:** 11

## Tests

- `customer-dashboard.spec.ts`
- `agent-dashboard.spec.ts`
- `jetpk-ui-06-portal-interiors.spec.ts`
- `jp-ops-04-agent-operational.spec.ts` (regression)

## Final status

Pending merge after acceptance PASS.
