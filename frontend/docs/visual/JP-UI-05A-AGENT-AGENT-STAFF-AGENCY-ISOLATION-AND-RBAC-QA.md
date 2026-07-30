# JP-UI-05A — Agent, Agent Staff, Agency Isolation and RBAC QA

## Test file

`frontend/tests/jp-ui-05a-agent-rbac.spec.ts`

## Command

```bash
cd frontend
npm run build
npx playwright test tests/jp-ui-05a-agent-rbac.spec.ts -c playwright.config.ts
```

## Results (JP-UI-05A)

| Test | Result |
|------|--------|
| Agent can access agency booking `BKG-2001` | Pass |
| Cross-agency `BKG-OTHER` safe not-found | Pass |
| Agent Staff wallet route forbidden | Pass |
| Agent Staff navigation omits wallet link | Pass |
| Agent private route noindex | Pass |

## Wallet / ledger / deposit scope

Visual matrix scenarios: `agent-wallet`, `agent-ledger`, `agent-deposits`, `agent-staff-owner-route-forbidden`, `agent-wallet-unavailable`. Agent Staff fixture sets `modules.agent_wallet: false`; wallet API returns 403.

## Agency isolation

Cross-agency booking returns 404/not-found message; no other-agency identity in page content. Ledger and deposits remain agency-scoped via Laravel contract (fixture-backed in tests).

## Laravel

No Laravel changes in JP-UI-05A.
