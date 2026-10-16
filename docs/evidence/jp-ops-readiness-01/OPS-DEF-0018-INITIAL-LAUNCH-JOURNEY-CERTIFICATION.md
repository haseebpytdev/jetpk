# OPS-DEF-0018 — Initial Launch Journey Certification

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `d85edb1e24724eef11b9c6bc31e929155f0cdea9`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID:** `QClrSvr5W0V2GqllIeLKE`  
**Branch:** `ops/ops-def-0018-initial-launch-certification`

---

## Launch contract (authoritative)

| Field | Value |
|-------|--------|
| PAYMENT_PROFILE | MANUAL_ONLY |
| ABHIPAY | DEFERRED_NOT_IN_LAUNCH_SCOPE (`OPS-DEF-0017` = `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH`) |
| SUPPLIER | Sabre GDS limited (`OPS-DEF-0016` = `CLOSED_LAUNCH_SCOPE_DEFINED`) |
| TICKETING | External/manual; product auto-ticketing disabled |
| AGENCY | agency_id=1 JetPakistan |

**Safety this loop:** No AbhiPay, no live card charge, no supplier ticket/cancel/refund/void, no wallet/deposit mutation, no destructive admin ops.

---

## Closed-defect rebase (remove as active journey blockers)

| Defect | Status | Journey impact |
|--------|--------|----------------|
| OPS-DEF-0004 | CLOSED (runtime `922f1577`) | Viewer cancel DENY — remove as OJ-B2B-VIEW active blocker |
| OPS-DEF-0006 | CLOSED | Accountant finance path no longer blocked by wallet/ledger atomicity defect |
| OPS-DEF-0007 | CLOSED | Canonical wallet residual closed |
| OPS-DEF-0013 | CLOSED | Payment idempotency uniqueness PASS |
| OPS-DEF-0016 | CLOSED_LAUNCH_SCOPE_DEFINED | Excluded suppliers are NOT launch blockers |
| OPS-DEF-0017 | OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH | AbhiPay NOT initial-launch payment blocker |

Still open / residual: OPS-DEF-0024 (legacy over-permissioned support QA fixture — prefer FINAL-11), Sabre PNR live UAT incomplete, browser password env missing for deep Playwright auth.

---

## Manual payment workflow (architecture)

| Layer | Evidence | Classification |
|-------|----------|----------------|
| Methods enum | `BookingPaymentMethod`: bank_transfer, cash, card_manual, easypaisa, jazzcash (+ abhipay deferred) | READY |
| Proof submit | `customer.bookings.payment-proof`, `agent.bookings.payment-proof`, `guest.bookings.payment-proof` + `platform.module:payment_proofs` | READY |
| Service lifecycle | `BookingPaymentService::submitPaymentProof` → status Submitted; `verifyPayment` / `rejectPayment` | READY_SOURCE |
| Admin/staff review | `admin.bookings.payments.verify/reject`, `staff.bookings.payments.verify/reject` | READY |
| AbhiPay UI | row=0; `PublicAbhiPayCheckoutPresenter` gates pay button | ABHIPAY_UI_AVAILABLE=NO |
| Live proof submit / verify mutation | Not executed this loop | MANUAL_UAT_REQUIRED |

**MANUAL_PAYMENT_WORKFLOW = SOURCE_READY_MANUAL_UAT_REQUIRED**

---

## Public health (2026-10-16)

| URL | HTTP |
|-----|------|
| /up | 200 |
| / | 200 |
| /login | 200 |
| /admin | 302 |
| /agent | 200 |

---

## Journey matrix (INITIAL MANUAL + Sabre limited)

