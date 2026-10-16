# OPS-DEF-0018 — Manual Payment UAT Execution (Disposable Local QA)

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `98e69b6871a3d8231578aada055b7decaa44ca24`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID:** `QClrSvr5W0V2GqllIeLKE`

```
MANUAL_PAYMENT_UAT=PASS
OJ_PAY_MANUAL_001=PASS
OPS_DEF_0018_MANUAL_PAYMENT_CERT_REQUIRED=NO
OPS_DEF_0018_STATUS=PARTIAL_BLOCKED_SABRE_PNR_UAT
REAL_SUPPLIER_API_USED=NO
REAL_CUSTOMER_USED=NO
REAL_PAYMENT_USED=NO
```

User-authorized disposable fixture + one submit + one verify only. No AbhiPay, card charge, supplier HTTP, PNR, ticket, or wallet mutation.

---

## Health (pre)

| Check | Result |
|-------|--------|
| /up / /login /agent | 200 |
| /admin | 302 |
| FAILED_JOBS_PRE | 2 (untouched) |
| PENDING_DEFAULT_PRE | 0 |
| PAYMENT_PROOFS_MODULE_ENABLED | YES |

---

## QA actors (masked)

| Role | ID | Classification |
|------|-----|----------------|
| Submit customer | 11 | QA_LIKE (`jp…qa…@jetpakistan.pk`) |
| Verify platform_admin | 9 | QA_LIKE (`jp…qa…@jetpakistan.pk`) |
| Booking contact | synthetic | `…@example.invalid` QA_LIKE |

`QA_CONTACT_IS_TEST_ONLY=YES` · `REAL_CUSTOMER_USED=NO`

---

## Disposable booking

| Field | Value |
|-------|--------|
| QA_BOOKING_ID | 40 |
| QA_BOOKING_REFERENCE | `JPQAMPAY9B8E85CF` |
| agency_id | 1 |
| supplier / provider | `local_qa_inert` |
| status (start) | `payment_pending` |
| payment_status (start) | `unpaid` |
| payable / balance_due | 1.00 / 1.00 PKR |
| amount_paid (start) | 0.00 |
| pnr / supplier refs | NULL |
| tickets / supplier attempts | 0 |
| meta | `qa_fixture=true`, `disposable=true`, `created_for=OPS-DEF-0018`, `external_supplier_call=false` |
| fare breakdown | base 1.00, total 1.00, `real_fare=false` |

Created via Eloquent in a DB transaction only — **no** supplier booking services.

---

## Payment proof submit (once)

| Field | Value |
|-------|--------|
| Path | `BookingPaymentService::submitPaymentProof` with QA customer actor |
| method | `bank_transfer` |
| amount | 1.00 PKR |
| BOOKING_PAYMENT_ID | 4 |
| status after submit | `submitted` |
| proof file | **NO** (portal path is metadata-only; no MIME upload on customer/agent/guest controllers) |
| booking amount_paid after submit | 0.00 (unchanged) |
| booking payment_status after submit | unpaid |
| notification | communication_logs incremented; recipient class QA contact + ops buckets |

`OPS_DEF_0020_IMPACT=NO` (no public storage dependency for this path).

---

## Verify (once)

| Field | Value |
|-------|--------|
| Actor | platform_admin QA id=9 |
| payment status | `verified` |
| amount_paid | 1.00 |
| balance_due | 0.00 |
| payment_status | `paid` |
| booking status | `paid` (not ticketing_pending) |
| ledger | YES — ledger_transactions id=1 amount 1.00 |
| audit | YES — `booking.payment_verified` audit id=1396 |
| wallet | 0 |
| payment_transactions / AbhiPay / card | 0 |
| supplier HTTP / attempts / bookings / tickets | 0 |
| AUTO_TICKETING_TRIGGERED | NO |
| SECOND_VERIFY_BLOCKED_BY_CODE | YES (service throws if already verified) |

---

## Isolation proof

```
SUPPLIER_HTTP_CALLS=0
SUPPLIER_BOOKING_ATTEMPTS_CREATED=0
SUPPLIER_BOOKINGS_CREATED=0
TICKETING_ATTEMPTS_CREATED=0
BOOKING_TICKETS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
ABHIPAY_ORDERS_CREATED=0
CARD_CHARGES=0
WALLET_MUTATIONS=0
```

QA booking/payment/ledger rows **retained** as certification evidence (`local_qa_inert` + qa meta). Not hard-deleted.

---

## Follow-ups

1. **Sabre PNR live UAT** remains the primary residual for OPS-DEF-0018 close.  
2. Optional P2: reporting hygiene to exclude `qa_fixture` / `local_qa_inert` from operational KPIs (not changed this loop).  
3. AbhiPay remains `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH`.

---

## Next required action

Controlled Sabre PNR UAT under OPS-DEF-0016 launch contract (separate authorization), or declare remaining browser-depth items as non-blocking documentation.
