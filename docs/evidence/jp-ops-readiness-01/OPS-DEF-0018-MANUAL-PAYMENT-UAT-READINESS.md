# OPS-DEF-0018 — Manual Payment UAT Readiness (READ ONLY)

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `d42d5214b99843acb169e6374e844d5d0c3932e0`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID:** `QClrSvr5W0V2GqllIeLKE`  
**Mode:** PRODUCTION READ-ONLY / UAT PREPARATION / NO PAYMENT MUTATION

```
CONTROLLED_UAT_REQUIRES_PRODUCTION_MUTATION_APPROVAL=YES
MANUAL_PAYMENT_UAT_READINESS=READY_AWAITING_MUTATION_AUTHORIZATION
OPS_DEF_0018_STATUS=PARTIAL_BLOCKED_SABRE_PNR_UAT
```

This loop **did not** submit, verify, reject, create BookingPayment, alter amount_paid, post ledger, or call suppliers.

---

## Phase 0 — Health

| Check | Result |
|-------|--------|
| /up | 200 |
| / | 200 |
| /login | 200 |
| /admin | 302 |
| /agent | 200 |
| FAILED_JOBS | 2 (not retried/deleted) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER_RUNNING | NO |
| PAYMENT_PROOFS_MODULE_ENABLED | YES (global + agency_id=1) |

---

## Phase 1 — Routes (write routes NOT invoked)

| Channel | ROUTE_NAME | METHOD | PATH | MIDDLEWARE / AUTH |
|---------|------------|--------|------|-------------------|
| Customer submit | `customer.bookings.payment-proof` | POST | `/customer/bookings/{booking:booking_reference}/payment-proof` | `web`,`auth`,`agency.context`,`account.type:customer`,`customer.email.portal.verified`,`platform.module:payment_proofs`,`throttle:payment-proof-submit` + Gate `submitPaymentProof` |
| Agent submit | `agent.bookings.payment-proof` | POST | `/agent/bookings/{booking:booking_reference}/payment-proof` | agent stack + `agent.permission:PaymentsUpload` + `platform.module:payment_proofs` + Gate + agent_id match |
| Guest submit | `guest.bookings.payment-proof` | POST | `/guest/bookings/{booking}/access/{token}/payment-proof` | `platform.module:payment_proofs`,`throttle:payment-proof-submit` + guest token validate |
| Admin verify | `admin.bookings.payments.verify` | PATCH | `/admin/bookings/payments/{bookingPayment}/verify` | `platform_admin` + Gate `verifyPayment` |
| Admin reject | `admin.bookings.payments.reject` | PATCH | `/admin/bookings/payments/{bookingPayment}/reject` | `platform_admin` + Gate `rejectPayment` + `reason` required |
| Staff verify | `staff.bookings.payments.verify` | PATCH | `/staff/bookings/payments/{bookingPayment}/verify` | staff + `staff.permission:PaymentsVerify` + Gate |
| Staff reject | `staff.bookings.payments.reject` | PATCH | `/staff/bookings/payments/{bookingPayment}/reject` | staff + `staff.permission:PaymentsReject` + Gate |

Related (not the portal “proof submit” path): admin/staff `bookings.payments.store` → `recordManualPayment` (optional file on `local` disk).

---

## Phase 2 — Service state machine (`BookingPaymentService`)

### submitPaymentProof()

| Field | Value |
|-------|--------|
| SUBMIT_PRECONDITIONS | `payment_proofs` module enabled; Gate `submitPaymentProof`; presenter `canUploadProof` (not cancelled; no existing submitted/pending proof; unpaid/partial/rejected or balance_due>0) |
| SUBMIT_STATE_CHANGE | Creates `booking_payments` row `status=submitted` |
| BOOKING_PAYMENT_STATUS_EFFECT | **None** on booking (no recalculate) |
| BOOKING_AMOUNT_PAID_EFFECT | **None** |
| LEDGER_EFFECT | **None** |
| NOTIFICATION_EFFECT | `sendPaymentSubmitted` → communication_logs + operational notify + B2B agency notify when applicable (**no** customer mailable in this method) |
| AUDIT_EFFECT | **None** (no AuditLog write on submit) |

