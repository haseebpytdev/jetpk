# JP-OPS-06 Test Matrix

## Laravel (15 files, 195 tests)

Mandatory gate = JP-OPS-05 12 files + `BackOfficeExecutionClosureTest` + `AgentCommissionLedgerTest` + `ControlledTicketingWorkflowTest`.

| File | Focus |
|------|--------|
| `BackOfficeExecutionClosureTest` | JSON execution, RBAC, idempotency, Blade fallback |
| `BackOfficeOperationalClosureTest` | Updated process/mark-paid JSON success |
| `AgentCommissionLedgerTest` | Four ticketing commission assertions |
| `ControlledTicketingWorkflowTest` | Ticketing workflow + Sabre/Pia edge cases |

## Dashboard

| Command | Result target |
|---------|----------------|
| `npm run test:jp-ops-06-admin-staff-regression` | runtime linkage + 10 Playwright |
| `npm run test:jp-ops-06-admin-staff-operational` | 10 Playwright |
| `npm run typecheck` / `lint` / `build` | PASS |