| JOURNEY_ID | ROLE | AUTH | ROUTES | SCOPING | READ_PATH | MANUAL_PAYMENT_PATH | WRITE_PATH | EXTERNAL_DEPENDENCY | ACTIVE_BLOCKER | FINAL_CLASSIFICATION |
|------------|------|------|--------|---------|-----------|---------------------|------------|---------------------|----------------|----------------------|
| OJ-B2C-001 | CUSTOMER | YES_SERVER_SIDE | PUBLIC_ENTRY_OK | N/A | CAPABILITY_LEVEL | SOURCE_READY | NOT_EXECUTED_STOP_BEFORE_SABRE | SABRE_BOOKING_LIVE_CALL_ON | SABRE_PNR_LIVE_UAT + MANUAL_PAYMENT_UAT | PASS_TO_SAFE_BOUNDARY |
| OJ-B2B-OWN-001 | AGENCY_OWNER | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | SOURCE_READY | NOT_EXECUTED (no wallet/deposit) | SABRE_LIMITED | BROWSER_DEPTH + SABRE_PNR_UAT | PARTIAL |
| OJ-B2B-MGR-001 | MANAGER | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | READ_SURFACES_OK | NOT_EXECUTED; cancel perm assignment follow-up | NONE_FOR_READ | BROWSER_DEPTH | PARTIAL |
| OJ-B2B-ACC-001 | ACCOUNTANT | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | FINANCE_READ_OK | NOT_EXECUTED (no deposit approve) | NONE | BROWSER_DEPTH (0006 CLOSED) | PARTIAL |
| OJ-B2B-SUP-001 | SUPPORT_STAFF | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | N/A_READ | NOT_EXECUTED | OPS-DEF-0024 fixture risk | Prefer FINAL-11; BROWSER_DEPTH | PARTIAL |
| OJ-B2B-VIEW-001 | VIEWER | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | N/A | CANCEL_DENY_PROVEN_PROD | NONE | 0004 CLOSED; BROWSER_DEPTH | PARTIAL_READ_SAFE |
| OJ-STAFF-001 | STAFF | YES_SERVER_SIDE | NAMED_ROUTES_PARTIAL | CAPABILITY_MATRIX_OK | CAPABILITY_LEVEL | VERIFY_ROUTES_PRESENT | NOT_EXECUTED (no ticket/verify mutation) | NONE | BROWSER_DEPTH | PARTIAL |

---

## Supplier / PNR / ticketing

| Item | Status |
|------|--------|
| SABRE_SEARCH_CERTIFIED | YES (prior 0016 posture; search active in launch scope) |
| SABRE_PNR_LAUNCH_STATUS | LIMITED_ARMED_UAT_INCOMPLETE (`booking_live_call` ON; itinerary-class live PNR UAT not executed) |
| TICKETING_LAUNCH_MODE | MANUAL_EXTERNAL_DISABLED_IN_PRODUCT |
| Excluded suppliers | Not blockers for initial launch |

---

## Closure decision

```
OPS_DEF_0018_STATUS=PARTIAL_BLOCKED_SABRE_PNR_UAT
OPS_DEF_0018_ABHIPAY_BLOCKER=NO
OPS_DEF_0018_MANUAL_PAYMENT_CERT_REQUIRED=YES
OPS_DEF_0018_ONLINE_PAYMENT_CERT_DEFERRED=YES
PRODUCTION_OPERATIONAL_READY=NO
```

**Why not CLOSED_INITIAL_LAUNCH_PROFILE:** Controlled Sabre PNR live UAT not completed; manual payment proof submit/verify not mutation-certified (architecture READY); browser authenticated depth still limited without password env.

**ACTIVE_P1_BLOCKERS_FOR_INITIAL_LAUNCH:**
1. Sabre PNR controlled live UAT (for full book certification)
2. Manual payment E2E human UAT (proof submit + staff verify) — authorized separately
3. Residual ops items outside payment (e.g. observability OPS-RISK-0017) — not payment blockers

---

## Next required action

1. Authorized controlled Sabre PNR UAT playbook (non-ticketed / approved QA).
2. Authorized manual payment proof + staff verify UAT on agency 1 (no AbhiPay).
3. Keep AbhiPay deferred until explicit reopen of OPS-DEF-0017.