### verifyPayment()

| Field | Value |
|-------|--------|
| VERIFY_PRECONDITIONS | Not already Verified/Rejected; Gate `verifyPayment`; if PIA NDC booking → status refresh required |
| VERIFY_STATE_CHANGE | `status=verified`, `received_by`, `verified_at` |
| BOOKING_AMOUNT_PAID_EFFECT | **YES** via `recalculateBookingPaymentStatus` |
| BOOKING_PAYMENT_STATUS_EFFECT | **YES** unpaid/partial/paid; may `changeStatus` Paid / TicketingPending if `PaymentPending` + `pending_ticketing` |
| LEDGER_EFFECT | **YES** `LedgerEventRecorder::recordBookingPaymentVerified` (idempotent findExistingPost) |
| WALLET_EFFECT | **None** |
| SUPPLIER_EFFECT | **None** for Sabre/local_qa_inert; PIA NDC only refresh gate |
| TICKETING_EFFECT | Status may become `TicketingPending` if already `pending_ticketing` — **does not issue tickets** |
| NOTIFICATION_EFFECT | Customer/contact email via `sendPaymentVerified` + operational + B2B |
| AUDIT_EFFECT | `booking.payment_verified` |

### rejectPayment()

| Field | Value |
|-------|--------|
| REJECT_PRECONDITIONS | Not Verified/Rejected; Gate `rejectPayment`; reason required |
| REJECT_STATE_CHANGE | `status=rejected` + reason in meta |
| LEDGER_EFFECT | **None** |
| BOOKING_AMOUNT_PAID_EFFECT | Recalculate (verified sum unchanged if never verified) |
| NOTIFICATION_EFFECT | Customer/contact email `sendPaymentRejected` |
| AUDIT_EFFECT | `booking.payment_rejected` |

Second verify: blocked (`Payment is already verified.`). Multiple submits: UI blocks while submitted/pending exists; service itself does not unique-constrain proofs.

---

## Phase 3 — Mutation footprint

| Path | Tables / side effects |
|------|------------------------|
| SUBMIT_MUTATION_TABLES | `booking_payments` (+ `communication_logs` / notification jobs via sendPaymentSubmitted) |
| VERIFY_MUTATION_TABLES | `booking_payments`, `bookings` (amount_paid, balance_due, payment_status, possibly status), `audit_logs`, `ledger_transactions` (+ lines), communication/notification |
| FILE_STORAGE_MUTATION | Portal submit: **none** (no file field). Admin/staff store optional: `local` disk `booking-payments/proofs` |
| QUEUE_SIDE_EFFECTS | Notification/mail queues possible |
| LEDGER_SIDE_EFFECT | Submit=NO; Verify=YES |
| WALLET_SIDE_EFFECT | NONE |
| SUPPLIER_SIDE_EFFECT | NONE (for selected inert/Sabre non-PIA fixtures) |

```
EXPLICIT_FINANCIAL_STATE_MUTATION_REQUIRES_APPROVAL
```
(for any verify that posts ledger / changes amount_paid)

---

## Phase 4–5 — Safe QA booking selection

```
SAFE_QA_BOOKING_FOUND=YES
CANDIDATE_BOOKING_ID=17
BOOKING_REFERENCE=JPQAOPS08E4590
AGENCY_ID=1
BOOKING_STATUS=expired
PAYMENT_STATUS=unpaid
CURRENCY=PKR
TOTAL_AMOUNT=(resolver-dependent; payable columns null — use small explicit amount)
AMOUNT_PAID=0.00
BALANCE_DUE=null
SUPPLIER=local_qa_inert
QA_CLASSIFICATION=JPQA_REF + local_qa_inert + CONTACT_EMAIL_CLASS=QA_LIKE
WHY_SAFE=Explicit JPQA reference; inert supplier (no live PNR/ticket dependency); no customer PII binding; contact recipient class QA_LIKE; zero payments/ledger; unpaid
```

Baseline:

