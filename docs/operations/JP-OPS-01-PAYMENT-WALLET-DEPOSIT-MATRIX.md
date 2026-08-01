# JP-OPS-01 Payment, Wallet, and Deposit Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

## Customer payments (B2C)

| Flow | FE Route | Laravel Action | State Authority | CSRF | Classification |
|------|----------|----------------|-----------------|------|----------------|
| Manual payment | `/booking/payment/manual` | review → manual instructions | Server booking status | ✓ | OPERATIONAL_CONNECTED |
| Pay by card | `/booking/payment/card` | `payments.abhipay.start` → external | AbhiPay + callback | ✓ start | OPERATIONAL_CONNECTED |
| Payment return | `/booking/payment/return` | return URL handler | Server reconciles | — | OPERATIONAL_CONNECTED |
| Payment status | `/booking/payment/status` | booking payment poll | Server | — | OPERATIONAL_CONNECTED |
| Confirmation | `/booking/confirmation` | post-payment view | Server | — | OPERATIONAL_CONNECTED |
| Invoice | `/booking/invoice` | document generation | Server | — | OPERATIONAL_CONNECTED |
| Payment proof (customer) | customer booking detail | `POST customer/bookings/{ref}/payment-proof` | Server verify flow | ✓ | OPERATIONAL_CONNECTED |
| AbhiPay callback | — | `GET\|POST payments/abhipay/callback` | **Server only** sets Paid | exempt | OPERATIONAL_CONNECTED |

### Payment security verified

| Check | Status |
|-------|--------|
| No PAN collection in frontend | ✓ |
| No CVV/expiry in frontend | ✓ |
| Card handoff external (AbhiPay) | ✓ |
| Browser never authoritatively sets Paid | ✓ |
| Duplicate callback protection | Server idempotency (AbhiPay controller) |
| Duplicate submit throttle | `throttle:public-booking-submit`, `abhipay-payment-start` |

### Payment states

| State | Server | FE display |
|-------|--------|------------|
| pending | ✓ | status page |
| paid | callback only | confirmation |
| failed/cancelled | ✓ | status page |
| manual_review | proof upload | manual flow |

## Agent wallet

| Action | FE | Laravel | Permission | Classification |
|--------|-----|---------|------------|----------------|
| View balance | `/agent/wallet` | `GET /agent/wallet` | wallet.view | OPERATIONAL_CONNECTED |
| Ledger | `/agent/wallet/ledger` | `GET /agent/ledger` | ledger.view | OPERATIONAL_CONNECTED |
| Wallet pay booking | agent checkout | wallet debit service | wallet + booking | OPERATIONAL_CONNECTED (module) |
| Accounting ledger | — | `agent/accounting/ledger` | ledger.view | BACKEND_WITHOUT_FRONTEND_BINDING |

## Agent deposits

| Step | FE | Laravel | Admin review | Classification |
|------|-----|---------|--------------|----------------|
| List deposits | `/agent/deposits` | `GET /agent/deposits` | — | OPERATIONAL_CONNECTED |
| New deposit | `/agent/deposits/new` | `GET create`, `POST store` | — | OPERATIONAL_CONNECTED |
| Proof upload | form on new deposit | `POST /agent/deposits` + file | — | OPERATIONAL_CONNECTED |
| Admin review | — (Blade) | `admin/agent-deposits/*` verify/reject | platform_admin | BACKEND_WITHOUT_FRONTEND_BINDING |
| Wallet credit | — (Blade) | admin wallet credit actions | platform_admin | BACKEND_WITHOUT_FRONTEND_BINDING |
| Immutable ledger | — | `AgentLedger`, `WalletAuditPolicy` | — | OPERATIONAL_CONNECTED |

## Commission / markup effects

- Commission displayed on agent booking list when present in JSON
- Markup rules: admin `MarkupRulePolicy` — Blade admin only
- Agent commission statements: Laravel `agent/commissions` — no Next.js page

## Gaps

| ID | Gap | Severity |
|----|-----|----------|
| PAY-01 | Admin deposit approval not in Next.js dashboard | P1 |
| PAY-02 | Wallet payment UI path depends on module flag — verify per agency | P2 |
| PAY-03 | Group booking payment may be manual-only | P2 |
