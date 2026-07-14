# Email, Cancellation & Refund Audit — Iati_new vs OTA

**Audit date:** 2026-06-08  
**Mode:** Read-only

---

## 1. Executive Summary

| Area | Iati_new | Our OTA |
|------|----------|---------|
| Email transport | PHPMailer / Postmark; sync | Laravel Mail / `OtaNotificationService`; queue-capable |
| Template system | PHP view files per product | Blade + DB `agency_message_templates` + registry |
| Cancel workflow | Portal request + live Sabre HTTP (fragile) | Full RBAC workflow; **no live Sabre cancel in service** |
| Refund workflow | DB logging endpoint only | `BookingRefundService` with approve/pay/reject |
| Customer safety | Risk of raw errors in debug; templates OK | Sanitized operational events; enum-driven |
| Admin alerts | `error_log`, debug files | `OtaNotificationEvent`, diagnostic logs |

**Verdict:** OTA is stronger on **process and communications architecture**; Iati has **live supplier cancel attempt** (but unsafe). OTA should implement Phase G cancel without copying Iati's cancel.php.

---

## 2. Email Sending Mechanism

### Iati_new

| Mechanism | Path | Notes |
|-----------|------|-------|
| PHPMailer SMTP | `app/lib/notifications/email/smtp.php` | Credentials from `settings.email_providers_config` |
| Postmark API | `app/lib/notifications/email/postmark.php` | Alternative provider |
| `SENDEMAIL()` | `app/lib/functions.php` | Low-level send |
| `NOTIFY` class | `app/lib/notify.php` | Orchestrates template + recipient |
| `triggerNotification()` | `app/lib/payment-gateway.php` | Event hook for booking lifecycle |

**Queue:** Synchronous (no queue abstraction observed).  
**Error handling:** PHP `error_log`; no structured `EMAIL_SEND_FAILED` category.

### Our OTA

| Mechanism | Path | Notes |
|-----------|------|-------|
| `OtaNotificationService` | `app/Services/Communication/OtaNotificationService.php` | `send()`, `resendCommunicationLog()` |
| `BookingCommunicationService` | `app/Services/Communication/BookingCommunicationService.php` | Customer Mailables |
| Mailables | `app/Mail/*` | Typed mail classes |
| Template registry | `app/Support/Emails/EmailTemplateRegistry.php` | Categories + OTA event mapping |
| Layout | `resources/views/emails/layouts/modern.blade.php` | Shared layout |

**Queue:** Laravel queue supported via standard Mail configuration (`config/queue.php`).  
**Error handling:** Communication logs; operational events; no raw supplier payload in mail.

---

## 3. Template Inventory

### Iati_new Templates

| Template path | Trigger | Recipients | Key variables |
|---------------|---------|------------|---------------|
| `app/views/notifications/emails/flights/booking.php` | Booking confirm (often via payment) | Customer | invoice_id, PNR, passengers, route, fare |
| `app/views/notifications/emails/flights/booking_pdf.php` | PDF attachment body | Customer | Itinerary details |
| `app/views/notifications/emails/flights/booking_cancellation.php` | `NOTIFY::cancellation()` | Customer | Cancellation status, booking ref |
| `app/views/notifications/emails/flights/itinerary_helpers.php` | Shared | — | `flight_voucher_normalize_itinerary()` |

### OTA Templates / Events

| Event (`OtaNotificationEvent`) | Typical Mailable / template | Recipients |
|-------------------------------|----------------------------|------------|
| `BookingRequestReceived` | `BookingRequestReceivedMail` | Customer |
| `BookingFareUpdatedRequiresAcceptance` | Operational template | Customer |
| `BookingUpdatedFareAccepted` | Operational template | Customer |
| `SupplierBookingCreated` | `BookingCommunicationService` | Customer + ops |
| `SupplierBookingFailed` | Operational template | Admin/staff |
| `SupplierReadinessFailed` | Operational template | Admin |
| `BookingManualReviewRequired` | Operational template | Admin/staff |
| `StaleSegmentRequiresNewSearch` | Operational template | Customer |
| `PnrItinerarySynced` / `PnrItinerarySyncFailed` | Operational template | Staff |
| `CancellationRequested` | Operational template | Admin + customer |
| `CancellationStatusChanged` | Operational template | Customer |
| `RefundRequested/Approved/Paid/Rejected` | Operational template | Customer + finance |
| `TicketIssued` | `TicketIssuedMail` | Customer |
| `PaymentVerified/Rejected` | Payment Mailables | Customer |

