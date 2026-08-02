# JP-OPS-04 Wallet, Deposit & Commission Contract

## Wallet (`GET /agent/wallet?format=json`)

| Field | Source |
|-------|--------|
| `summary.balance` | `AgentWalletService` |
| `summary.available_balance` | Server-calculated |
| `summary.currency` | Wallet record (default PKR) |
| `recent_ledger_entries` | Scoped to agent wallet |
| `capabilities` | Module + permission flags |

Requires `wallet.view` + `platform.module:agent_wallet`.

## Ledger (`GET /agent/ledger?format=json`)

Operational wallet transaction ledger. Requires `ledger.view` + `agent_ledger` module.

**Deferred:** `/agent/accounting/ledger` Next binding — Laravel accounting ledger remains Blade-only.

## Deposits

| Step | Endpoint | Gate |
|------|----------|------|
| List | `GET /agent/deposits?format=json` | `wallet.view` + `agent_deposits` |
| Create form | `GET /agent/deposits/create?format=json` | `payments.upload` + `agent_deposits` |
| Submit | `POST /agent/deposits?format=json` | `payments.upload` + `agent_deposits` |

### Deposit CTA (JP-OPS-04)

`DepositListPage` sets `canCreateDeposit` from `capabilities.can_submit_deposit` — not inferred from role or nav presence.

Admin verify/reject and wallet credit remain platform-admin Blade flows (JP-OPS-01 PAY-01).

## Payments & invoices

Read-only JSON lists scoped via agent bookings. No client-side retry or status mutation.

## Commissions (owner only)

### Index (`GET /agent/commissions?format=json`)

```json
{
  "ok": true,
  "balance": 0,
  "totals": { "pending": 0, "approved": 0, "paid": 0, "currency": "PKR" },
  "entries": [{ "id": 1, "booking_reference": "...", "amount": 0, "status": "pending" }],
  "statements": [{ "id": 1, "reference": "STMT-1", "detail_url": "/agent/commissions/statements/1" }]
}
```

### Statement detail (`GET /agent/commissions/statements/{id}?format=json`)

Statement header + line entries. `Gate::authorize('view', $statement)` enforces agent ownership.

Staff users receive 403 on commission endpoints (`agent.admin` middleware).

## Agency wallet summary

`GET /agent/agency?format=json` includes `wallet_summary` when actor has `wallet.view`; otherwise `null`.

## Markup

Agent-facing markup mutation is **not** exposed. Admin `MarkupRulePolicy` remains out of scope.