| Metric | Value |
|--------|--------|
| BOOKING_PAYMENTS_COUNT | 0 |
| SUBMITTED/VERIFIED/REJECTED | 0/0/0 |
| PAYMENT_PROOF_FILES_COUNT | 0 |
| PAYMENT_TRANSACTIONS_COUNT | 0 |
| LEDGER_ROWS_LINKED | 0 |
| AUDIT_EVENTS_LINKED | 0 |

Alternate disposable: `PLP3UEDJ` (id=14) — inert, balance_due=50000, contact QA_LIKE, no customer.  
Rejected for primary: `JPQAREJ9356` (id=20) — QA ref but `customer_id` email class LIKELY_REAL; already has 1 rejected payment.  
Rejected: live Sabre drafts / HDC7E3KM with LIKELY_REAL contact + `pending_payment_or_ticketing`.

**Submit-actor gap:** booking 17 has `customer_id=null`, `agent_id=null`, `ACTIVE_GUEST_TOKENS=0`. Portal customer/agent submit cannot bind without fixture prep. Guest submit needs a valid access token (token mint = separate authorized prep mutation — **not** performed here).

---

## Phase 6–7 — Actors (not executed)

| Role | Fixture | Proven |
|------|---------|--------|
| SUBMIT_ACTOR_TYPE | `GUEST` (preferred for booking 17) **after** authorized guest-token mint; alternate `PLATFORM_ADMIN` via `recordManualPayment(verify_now=false)` → Pending (not Submitted) | AUTH for admin/staff server-side from Wave A; guest token **not** present |
| SUBMIT_ACTOR_SAFE_FIXTURE | Guest token on JPQAOPS08E4590 (to be minted under approval) OR `jp-dash-03-qa-admin` for admin pending path | Wave A marker inventory |
| VERIFY_ACTOR_TYPE | `PLATFORM_ADMIN` (preferred) | `jp-dash-03-qa-admin` |
| VERIFY_POLICY_ALLOWS | YES (platform_admin always) | BookingPolicy |
| REJECT_POLICY_ALLOWS | YES | BookingPolicy |
| STAFF alternate | `jp-dash-03-qa-staff` if PaymentsVerify/Reject permissions present | Wave A staff perms proven historically |

---

## Phase 8 — Validation / file requirements

Portal customer/agent/guest submit:

| Field | Rule |
|-------|------|
| method | required enum `BookingPaymentMethod` |
| amount | required numeric min:1 |
| payment_reference | nullable string max:255 |
| notes | nullable string max:2000 |
| file | **not accepted** on these controllers |

Admin/staff store optional file: `payment_proof` nullable file max:5120 KB mimes jpg,jpeg,png,pdf,webp → `Storage::disk('local')->store('booking-payments/proofs')`.

**Later UAT file plan (harmless):** plain 1×1 PNG or short PDF labeled `JPQA-PAYMENT-PROOF-UAT` — only if using admin store with file; portal path needs no file.

---

## Phase 9–10 — Module / UI

```
PAYMENT_PROOFS_MODULE_ENABLED=YES
SUBMIT_UI_AVAILABLE=CONDITIONAL (needs bound actor/token; booking show surfaces gate on canUploadProof)
VERIFY_UI_AVAILABLE=YES (admin/staff booking payment capabilities when Submitted/Pending exists)
REJECT_UI_AVAILABLE=YES
```

UI mutation controls were **not** clicked.

---

## Phase 11 — Idempotency

| Behavior | Result |
|----------|--------|
| MULTIPLE_SUBMITTED_PROOFS_ALLOWED | Service allows; UI `canUploadProof` blocks while submitted/pending exists |
| DUPLICATE_REFERENCE_HANDLING | No unique on payment_reference for proofs |
| SECOND_VERIFY_HANDLING | Throws `Payment is already verified.` |
| ALREADY_VERIFIED_REVERIFY_BLOCKED | YES |

No new defect opened for idempotency (adequate for controlled one-shot UAT).

---

## Phase 12–14 — Financial / rollback / notifications

