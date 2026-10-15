# PAYMENT-MUTATION-INVENTORY — OPS-DEF-0013

**Date:** 2026-10-15  
**Base SHA:** `803f0ce6d0c43420314e63981407b73a7c5101f5`  
**Scope:** Code paths that create or verify `PaymentTransaction` / `BookingPayment`.

| PATH | METHOD | TYPE | GATEWAY_OR_MANUAL | IDENTITY_KEY | LOCKING | IDEMPOTENCY | DB_CONSTRAINT | RISK |
|------|--------|------|-------------------|--------------|---------|-------------|---------------|------|
| `PaymentTransactionService::startAbhiPayCheckout` | create | PaymentTransaction | GATEWAY | `client_transaction_id` (new UUID) | none on insert; reuses pending via `findReusablePendingTransaction` | reuse pending same amount | UNIQUE `client_transaction_id`; UNIQUE `(gateway,environment,gateway_order_id)` after 0013 | Medium — order-id assignment race without fail-closed was open |
| `PaymentTransactionService::processCallback` | verify settle | PaymentTransaction→BookingPayment | GATEWAY | `client_transaction_id` primary; `gateway_order_id` fallback | `lockForUpdate` in `applyVerificationResult` | `isPaid` short-circuit | PT paid status + BP `payment_transaction_id` UNIQUE | High if BP create races |
| `PaymentTransactionService::verifyTransaction` | verify settle | same | GATEWAY | PT id | `lockForUpdate` | `isPaid` short-circuit | same | High if BP create races |
| `PaymentTransactionService::applyVerificationResult` | update+settle | PaymentTransaction | GATEWAY | PT id | `lockForUpdate` | paid early return | status + paid_at | Medium — stale pending must not downgrade |
| `PaymentTransactionService::settleVerifiedBookingPayment` | create BP | BookingPayment | GATEWAY | was `payment_reference`; now `payment_transaction_id` | inherits PT lock | exists() by ref (compat) + TX link | UNIQUE `payment_transaction_id` | Primary defect surface |
| `BookingPaymentService::recordVerifiedGatewayPayment` | create | BookingPayment | GATEWAY | `payment_transaction_id` (required) | DB TX + unique catch | refetch on unique collision | UNIQUE FK | High without DB unique |
| `BookingPaymentService::recordManualPayment` | create | BookingPayment | MANUAL | generated/`payment_reference` | DB TX | none (intentional) | none on reference; `payment_transaction_id` NULL | Low — must stay NULL-link |
| `BookingPaymentService::submitPaymentProof` | create | BookingPayment | MANUAL | generated/`payment_reference` | DB TX | none | NULL TX link | Low |
| `BookingPaymentService::verifyPayment` | status→verified | BookingPayment | MANUAL | BP id | DB TX | rejects if already verified | none | Medium — separate from gateway |
| `AbhiPayGateway::handleCallback` | lookup | PaymentTransaction | GATEWAY | `client_transaction_id` then scoped `gateway_order_id` | none | n/a | UNIQUE client id; UNIQUE provider order | Medium — unscoped `first()` was ambiguous |
| `AbhiPayGateway::createPayment` | remote order | PaymentTransaction | GATEWAY | returns `gatewayOrderId` | n/a | n/a | assigned on save | Medium — collision must fail closed |
| Factories / demo / tests | create | both | MIXED | fixtures | n/a | n/a | n/a | Out of production path |

## Notes

- Ledger posting for verified payments keys on `BookingPayment` morph source (`findExistingPost`) — one BP ⇒ one ledger source.
- Promo redeem is booking-row locked and skips when already `Redeemed`.
- Notifications (`sendPaymentVerified`) are not DB-idempotent; must fire only on newly created settlement rows.
