# OPS-DEF-0013 — Payment DB Idempotency Remediation

**Date:** 2026-10-15  
**Branch:** `fix/ops-def-0013-payment-idempotency`  
**Base main SHA:** `803f0ce6d0c43420314e63981407b73a7c5101f5`  
**Production runtime SHA (read-only probe):** `7fe1cd8088ff15d4e27bbc512521dc1055e7a12c`  
**Status:** `FIX_READY_NOT_DEPLOYED`

---

## ROOT_CAUSE

Gateway settlement idempotency relied on application-level checks only:

1. `PaymentTransaction` row lock + `isPaid()` short-circuit (good for same-row retries).
2. `BookingPayment::exists()` by `(booking_id, method=abhipay, payment_reference, verified)` — **not a DB invariant**.
3. No FK/unique link from `booking_payments` → `payment_transactions`.
4. `gateway_order_id` indexed but not unique — callback fallback used unscoped `first()`.

Concurrent/retried settlement could theoretically create duplicate verified `BookingPayment` rows, double `amount_paid`, duplicate ledger sources, duplicate notifications, and duplicate settlement audits.

---

## CURRENT_APP_GUARDS

- `applyVerificationResult()`: `lockForUpdate()` + paid early return.
- `processCallback()` / `verifyTransaction()`: skip remote verify when already paid.
- Promo redeem: booking lock + already-`Redeemed` short-circuit.
- Ledger: `findExistingPost(BookingPayment, BookingPaymentVerified)`.

These remain as defense-in-depth; they are no longer the primary idempotency guarantee.

---

## MISSING_DB_INVARIANT

Before this change:

- `booking_payments`: PRIMARY only (no gateway settlement unique).
- `payment_transactions.gateway_order_id`: non-unique index only.

---

## PAYMENT_TRANSACTION_LINK_DESIGN

```
booking_payments.payment_transaction_id
  nullable FK -> payment_transactions.id (nullOnDelete)
  UNIQUE index: booking_payments_payment_transaction_id_unique
```

| Value | Meaning |
|-------|---------|
| NULL | Manual / non-gateway / historical |
| non-NULL | Exactly one BookingPayment per PaymentTransaction |

---

## PROVIDER_ORDER_UNIQUE_DESIGN

```
UNIQUE(gateway, environment, gateway_order_id)
index: payment_transactions_gateway_env_order_unique
```

- Environment-scoped (same provider order id may exist in `test` vs `production`).
- Multiple NULL `gateway_order_id` values remain allowed.
- Legacy single-column `gateway_order_id` index dropped when unique composite is added.
- Migration refuses if duplicate non-null `(gateway, environment, gateway_order_id)` groups exist.

---

## HISTORICAL_BACKFILL_STRATEGY

Unambiguous AbhiPay rows only:

- `method = abhipay`
- `payment_reference = payment_transactions.client_transaction_id`
- optional `meta.payment_transaction_uuid` match
- booking_id must match when both present
- refuse on 0→skip, 1→link, >1 or mismatch→throw (no `first()` guess)

**Production preflight (2026-10-15):**  
`PAYMENT_TRANSACTIONS_TOTAL=0`, `ABHIPAY_BOOKING_PAYMENTS_TOTAL=0` → backfill is a no-op.

---

## CALLBACK_RACE_PROOF

- Double `verifyTransaction` / double `applyVerificationResult` → 1 BookingPayment, amount_paid once.
- DB UNIQUE on `payment_transaction_id` blocks second insert.
- Unique collision → refetch existing row; no duplicate side effects.
- SQLite limitation: true multi-process concurrency not proven; same-process double invoke + unique constraint proven.

---

## LEDGER_IDEMPOTENCY

`LedgerEventRecorder::recordBookingPaymentVerified` keys on BookingPayment morph source.  
One BookingPayment per PaymentTransaction ⇒ at most one ledger source.  
`DUPLICATE_CALLBACK_LEDGER_POSTS=0` (test).

---

## NOTIFICATION_IDEMPOTENCY

`recordVerifiedGatewayPayment` returns `{payment, created}`.  
`sendPaymentVerified` runs only when `created=true`.  
`DUPLICATE_CALLBACK_NOTIFICATIONS=0` (test).

---

## AUDIT_IDEMPOTENCY

`booking.payment_abhipay_verified` written only when settlement row was newly created.  
`DUPLICATE_CALLBACK_SETTLEMENT_AUDIT_ONCE=PASS`.

---

## MANUAL_PAYMENT_COMPATIBILITY

Manual paths leave `payment_transaction_id=NULL`.  
No global UNIQUE on `payment_reference`.  
Multiple NULL-link manual rows allowed.  
`MANUAL_PAYMENT_REGRESSION=PASS`.

---

## PRODUCTION_PREFLIGHT

| Metric | Value |
|--------|------:|
| PAYMENT_TRANSACTIONS_TOTAL | 0 |
| BOOKING_PAYMENTS_TOTAL | 3 |
| ABHIPAY_TRANSACTIONS_TOTAL | 0 |
| ABHIPAY_BOOKING_PAYMENTS_TOTAL | 0 |
| DUPLICATE_CLIENT_TRANSACTION_IDS | 0 |
| DUPLICATE_NON_NULL_GATEWAY_ORDER_IDS_BY_GATEWAY_ENV | 0 |
| DUPLICATE_ABHIPAY_PAYMENT_REFERENCES | 0 |
| VERIFIED_ABHIPAY_WITHOUT_MATCHING_TX | 0 |
| ABHIPAY_TX_WITH_MULTIPLE_BP | 0 |
| AMBIGUOUS_GATEWAY_SETTLEMENT_ROWS | 0 |
| FAILED_JOBS | 1 |
| FAILED_JOB_PAYMENT_RELATED | NO (QA SMTP bounce) |

`PRODUCTION_PAYMENT_MUTATIONS=0`  
`PRODUCTION_SCHEMA_MUTATIONS=0`

---

## TEST_RESULTS

Targeted suite (worktree):

- `tests/Feature/Payments/OpsDef0013PaymentIdempotencyTest.php` — 11 tests PASS
- Plus AbhiPay / PaymentWorkflow / PiaNdc checkout (see PR CI / local run log)

---

## OPS_DEF_0013_STATUS

`FIX_READY_NOT_DEPLOYED`

Next: production schema/deployment closure (path-scoped migrate + markers) — **not** in this loop.

After production closure: `NEXT_PRIORITY=OPS-DEF-0004`.