| Flag | Value |
|------|--------|
| VERIFY_CHANGES_AMOUNT_PAID | YES |
| VERIFY_CHANGES_PAYMENT_STATUS | YES |
| VERIFY_POSTS_LEDGER | YES |
| VERIFY_MUTATES_WALLET | NO |
| VERIFY_TRIGGERS_TICKETING | NO issuance; may set TicketingPending only if pending_ticketing |
| VERIFY_TRIGGERS_SUPPLIER | NO for local_qa_inert |
| APPLICATION_ROLLBACK_AVAILABLE | Reject **before** verify = YES. After verify = no supported “unverify”; refund/manual finance separate |
| UAT_REQUIRES_DISPOSABLE_QA_BOOKING | YES |
| LIVE_EMAIL_RISK | LOW for booking 17 (contact QA_LIKE; preferred recipient). Operational/admin buckets may still notify staff |
| LIVE_SMS_RISK | NONE_OBSERVED in payment proof mail path (email-centric) |

---

## Phase 15 — Storage vs OPS-DEF-0020

| Item | Value |
|------|--------|
| PAYMENT_PROOF_DISK | `local` (admin optional upload) |
| PAYMENT_PROOF_STORAGE_PATH | `storage/app/booking-payments/proofs` (dir absent until first store — Laravel creates) |
| PUBLIC_ACCESS_MODEL | private local disk; not public URL |
| public/storage | **directory** (not symlink) — OPS-DEF-0020 OPEN |
| OPS_DEF_0020_IMPACTS_PAYMENT_PROOF_UAT | **NO** for portal metadata-only submit; **LOW/NONE** for admin local-disk optional file |

---

## Phase 16 — One-shot UAT design (DO NOT EXECUTE HERE)

**Preferred (true Submitted → Verified):**

1. Authorize fixture prep: mint guest access token for booking `JPQAOPS08E4590` (id=17) — disposable QA only.  
2. Guest POST payment-proof once: method=`bank_transfer`, amount=`1` (or other approved small amount), notes=`JPQA-MANUAL-PAYMENT-UAT`.  
3. Prove BookingPayment status=`submitted`, count=+1, no ledger, amount_paid unchanged.  
4. Login `jp-dash-03-qa-admin`.  
5. **Either** reject once (safer financially) **or** verify once (full financial cert).  
6. Assert audit + notifications to QA contact only; supplier/ticket/wallet unchanged.  
7. Capture evidence; stop.

**Alternate (no guest token):** admin `recordManualPayment` with `verify_now=false` → Pending → verify/reject. Documents admin path; does **not** exercise portal `submitPaymentProof`.

**Explicitly out of scope:** AbhiPay, card charge, Sabre PNR, ticketing issue, wallet/deposit.

---

## Phase 17 — Future PASS criteria

PROOF_SUBMIT_HTTP_PASS · BOOKING_PAYMENT_CREATED_ONCE · SUBMITTED_STATUS_CORRECT · SUBMIT_AUTHORIZATION_CORRECT · REVIEWER_AUTHORIZATION_CORRECT · VERIFY_OR_REJECT_TRANSITION_CORRECT · BOOKING_AMOUNT_PAID_CORRECT (if verify) · AUDIT_CREATED (verify/reject) · NO_DUPLICATE_BOOKING_PAYMENT · NO_SUPPLIER_CALL · NO_TICKETING_CALL · NO_WALLET_MUTATION · LEDGER_BEHAVIOR_MATCHES_CONTRACT · NOTIFICATIONS_EXPECTED_ONLY

---

## Decision

```
MANUAL_PAYMENT_UAT_READINESS=READY_AWAITING_MUTATION_AUTHORIZATION
CONTROLLED_UAT_REQUIRES_PRODUCTION_MUTATION_APPROVAL=YES
FIXTURE_PREP_REQUIRED=GUEST_TOKEN_MINT_OR_ADMIN_PENDING_PATH
NEXT_REQUIRED_ACTION=Explicit human/ChatGPT authorization for (1) guest-token fixture prep OR admin-pending path, then (2) one-shot submit+reject or submit+verify on JPQAOPS08E4590
```

OPS-DEF-0018 remains **OPEN** (`PARTIAL_BLOCKED_SABRE_PNR_UAT`). Sabre PNR UAT remains a separate residual blocker for full journey close.