**Registry categories** (`EmailTemplateRegistry`): Booking, Payment, Cancellation/Refund, Supplier, Security, etc.

---

## 4. Trigger Point Comparison

| Trigger | Iati_new | OTA | Gap |
|---------|----------|-----|-----|
| Checkout submit | Webhook + draft booking | `BookingRequestReceived` | OTA ahead |
| Payment received | `triggerNotification('booking.payment_received')` | `PaymentVerified` / `PaymentRecorded` | Parity |
| Auto PNR after payment | `payment-gateway.php` → issue | Gated `createSupplierBooking` | OTA safer |
| PNR success | `booking.issued` | `SupplierBookingCreated` | Parity |
| PNR failure | `booking.issue_failed`, `booking.issue_exception` | `SupplierBookingFailed` | OTA structured |
| Revalidation fail | Not dedicated event | `SupplierReadinessFailed`, manual review | OTA ahead |
| Price change | Applied in helper; unclear email | `BookingFareUpdatedRequiresAcceptance` | OTA ahead |
| Cancel request | `NOTIFY::cancellation` + DB flag | `CancellationRequested` | Parity |
| Cancel complete | Partial via cancel API route | `CancellationStatusChanged` on process | OTA workflow clearer |
| Refund | `actions/refund.php` DB update | `BookingRefundService` full workflow | OTA ahead |
| Resend itinerary | `NOTIFY::resend()` | `resendCommunicationLog()` | Parity |

---

## 5. Template Variables (Safe Subset)

| Variable | Iati | OTA | Sensitive data risk |
|----------|------|-----|---------------------|
| Booking reference | Yes | Yes | Low |
| PNR | Yes | Yes (when issued) | Low |
| Passenger names | Yes | Yes | Medium — need consent/retention policy |
| Route / dates | Yes | Yes | Low |
| Fare / currency | Yes | Yes | Low |
| Payment status | Yes | Yes | Low |
| Supplier error raw | Possible in debug | **Redacted** | Iati: High in debug; OTA: Low |
| API tokens | Debug files only | Never | Iati: Critical in logs |
| Passport numbers | In booking emails if in template | Should use masked display | Medium — verify Blade partials |

---

## 6. Queue vs Sync

| Project | Default behavior | Recommendation |
|---------|-------------------|----------------|
| Iati_new | Sync `mail()` / PHPMailer | Move to queue if retained |
| OTA | Configurable Laravel queue | **Use queue for all operational mail in production** |

---

## 7. Error Handling if Email Fails

| Project | Behavior |
|---------|----------|
| Iati_new | `error_log`; booking still proceeds | Risk: customer unaware of PNR failure |
| OTA | `CommunicationLog` + operational event; booking state independent | Add explicit `EMAIL_SEND_FAILED` alert to admin |

---

## 8. Sensitive Data Leakage Assessment

| Risk | Iati_new | OTA |
|------|----------|-----|
| Full Sabre payload in email | Unlikely in templates; likely in debug | Prevented by design |
| API errors to customer | Possible if exception bubbles to UI | Sanitized messages |
| Tokens in email | No in templates; yes in debug files | No |
| Card/payment info | Payment gateway separate | PCI via payment provider |
| Passports in email | Template-dependent | Review `BookingItineraryReadyMail` variables |

---

## 9. Cancellation Flow Comparison

### Iati_new

```
Customer POST /api/flight/booking/request-cancellation
  → DB cancellation_request = 1
  → NOTIFY::cancellation()
  
Admin/automation POST flights/sabre/cancel (modules API)
  → Load booking by invoice_id
  → Auth via legacy modules credentials
  → Heuristic ticket check on booking_response JSON
  → DELETE /v1/trip/orders/cancelBooking { confirmationId }
  → Update booking_status
  → JSON response (may include error details)
```

**Gaps:** No getBooking; no isCancelable; wrong PCC source; ticketed same as unticketed.

### OTA

