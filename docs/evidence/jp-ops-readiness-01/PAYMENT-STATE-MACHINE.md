# JP-OPS-READINESS-01 — Payment State Machine

**Sources:** `PaymentTransactionStatus`, `PaymentTransactionService`, `BookingPaymentStatus`, AbhiPay gateway  
**Mode:** audit-only; no live charges

## Gateway transaction statuses

`initiated` → `pending` → `created` → (`paid` | `failed` | `declined` | `cancelled` | `expired` | `verification_failed`) → optional `refunded`

Terminal helpers: `isTerminal()`, `isPaid()`.

## Manual / proof payment statuses

`pending` → `submitted` → (`verified` | `rejected`) ; also `refunded`, `partial`.

## Primary online path (AbhiPay)

1. `createAbhiPayTransaction` — builds `client_transaction_id` (`OTA-{ref}-{uuid}`)
2. Customer redirected to AbhiPay
3. `ANY /payments/abhipay/callback` (CSRF-exempt, throttled) → `processCallback`
4. Server-side `verifyTransaction` via AbhiPay API (callback body not trusted alone)
5. `applyVerificationResult` under `lockForUpdate`
6. `settleVerifiedBookingPayment` → verified `BookingPayment` → recalc booking payment status → may move booking to `paid` / `ticketing_pending`

## Idempotency controls observed

| Control | Strength |
|---------|----------|
| Paid short-circuit on replay | Strong |
| Row lock on verification | Strong |
| Reuse pending transaction same amount | Medium |
| App-level dedupe verified payment by reference | Medium (no DB unique on gateway ref) |
| Wallet/ledger deposit path | Separate; see FIN-INV gaps |

## Blockers / readiness

- AbhiPay credentials + callback registration: MANUAL_ACTIVATION_REQUIRED / EXTERNAL_DEPENDENCY
- PIA NDC online payment blocked without active PNR context (code gate)
- Duplicate webhook under race without DB unique: OPS-DEF-0013 (P2) / OPS-RISK-0002 (P0 watch)