```
Customer/Agent/Guest → BookingCancellationController::store
  → BookingCancellationService::requestCancellation
  → AuditLog + CommunicationLog + CancellationRequested notification

Staff/Admin approve → approveCancellation
Staff/Admin process → processCancellation
  → If unticketed: BookingStatus::Cancelled locally
  → If ticketed: manual_void_refund_warning in meta; no Sabre HTTP
  → CancellationStatusChanged notification

Sabre HTTP (inspect only today):
  sabre:inspect-cancel-booking → SabreCancelBookingInspectProbe
    → getBooking context → candidate payloads → cancelBooking probe
    → verify with second getBooking
```

**Gaps:** Live `SabreBookingService::cancelBooking()` returns `pending_implementation`.

---

## 10. Refund / Void Workflow Comparison

### Iati_new (`actions/refund.php`)

- POST `invoice_id`, optional `refund_amount`, `refund_reason`
- Validates booking exists and is cancelled
- **No Sabre refund API**
- Updates DB: refund status fields, logs request
- Documents manual Red Workspace processing in comments

### OTA (`BookingRefundService`)

| Method | Purpose |
|--------|---------|
| `createRefund` | Customer/agent refund request |
| `approveRefund` | Staff approval |
| `markRefundPaid` | Finance marks paid |
| `rejectRefund` | Rejection with reason |

**Notifications:** `RefundRequested`, `RefundApproved`, `RefundPaid`, `RefundRejected` via `OtaNotificationEvent`.

**Sabre void/refund HTTP:** Explicitly excluded from `SabreTicketingEndpointDiscovery` and not implemented.

---

## 11. Ticketed vs Unticketed Decision Matrix

| Scenario | Iati_new | OTA | Best OTA behavior |
|----------|----------|-----|-------------------|
| Unticketed PNR, cancel requested | Live cancelBooking | Local cancel on process | Phase G: live cancel + local status |
| Ticketed PNR, cancel requested | Live cancelBooking (same payload) + error note | Manual warning only | Staff void workflow; no auto cancel HTTP |
| No PNR (never issued) | Local cancel only | Local cancel | Same |
| Partially flown | Not distinguished | Manual review | Block auto cancel; staff only |
| NDC order | Same cancel endpoint attempted | Not live | Cert NDC cancel separately in Phase J |
| Expired PNR | **Needs manual confirmation** | Manual review | Retrieve → show expired → no cancel HTTP |
| Cancel HTTP 200 but still active | No verify | Inspect detects ineffectual cancel | Always retrieve-after-cancel |

---

## 12. Required Cancel JSON Body Findings

### Iati_new (production)

```json
{ "confirmationId": "<PNR>" }
```

HTTP: `DELETE /v1/trip/orders/cancelBooking`

### OTA (inspect probes — `SabreCancelPayloadBuilder`)

Candidate shapes (cert only):

| Style | Body shape |
|-------|------------|
| confirmationId only | `{ "confirmationId": "..." }` |
| cancelData.cancelAll | `{ "cancelData": { "cancelAll": true }, "confirmationId": "..." }` |
| bookingId from getBooking | `{ "bookingId": "...", "cancelData": { "cancelAll": true } }` |
| orderItemIds | Targeted items when snapshot exposes IDs |

**Finding:** Iati uses minimal confirmationId only; OTA inspect has richer variants because getBooking often exposes `bookingId`, `isCancelable`, `bookingSignature`.

---

## 13. Email / Alert Matrix (Full)

| Trigger | Iati_new | OTA | Gap | Better template/action |
|---------|----------|-----|-----|------------------------|
| Booking draft created | Webhook | `BookingRequestReceived` | — | Keep OTA |
| Payment received | `booking.payment_received` | `PaymentVerified` | — | — |
| PNR created | `booking.issued` | `SupplierBookingCreated` | — | — |
| PNR failed | `booking.issue_failed` | `SupplierBookingFailed` | — | Customer: "We're confirming manually" |
| Revalidation failed | — | `SupplierReadinessFailed` | Iati gap | "This fare is no longer available" |
| Stale segment | — | `StaleSegmentRequiresNewSearch` | Iati gap | "Please search again" |
| Price changed | Inline price update | `BookingFareUpdatedRequiresAcceptance` | OTA ahead | Show old/new fare |
| Manual review | — | `BookingManualReviewRequired` | OTA ahead | Admin ops alert |
| Cancel requested | `NOTIFY::cancellation` | `CancellationRequested` | — | — |
| Cancel approved | Partial | `CancellationStatusChanged` | — | — |
| Cancel processed (unticketed) | cancel.php success | Local cancel + notification | Live Sabre gap | Phase G |
| Cancel failed (Sabre) | Exception to API client | — | OTA gap | Admin alert; customer generic message |
| Refund requested | refund.php DB | `RefundRequested` | OTA ahead | — |
| Refund paid | Manual | `RefundPaid` | — | — |
| Ticket issued | `booking.issued` | `TicketIssued` | — | — |
| Email send failed | error_log | — | Both gap | `EMAIL_SEND_FAILED` admin alert |

---

## 14. Recommended OTA Email Template List

### Customer-facing

1. `booking_request_received` — booking ref, next steps
2. `booking_confirmed_supplier` — PNR when issued (no raw GDS)
3. `fare_updated_acceptance_required` — old/new price, accept link
4. `fare_no_longer_available` — revalidation/stale failure
5. `booking_manual_processing` — PNR delayed, support contact
6. `cancellation_received` — request acknowledged
7. `cancellation_confirmed` — unticketed cancel complete
8. `cancellation_ticketed_manual` — ticketed; manual refund timeline
9. `refund_requested` / `refund_completed`
10. `ticket_issued` — ticket numbers if applicable

### Admin / staff operational

1. `supplier_booking_failed` — safe_summary category only
2. `supplier_readiness_failed` — revalidation context
3. `booking_manual_review_required` — reason code
4. `pnr_sync_failed`
5. `cancellation_sabre_failed` — for Phase G
6. `email_send_failed` — meta only

---

## 15. Recommended Admin Alert Triggers

| Trigger | Priority |
|---------|----------|
| `SupplierBookingFailed` after live PNR attempt | P0 |
| `BookingManualReviewRequired` | P0 |
| `SupplierReadinessFailed` on public checkout | P1 |
| `PnrItinerarySyncFailed` | P1 |
| `CancellationRequested` on ticketed booking | P1 |
| `PRICE_CHANGED` without customer acceptance within TTL | P2 |
| `EMAIL_SEND_FAILED` | P2 |
| Repeated `AUTH_INVALID_CLIENT` on connection | P0 |

---

## 16. Safe Customer-Facing Wording

### Revalidation failed / stale

> The fare you selected is no longer available at that price. Please run a new search to see current options.

### PNR failed (after payment)

> We've received your payment and booking request. Our team is confirming availability with the airline. You'll receive an update shortly — no further action needed unless we contact you.

### Cancel success (unticketed)

> Your booking has been cancelled. If you paid online, any eligible refund will be processed according to our refund policy.

### Cancel blocked (ticketed)

> This booking has been ticketed. Cancellation and refund must be handled by our support team. We've received your request and will contact you with options.

### Refund in progress

> Your refund request has been approved. Funds will be returned to your original payment method within [X] business days.

**Never show:** Sabre error codes, PCC, raw JSON, `ERR.2SG.SEC.NOT_AUTHORIZED`, segment UC codes.

---

## 17. Recommended OTA Cancellation/Refund Architecture

```
┌─────────────────┐
│ CancelRequest   │  Portal: customer/agent/guest
└────────┬────────┘
         ▼
┌─────────────────────────┐
│ Staff approve/process   │  BookingCancellationService
└────────┬────────────────┘
         ▼
┌─────────────────────────┐
│ DetermineEligibility    │  ticketed? pnr present? supplier?
└────────┬────────────────┘
         ├─ unticketed + PNR ──► RetrieveLatestBooking (getBooking)
         │                              ▼
         │                      isCancelable?
         │                              ▼
         │                      BuildCancelPayload (from snapshot)
         │                              ▼
         │                      SubmitCancel (gated live)
         │                              ▼
         │                      RetrieveAgain (verify)
         │                              ▼
         │                      UpdateLocalStatus + notify
         │
         └─ ticketed ──► Manual void/refund task
                               ▼
                        BookingRefundService workflow
                               ▼
                        Notify customer + finance
```

---

## 18. What Not to Copy from Iati

1. `NOTIFY` + debug file as operational alert channel
2. Live cancel without getBooking
3. Cancel credentials from global `modules` row
4. Returning full Sabre JSON from `issue.php` / `cancel.php` to API clients
5. Synchronous-only email with no failure escalation
6. `refund.php` as substitute for finance workflow (OTA already better)

---

*End of email, cancellation & refund audit.*
