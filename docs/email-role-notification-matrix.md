# EMAIL-ROLE-MATRIX-1 Role-Based Notification Matrix Audit

Date: 2026-06-13
Scope: audit and documentation only. No runtime code, migrations, events, listeners, supplier, PNR, payment execution, ticketing, cancellation execution, or template deletion changes were made.

## Audit Summary

The current verified universal booking email path is `BookingUniversalNotification` plus `BookingEmailPayloadFactory`, driven by `BookingCommunicationService` for customer booking emails and by `OtaNotificationService` for operational notifications that carry a `universal_email` payload.

Matrix counts after EMAIL-A3-SCHEDULER-CLOSURE-1:

- Implemented: 22
- Partial: 9
- Missing: 2
- Risk: 2

Status definitions:

- Implemented: a clear trigger, recipient resolver, mailable/service, and `communication_logs` write exist for the scenario.
- Partial: a related email/log path exists, but the exact role, event, payload, or coverage is incomplete.
- Missing: no implemented email route was found for the scenario.
- Risk: a send exists but has duplicate-delivery, wrong-recipient, wrong-payload, or critical-routing risk.

## Files Inspected

- `app/Mail`
- `app/Services/Communication`
- `app/Support/Emails`
- `app/Enums/OtaNotificationEvent.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Booking/BookingService.php`
- `app/Services/Payments/BookingPaymentService.php`
- `app/Services/Payments/BookingRefundService.php`
- `app/Services/Documents/BookingDocumentService.php`
- `app/Services/Bookings/BookingCancellationService.php`
- `app/Services/Suppliers/SupplierBookingService.php`
- `app/Services/Suppliers/TicketingService.php`
- `app/Services/Agents/AgentWalletService.php`
- `resources/views/emails`
- `config/mail.php`
- `config/ota.php`
- `summary.md`

Absent folders in this working tree: `app/Notifications`, `app/Events`, `app/Listeners`, and `app/Observers`.

## Role-Based Notification Matrix

| Scenario ID | Scenario name | Recipient role | Trigger/status/event | Current source file/method | Current mailable/service | Recipient resolver | communication_logs status | Status: Implemented / Partial / Missing / Risk | Risk notes | Recommended next action |
|---|---|---|---|---|---|---|---|---|---|---|
| C1 | Booking Request Received / pending request | Customer / guest / registered | `BookingStatus::Pending`, `booking_request_received` | `BookingService::submitBookingRequest`; `BookingCommunicationService::sendBookingRequestReceived` | `BookingUniversalNotification` via `BookingEmailPayloadFactory::bookingReceived` | `BookingCommunicationService::resolveRecipient` uses booking contact, customer, then lead passenger name | Email log created as queued/sent/skipped/failed with `recipient_type=customer`; operational log also created | Implemented | Operational alert also fires, but customer path is direct and logged. | Keep as baseline pattern for new customer booking emails. |
| C2 | Booking Confirmed / PNR hold active / pending_payment_or_ticketing | Customer / guest / registered | `BookingStatus::Confirmed`, related hold/payment pending states | `BookingService::changeStatus`; `BookingCommunicationService::sendBookingStatusChanged`; `sendBookingConfirmed` | `BookingUniversalNotification` via `statusChanged` | Customer booking contact/customer resolver | Email logs for status change and confirmed events | Partial | Booking confirmed exists, but exact `pending_payment_or_ticketing` / hold-active routing is not a distinct scenario. | Add explicit event mapping for hold active / payment pending in EMAIL-ROLE-MATRIX-2. |
| C3 | Ticket Issued / ticketed_complete | Customer / guest / registered | `BookingStatus::Ticketed`, `ticket_issued` | `TicketingService` success path; `BookingCommunicationService::sendTicketIssued` | `BookingUniversalNotification` via `ticketIssued` | Customer booking contact/customer resolver | Email log created for `ticket_issued`; operational log also created | Implemented | Direct customer path is present. | Keep, then align agent/agency ticket recipients separately. |
| C4 | Booking Deferred to Manual Review / needs_review / staff_review_required | Customer / guest / registered | `booking_manual_review_required`, supplier manual review, `fare_review`, review status labels | `SupplierBookingService` failure branch; `BookingCommunicationService::notifyManualReviewRequired`; `sendBookingStatusChanged` review-label guard | `BookingUniversalNotification` via `BookingEmailPayloadFactory::customerManualReviewRequired` | Direct booking customer resolver | Customer email log `customer_manual_review_required`; operational alert logs separately | Implemented | Customer copy is reassuring and does not expose supplier/Sabre raw errors. Non-enum labels are detected only when supplied as a status label; no new booking statuses were added. | Keep operational manual-review alert separate from customer copy. |
| C5 | Payment Verified | Customer / guest / registered | `payment_verified` | `BookingPaymentService::verifyPayment`; `BookingCommunicationService::sendPaymentVerified` | `BookingUniversalNotification` via `paymentVerified`; operational `OtaNotificationService` also fires internally | Direct customer resolver; operational buckets are now `platform_admin`, `assigned_staff` | Direct email log and operational email log | Implemented | EMAIL-ROLE-MATRIX-2A removed customer/agent operational buckets, so the direct booking communication email is the only customer-facing payment verified path. | Keep customer path direct; add future agency/agent payment notifications only through explicitly non-duplicating buckets/events. |
| C6 | Payment Rejected | Customer / guest / registered | `payment_rejected` | `BookingPaymentService::rejectPayment`; `BookingCommunicationService::sendPaymentRejected` | `BookingUniversalNotification` via `paymentRejected`; operational `OtaNotificationService` also fires internally | Direct customer resolver; operational buckets are now `platform_admin`, `assigned_staff` | Direct email log and operational email log | Implemented | EMAIL-ROLE-MATRIX-2A removed customer/agent operational buckets, so the direct booking communication email is the only customer-facing payment rejected path. | Keep customer path direct; add future agency/agent payment notifications only through explicitly non-duplicating buckets/events. |
| C7 | Booking Cancelled | Customer / guest / registered | `booking_cancelled`, cancellation processed/status changed | `BookingCommunicationService::sendBookingStatusChanged`; `BookingCancellationService::notifyCancellation` | `BookingUniversalNotification` via `BookingEmailPayloadFactory::customerCancellationUpdate` | Direct booking customer bucket for cancellation workflow; internal sends use separate buckets | Customer/internal/agency sends log separately with recipient buckets and skipped bucket metadata | Implemented | Customer cancellation no longer receives `adminCancellationAlert`; internal cancellation alerts retain operational payload/context. | Keep customer/internal cancellation payload split. |
| C8 | Refund Update if implemented | Customer / guest / registered | `refund_requested`, `refund_approved`, `refund_paid`, `refund_rejected` | `BookingRefundService::notifyRefund` | `BookingUniversalNotification` via status-specific `BookingEmailPayloadFactory::customerRefundUpdate` / `agencyRefundUpdate` | Customer `booking_customer`; B2B `booking_agent`, `agency_admin`, `agent_staff_creator` | Operational email logs include event key, `booking_refund_id`, `refund_status`, recipient buckets, skipped/deduplicated bucket metadata | Implemented | Customer and B2B refund emails now use distinct copy per refund status; internal finance notes and rejection reasons are not exposed to customers. | Keep monitoring skipped `agent_staff_creator` bucket for historical bookings. |
| C9 | Itinerary / invoice / ticket attachment email if implemented | Customer / guest / registered | Ticket itinerary document generated or tickets exist | `BookingDocumentService::generateTicketItinerary`; `BookingCommunicationService::sendItineraryReady` | `BookingUniversalNotification` via `itineraryReady`, optional PDF attachment | Customer booking contact/customer resolver | Email log `itinerary_ready` with `has_attachment`, `document_id`, and `recipient_type=customer` | Implemented | Invoice/payment receipt PDFs are generated, but only ticket itinerary is emailed automatically. | Keep itinerary path; decide separately whether invoices/receipts should auto-email. |
| A1 | Agency booking created by agency admin | Agency admin / agent admin | Agency-admin or agent-admin created booking | `BookingService::submitBookingRequest`; `BookingCommunicationService::sendBookingRequestReceived` | `OtaNotificationService` with B2B-safe `BookingEmailPayloadFactory::b2bBookingCreated` | B2B buckets `booking_agent`, `agency_admin`; customer buckets deduplicated | Separate booking-created B2B log uses `notification_type=booking_created_b2b`; sent/skipped logs include `routing_policy=A1_agency_or_agent_admin`, creator id/role/source, recipient buckets, and de-duplicated customer buckets | Implemented | Customer `booking_request_received` remains the only customer-facing booking-created email. Agency-side actor is required; customer/guest-created bookings do not enter the B2B route. | Monitor skipped `booking_agent` / `agency_admin` buckets when agency pivot users are missing. |
| A2 | Agent staff booking created | Agency admin / agent admin / agent staff creator | Booking created by agent staff | `BookingService::submitBookingRequest`; `BookingCommunicationService::sendBookingRequestReceived` | `OtaNotificationService` with B2B-safe `BookingEmailPayloadFactory::b2bBookingCreated` | B2B buckets `booking_agent`, `agency_admin`, `agent_staff_creator`; `agent_staff_creator` from direct actor first, then stored `booking.meta.creator_context.agent_staff_creator_user_id` | Separate booking-created B2B log uses `notification_type=booking_created_b2b`; sent/skipped logs include `routing_policy=A2_agent_staff`, `agent_staff_creator_source`, creator id/role/source, skipped bucket reason when creator missing, and de-duplicated customer buckets | Implemented | Missing/unresolvable `agent_staff_creator` no longer blocks reliable `booking_agent` / `agency_admin` delivery. Customer route remains separate and non-duplicated. | Monitor skipped `agent_staff_creator` logs for historical/no-context bookings. |
| A3 | Staff booking activity summary to agency admin | Agency admin / agent admin | Daily scheduler + manual/on-demand summary | `AdminReportMailerService::sendAgencyBookingActivitySummary()`; `ota:send-agency-booking-activity-summary` (`--agency=`, `--all-active-agencies`, `--from=`, `--to=`, `--force` manual only); scheduler `routes/console.php` daily 07:10 with `--all-active-agencies` (no `--force`) gated by `AGENCY_BOOKING_ACTIVITY_SUMMARY_DAILY_ENABLED` | `OtaNotificationService` with `BookingEmailPayloadFactory::agencyBookingActivitySummary()` | `agency_admin` only | Operational email log `notification_type=agency_booking_activity_summary` with period/metrics/sample_refs metadata; dedupe per agency + period unless `--force` | Implemented | Read-only digest from `bookings` scoped by `agency_id`; no per-event duplicate of A1/A2/A5/A7. Scheduler iterates active agencies only; no platform-wide consolidated payload. | Monitor skipped `agency_admin` buckets when agency pivot admins are missing. |
| A4 | Wallet / credit / deposit low balance warning if wallet exists | Agency admin / agent admin | Agent wallet/deposit threshold | `AgentWalletService::notifyDepositEvent` for deposit events only | `OtaNotificationService` | Deposit events use resolver policy; no low-balance event found | Operational logs for deposit sends | Partial | Wallet/deposit email infrastructure exists, but low-balance warning scenario was not found. | Add explicit low-balance threshold event and recipient policy. |
| A5 | Agency payment/payment proof update if implemented | Agency admin / agent admin / agent staff creator | `payment_proof_submitted`, `payment_verified`, `payment_rejected` | `BookingPaymentService::submitPaymentProof` / `verifyPayment` / `rejectPayment`; `BookingCommunicationService::sendPaymentSubmitted` / `sendPaymentVerified` / `sendPaymentRejected` | B2B-safe `agencyPaymentProofSubmitted` / `agencyPaymentVerified` / `agencyPaymentRejected`; internal S6/C5/C6 paths unchanged | B2B buckets `booking_agent`, `agency_admin`, `agent_staff_creator` when agency/agent booking context resolves; customer buckets deduplicated | Separate internal, customer direct, and B2B operational logs with `notification_type=payment_proof_submitted_b2b` / `payment_verified_b2b` / `payment_rejected_b2b`, `booking_payment_id`, `payment_event`, recipient/skipped/deduplicated bucket metadata | Implemented | Customer payment verified/rejected remains direct-only through `BookingCommunicationService`. Internal S6 payment proof alert preserved. `payment_recorded` remains internal-only (finance/admin/staff) without B2B route. Missing `agent_staff_creator` does not block reliable `booking_agent` / `agency_admin` delivery. | Monitor skipped `agent_staff_creator` bucket for historical/no-context agency bookings. |
| A6 | Ticket issued for agency booking | Agency admin / agent admin | `ticket_issued` on agency/agent booking | `TicketingService`; `BookingCommunicationService::sendTicketIssued` | Customer direct `ticketIssued`; B2B-safe `b2bTicketIssued`; internal alert split | Direct customer resolver; B2B buckets `booking_agent`, `agency_admin`, `agent_staff_creator`; internal buckets `platform_admin`, `assigned_staff` | Customer direct log plus separate operational/B2B logs | Implemented | Customer direct path remains the only customer-facing ticket-issued email. Agent-staff only resolves when actor/context exists. | Monitor skipped `agent_staff_creator` bucket until booking creator context is persisted broadly. |
| A7 | Cancellation/refund update for agency booking | Agency admin / agent admin | Cancellation/refund events | `BookingCancellationService::notifyCancellation`; `BookingRefundService::notifyRefund` | B2B-safe `agencyCancellationUpdate` and status-specific `agencyRefundUpdate` | B2B buckets `booking_agent`, `agency_admin`, `agent_staff_creator` | Separate customer, internal, and B2B communication logs; missing buckets skipped safely | Implemented | Refund B2B emails now use status-specific copy per `refund_requested` / `refund_approved` / `refund_paid` / `refund_rejected`; customer buckets are deduplicated on B2B sends. | Keep actor/context gap documented for staff-created bookings. |
| AS1 | Own booking created | Agent staff | Agent-staff booking create | `BookingService::submitBookingRequest`; `BookingCommunicationService::sendBookingRequestReceived` | `OtaNotificationService` with B2B-safe `BookingEmailPayloadFactory::b2bBookingCreated` | Direct active agent-staff actor first; fallback to stored active `booking.meta.creator_context.agent_staff_creator_user_id`; B2B buckets `booking_agent`, `agency_admin`, `agent_staff_creator` | Separate booking-created B2B log uses `notification_type=booking_created_b2b`; sent/skipped logs include `routing_policy=A2_agent_staff`, recipient buckets, skipped reasons, `agent_staff_creator_source`, creator id/role/source, and de-duplicated customer buckets | Implemented | Customer `booking_request_received` remains the only customer-facing booking-created email. Missing/unresolvable `agent_staff_creator` is skipped and logged without blocking reliable `booking_agent` / `agency_admin` delivery. | Monitor skipped `agent_staff_creator` logs for historical/no-context bookings. |
| AS2 | Own booking manual review update | Agent staff | Manual review required | `BookingCommunicationService::notifyManualReviewRequired` | `OtaNotificationService` with customer-safe/B2B universal payload | Direct agent-staff actor first; fallback to stored `booking.meta.creator_context.agent_staff_creator_user_id` | Sent or skipped log includes `agent_staff_creator_source` and skipped bucket reason | Implemented | Implemented for new bookings with persisted agent-staff creator context and for direct agent-staff actor sends; historical bookings without reliable context are skipped safely. | Monitor skipped `agent_staff_creator` logs for historical/no-context bookings. |
| AS3 | Own booking ticket issued | Agent staff | Ticket issued | `TicketingService`; `BookingCommunicationService::sendTicketIssued` | `b2bTicketIssued` | Direct agent-staff actor first; fallback to stored `booking.meta.creator_context.agent_staff_creator_user_id` | Sent or skipped log includes `agent_staff_creator_source` and skipped bucket reason | Implemented | Ticketing success can resolve the original agent-staff creator from stored booking context even though the ticketing call does not pass that actor. Historical bookings without reliable context are skipped safely. | Monitor skipped `agent_staff_creator` logs for historical/no-context bookings. |
| AS4 | Own booking cancellation/refund update | Agent staff | Cancellation/refund events | `BookingCancellationService`; `BookingRefundService` | `agencyCancellationUpdate`; status-specific `agencyRefundUpdate` | Direct agent-staff actor first; fallback to stored `booking.meta.creator_context.agent_staff_creator_user_id` | Sent or skipped log includes `agent_staff_creator_source` and skipped bucket reason | Implemented | Refund B2B emails now use status-specific copy per refund event; customer refund emails remain on the separate `booking_customer` path only. | Monitor skipped `agent_staff_creator` logs for historical/no-context bookings. |
| AS5 | Login/security emails if implemented or planned | Agent staff | Agent login success/failure/security | `AuthenticatedSessionController`; `LoginRequest`; `AuthSecurityEmailNotificationService` | `OtaNotificationService` | `logged_in_user` for agent/agent-staff success; user alert on threshold for failed login | Operational email log + audit log | Implemented | Agent staff shares `agent_login_success` when `NOTIFY_AGENT_LOGIN=true`; failed login alert uses threshold + cooldown to known active users only. | Monitor skipped auth email logs for cooldown/threshold cases. |
| S1 | Manual review queue alert | Platform staff | `booking_manual_review_required` | `SupplierBookingService`; `BookingCommunicationService::notifyManualReviewRequired` | `OtaNotificationService` with universal manual-review payload | Buckets `assigned_staff`, `operations_queue`, `platform_admin` | Operational email log includes resolved and skipped buckets | Implemented | `operations_queue` uses existing support email fallback only; no new inbox is invented. | Configure a dedicated queue inbox later if product wants one. |
| S2 | Staff review required alert | Platform staff | `staff_review_required` / manual review | `BookingCommunicationService::notifyManualReviewRequired`; `sendBookingStatusChanged` review-label guard | `BookingEmailPayloadFactory::staffReviewRequired` via `OtaNotificationService` | `assigned_staff`, `operations_queue`, `platform_admin` | Operational email log `notification_type=staff_review_required` with `staff_review_reason`, `supplier_booking_status`, `ticketing_status`, recipient/skipped/deduplicated bucket metadata | Implemented | Distinct internal staff-review path separate from customer `customer_manual_review_required` and B2B `booking_manual_review_b2b`; duplicate internal alerts skipped per booking + notification type. | Monitor skipped staff buckets when assignee/ops inbox is unset. |
| S3 | Supplier/ticketing failure alert | Platform staff | `supplier_booking_failed`, `supplier_readiness_failed`, `supplier_search_failed`, `supplier_order_failed`, `ticketing_failed`, `ticketing_not_supported` | `SupplierBookingService` failure branch; `TicketingService` failure branch; `BookingCommunicationService::notifySupplierFailure` / `notifyTicketingFailure` | `OtaNotificationService` with `supplierFailureAlert`, `ticketingFailureAlert`, or `ticketingNotSupportedAlert` | Buckets `assigned_staff`, `operations_queue`, `platform_admin`; customer/B2B buckets deduplicated | Operational email log with `notification_type`, `failure_type`, safe failure reason/classification, supplier/ticketing status, attempt id when present, recipient/skipped/deduplicated bucket metadata | Implemented | Internal-only staff-safe payloads; no raw Sabre/GDS/XML/JSON, credentials, or customer/B2B routing. Readiness/search/order types classify from safe `error_code` on supplier booking failure when applicable. P1 credential/link failure remains separate. | Keep P1 dedicated source event as future work. |
| S4 | Ticketing queue alert | Platform staff | Supplier booking enters `pending_ticketing` | `SupplierBookingService` success path; `BookingCommunicationService::sendSupplierBookingCreated` | `OtaNotificationService` with `pnrCreated` universal payload | Buckets `assigned_staff`, `operations_queue`, `platform_admin` | Operational email log with deduplicated customer bucket note | Implemented | Uses existing supplier-created/pending-ticketing source; no ticketing execution behavior changed. | If product needs a distinct event key, add it in a later schema/event taxonomy pass. |
| S5 | Cancellation request alert | Platform staff | `cancellation_requested` | `BookingCancellationService::requestCancellation`; `notifyCancellation` | Internal `cancellationRequested`; customer-safe cancellation payload split separately | Internal buckets `assigned_staff`, `operations_queue`, `platform_admin`; customer bucket split | Separate customer/internal/B2B logs | Implemented | Customer/internal split prevents admin payload from reaching customers. | Keep queue fallback limited to existing support email. |
| S6 | Payment proof submitted alert | Platform staff | `payment_proof_submitted` | `BookingPaymentService::submitPaymentProof`; `BookingCommunicationService::sendPaymentSubmitted` | `OtaNotificationService` | Buckets `finance`, `assigned_staff`, `operations_queue`, `platform_admin` | System log plus operational email log | Implemented | Queue fallback uses existing support email only. | No customer payment duplicate path added. |
| S7 | Refund action required alert if implemented | Platform staff | `refund_requested` | `BookingRefundService::notifyRefund` | `OtaNotificationService` with `BookingEmailPayloadFactory::refundActionRequired` | Buckets `finance`, `assigned_staff`, `operations_queue`, `platform_admin`; customer/B2B buckets deduplicated | Operational email log with `notification_type=refund_action_required`, `booking_refund_id`, `refund_status=refund_requested`, recipient/skipped/deduplicated bucket metadata | Implemented | Dedicated internal refund action alert on refund request only; customer `customerRefundUpdate` and B2B `agencyRefundUpdate` paths remain separate. Duplicate internal alerts for the same `booking_refund_id` are skipped when already logged. | Keep queue fallback limited to existing support/finance email configuration. |
| P1 | Critical Sabre/GDS credential/auth/link failure | Platform admin | Supplier credential/auth/link failure | `BookingCommunicationService::notifySupplierFailure()` / `notifyTicketingFailure()` when safe `error_code` or `SabreHostErrorClassifier::REASON_ENTITLEMENT_OR_SECURITY` classifies credential/auth/link failure | `OtaNotificationService` with `BookingEmailPayloadFactory::supplierConnectionAuthFailureAlert()` | `platform_admin` only; customer/B2B/staff/ops buckets deduplicated | Operational log `notification_type=supplier_connection_auth_failed` with supplier connection id, safe classification, recipient/skipped/deduplicated bucket metadata | Implemented | Dedicated platform-admin-only alert separate from S3 generic supplier failure routing; deduped per agency + `supplier_connection_id` within 60 minutes via `communication_logs`. No credentials/tokens/raw payloads in email. | Monitor skipped platform_admin bucket when admin recipients unset; auth failures during search-only paths may not reach booking failure hooks. |
| P2 | Daily transaction/revenue digest if implemented | Platform admin | Daily admin report | `AdminReportMailerService::sendDailyReport` | `OtaNotificationService` | Default/admin scope | Operational email log | Implemented | Daily report exists; revenue fields are basic gross/unpaid aggregates. | Keep; later enhance metrics only if reporting scope asks. |
| P3 | Failed PNR ratio/manual review digest if implemented | Platform admin | PNR failure/manual review digest | `AdminReportMailerService::sendPnrManualReviewDigest()`; `ota:send-pnr-manual-review-digest` (manual/on-demand) | `OtaNotificationService` with `BookingEmailPayloadFactory::pnrManualReviewDigest()` | `platform_admin` only | Operational email log `notification_type=pnr_manual_review_digest` with period/metrics/sample_refs metadata | Partial | Read-only digest from `bookings` + `supplier_booking_attempts`; no per-failure email (S2/S3 remain event-level). No scheduler/cron hook added. | Add scheduler hook in a later reporting sprint if product wants automated P3 digests. |
| P4 | Agency wallet/deposit summary if implemented | Agency admin | Wallet/deposit summary | `AgentWalletService::sendAgencyWalletDepositSummary()` (manual/on-demand); read-only data from `agencyWalletSummary()` | `OtaNotificationService` with `BookingEmailPayloadFactory::agencyWalletDepositSummary()` | `agency_admin` only | Operational email log `notification_type=agency_wallet_deposit_summary` with agency-scoped balance/pending deposit counts | Partial | Payload/send method implemented using existing read-only wallet summary data; no scheduler/cron trigger added (manual call only). Agency-scoped only — no cross-agency or platform-wide ledger exposure. | Add scheduler hook in a later reporting sprint if product wants automated wallet summaries. |
| P5 | Critical system/security/auth alert if implemented | Platform admin | Security/auth/system critical | `AuthSecurityEmailNotificationService`; privileged failed login admin bucket | `OtaNotificationService` | Failed sensitive login to admin bucket; success to logged-in user; new-device to logged-in user | Operational email log + audit log | Partial | Privileged auth alerts implemented with threshold/cooldown; AU3 new-device detection implemented via `auth.login_success` audit metadata. | Broad critical system/security event matrix beyond login remains out of scope. |
| AU1 | Login success email | Auth/security user | Login success by account type | `AuthenticatedSessionController`; `AuthSecurityEmailNotificationService::notifyLoginSuccess` | `OtaNotificationService` | `logged_in_user` for all enabled roles | Operational email log; skipped reasons in app log | Implemented | Customer gated by `NOTIFY_CUSTOMER_LOGIN` (default off); admin/staff default on; agent default off; agent staff uses agent success event; success cooldown default 15 minutes. | Enable customer login alerts only when product approves. |
| AU2 | Failed login attempt email | Auth/security user/admin | Failed login threshold alert | `LoginRequest`; `AuthSecurityEmailNotificationService::notifyFailedLogin` | `OtaNotificationService`; `AuditLog` | Admin bucket for agency/platform admin failures; `logged_in_user` for customer/staff/agent/agent-staff when enabled | Operational email log + audit log | Implemented | Unknown addresses never emailed; generic auth.failed UI preserved; threshold default 3 attempts; email cooldown default 60 minutes per user. | Tune threshold/cooldown via env if noisy. |
| AU3 | New device/suspicious login email | Auth/security user | New device / suspicious login | `AuthenticatedSessionController`; `AuthSecurityEmailNotificationService::notifyNewDeviceLogin` | `OtaNotificationService`; `AuditLog` (`auth.login_success`, `auth.new_device_login`) | `logged_in_user` only for all active account types | Operational email log + audit log + skipped reasons in app log | Implemented | Conservative detection: prior `auth.login_success` audit required; triggers when normalized user-agent fingerprint differs; first login after deploy not alerted; cooldown default 60 minutes per user+fingerprint; gated by `NOTIFY_AUTH_NEW_DEVICE_LOGIN` (default on). No session/password/token changes. | Historical logins before AU3 lack `auth.login_success` rows until next successful login seeds audit trail. |
| AU4 | Password reset email | Auth/security user | Password reset link request | `PasswordResetLinkController::store`; Laravel password broker | Framework password reset notification | Laravel framework notifiable resolver | No `communication_logs` coverage found | Partial | Sends via framework path, not universal/modern operational system and not logged in `communication_logs`. | Document as framework-managed or wrap with logging in a later auth email sprint. |
| AU5 | Email verification email | Auth/security user | `Registered` event / verification resend | `AppServiceProvider::boot`; Laravel `SendEmailVerificationNotification` | Laravel `VerifyEmail` notification | Laravel framework notifiable resolver | No `communication_logs` coverage found | Partial | Framework-managed; not logged or templated through agency communication settings. | Keep framework-managed or add logging wrapper if auditability is required. |
| AU6 | Registration/welcome email | Auth/security customer/admin | Customer registration / Google onboarding | `RegisteredUserController::sendCustomerWelcomeEmail`; `sendAdminNewCustomerEmail`; `GoogleOnboardingController` for Google welcome | `CustomerWelcomeMail`, `AdminNewCustomerSignupMail`, `GoogleCustomerWelcomeMail` | Direct `Mail::to`; admin recipients support-email fallbacks | No `communication_logs` coverage found | Risk | Direct mailables bypass `OtaNotificationService`, agency template settings, and delivery logs. | Move welcome/admin signup sends into logged notification service or explicitly mark them framework/direct exceptions. |

## Current Implemented Email Flows

- Customer booking request received: `BookingService::submitBookingRequest` calls `BookingCommunicationService::sendBookingRequestReceived`, which sends `BookingUniversalNotification` and logs to `communication_logs`.
- Customer booking status/confirmation: `BookingService::changeStatus` calls `sendBookingStatusChanged` and, for confirmed status, `sendBookingConfirmed`.
- Customer payment verified/rejected: `BookingPaymentService` calls the corresponding `BookingCommunicationService` methods.
- Customer ticket issued: `TicketingService` success path calls `BookingCommunicationService::sendTicketIssued`.
- Customer itinerary ready with optional PDF: `BookingDocumentService::generateTicketItinerary` calls `BookingCommunicationService::sendItineraryReady`.
- Operational booking/supplier/payment/cancellation/refund/report/auth notifications: `OtaNotificationService` resolves recipients, renders modern/universal layouts, and writes `communication_logs`.
- Privileged auth success/failure/new-device notifications: `AuthenticatedSessionController` and `LoginRequest` call `AuthSecurityEmailNotificationService`, which sends through `OtaNotificationService` for configured role events with threshold/cooldown protection.
- Daily/weekly/monthly admin reports and monthly finance ledger: `AdminReportMailerService` sends through `OtaNotificationService`.

## Missing Email Flows

- A4 low-balance wallet/deposit warning (threshold event not found).
- AU4/AU5/AU6 framework/direct auth emails outside operational logging path.

## AUTH-SECURITY-EMAIL-1 Login Security Email Closure

Date: 2026-06-13

Scope: auth/security login email routing, universal payload support, audit/app logging, and documentation only. No booking lifecycle, Sabre API, PNR/ticketing/cancellation/refund/payment execution, checkout business rule, migration, or new user role changes were made.

### Login Success Coverage

| Account type | Event key | Config gate | Recipient bucket | Status |
|---|---|---|---|---|
| Customer | `customer_login_success` | `NOTIFY_CUSTOMER_LOGIN` (default off) | `logged_in_user` | Implemented |
| Platform admin / agency admin | `admin_login_success` | `NOTIFY_ADMIN_LOGIN` (default on) | `logged_in_user` | Implemented (preserved) |
| Staff | `staff_login_success` | `NOTIFY_STAFF_LOGIN` (default on) | `logged_in_user` | Implemented (preserved) |
| Agent / agent staff | `agent_login_success` | `NOTIFY_AGENT_LOGIN` (default off) | `logged_in_user` | Implemented |

Success emails include account name, timestamp, IP, user agent (truncated), and reset-password/support guidance. No session id, token, password, or secret values are included. Success sends use `AUTH_LOGIN_SUCCESS_EMAIL_COOLDOWN_MINUTES` (default 15) per user.

### Failed Login Coverage

| Account type | Event key | Config gate | Recipient bucket | Status |
|---|---|---|---|---|
| Platform admin / agency admin | `login_failed_sensitive` | `NOTIFY_FAILED_ADMIN_LOGIN` (default on) | `admin` | Implemented (preserved, now thresholded) |
| Customer / staff / agent / agent staff | `login_failed_alert` | `NOTIFY_FAILED_LOGIN` (default on) | `logged_in_user` | Implemented |

Failed-login emails are sent only for known active users after `AUTH_FAILED_LOGIN_EMAIL_THRESHOLD` attempts (default 3) or at lockout threshold (5). Unknown/nonexistent addresses are never emailed. UI still returns generic `auth.failed`. Email cooldown uses `AUTH_FAILED_LOGIN_EMAIL_COOLDOWN_MINUTES` (default 60) per user. Privileged admin failures still write `audit_logs.action=auth.admin_login_failed`; user alerts write `auth.login_failed`.

### Partial / Missing

- AU4/AU5/AU6 framework/direct auth emails remain outside this pass.

### Config Gates

- `NOTIFY_CUSTOMER_LOGIN`
- `NOTIFY_AGENT_LOGIN`
- `NOTIFY_STAFF_LOGIN`
- `NOTIFY_ADMIN_LOGIN`
- `NOTIFY_FAILED_ADMIN_LOGIN`
- `NOTIFY_FAILED_LOGIN`
- `AUTH_FAILED_LOGIN_EMAIL_THRESHOLD`
- `AUTH_FAILED_LOGIN_EMAIL_COOLDOWN_MINUTES`
- `AUTH_LOGIN_SUCCESS_EMAIL_COOLDOWN_MINUTES`
- `NOTIFY_AUTH_NEW_DEVICE_LOGIN`
- `AUTH_NEW_DEVICE_EMAIL_COOLDOWN_MINUTES`

## AUTH-AU3-NEW-DEVICE-SUSPICIOUS-LOGIN-1 New Device Login Closure

Date: 2026-06-13

Scope: auth/security new-device email routing, audit logging, and documentation only. No login authentication behavior, password/session/token, 2FA, role, migration, booking, Sabre, payment, or wallet changes.

### Detection policy

| Rule | Behavior |
|---|---|
| Prior login required | Query latest `audit_logs.action=auth.login_success` for the authenticated user before recording the current login |
| First login | No alert when no prior successful-login audit exists (seeds `auth.login_success` for future detection) |
| New device trigger | Prior audit exists and normalized user-agent fingerprint (lowercased, whitespace-collapsed, max 250 chars) differs from current |
| IP-only change | Does not trigger when user-agent fingerprint is unchanged |
| Login behavior | No block, no session invalidation, no auth result change |

### Notification

| Field | Value |
|---|---|
| Event key | `auth_new_device_login` |
| Payload type | `auth_new_device_login` |
| Recipient | `logged_in_user` only (all active account types) |
| Config gate | `NOTIFY_AUTH_NEW_DEVICE_LOGIN` (default on) |
| Cooldown | `AUTH_NEW_DEVICE_EMAIL_COOLDOWN_MINUTES` (default 60) per user + user-agent fingerprint hash |
| Audit actions | `auth.login_success` (every success), `auth.new_device_login` (when email sent) |

Payload includes account name, timestamp, IP, truncated user agent, and support guidance. No password, session id, remember token, reset token, cookie, raw headers, or geolocation.

## Duplicated Or Overlapping Email Flows

- `PaymentVerified` and `PaymentRejected` duplicate customer/agent delivery was fixed in EMAIL-ROLE-MATRIX-2A: direct `BookingCommunicationService` remains the only customer-facing path, while operational `OtaNotificationService` routes only to `platform_admin` / `assigned_staff` and logs de-duplicated `customer_party` / `agent_booking` buckets.
- Booking status and booking confirmed can both fire for a confirmed status transition, creating two customer-facing emails for one state change.
- Ticket issued and itinerary ready can both notify the customer after ticketing if a ticket-issued email is sent and then a ticket itinerary PDF is generated.
- Legacy booking mailables and views still exist under `app/Mail` and `resources/views/emails/bookings`, while current verified booking emails use `BookingUniversalNotification` and `resources/views/emails/booking/universal-notification.blade.php`.
- Cancellation/refund operational notifications use universal payloads with role-specific copy; refund customer/B2B emails are status-specific per `refund_requested` / `refund_approved` / `refund_paid` / `refund_rejected`. Refund requested also sends a separate internal `refund_action_required` platform-staff alert (finance, assigned staff, operations queue, platform admin) without duplicating customer/B2B refund emails.

## Unsafe Or Hardcoded Recipient Risks

- There is no separate `agency_admin` gap for booking-created B2B routing; A1/A2 now use the `agency_admin` bucket when an agency-side creator is present. Other lifecycle gaps may still exist outside booking-created.
- Agent staff identity is now represented for booking-created, ticketed, cancellation, and refund B2B routes through `agent_staff_creator`, but only when direct/stored creator context resolves to an active agent-staff user.
- Cancellation status updates no longer route `adminCancellationAlert` to `customer_party` / `booking_customer`; customer cancellation uses dedicated customer-safe payloads only (resolved in EMAIL-CANCEL-CUSTOMER-PAYLOAD-SAFETY-1).
- Critical supplier credential/auth/link failures now have a dedicated platform-admin-only alert (`supplier_connection_auth_failed`) separate from generic S3 supplier failure alerts (EMAIL-P1-P4-PLATFORM-OPS-ALERTS-1).
- Direct auth/welcome mailables bypass `OtaNotificationService` and do not write `communication_logs`.

## Missing communication_logs Coverage

- Laravel password reset emails are framework-managed and were not found writing `communication_logs`.
- Laravel email verification emails are framework-managed and were not found writing `communication_logs`.
- Customer welcome, Google customer welcome, and admin new-customer signup mailables send directly through `Mail::to` and were not found writing `communication_logs`.
- Agent staff/agency admin booking-created scenarios now log through the A1/A2 B2B route; historical bookings without agency-side creator context still skip the B2B route safely.
- Some refund/cancellation state changes write system logs, but not every state-specific customer/role email has a distinct system-channel log (email `communication_logs` now cover all four refund status events with status-specific payloads).

## Proposed EMAIL-ROLE-MATRIX-2 Implementation Plan

1. Add explicit recipient bucket policy for `agency_admin`, `agent_admin`, `agent_staff_creator`, `manual_review_queue`, `ticketing_queue`, and `cancellation_queue`.
2. Fix highest-risk overlaps first: payment verified/rejected duplicate delivery (done), cancellation wrong-payload risk (done in EMAIL-CANCEL-CUSTOMER-PAYLOAD-SAFETY-1), and missing `TicketIssued` resolver buckets.
3. Remaining role-specific event mappings for agency/agent bookings outside booking-created (e.g. A4 low-balance wallet warning) after A1/A2/A3 closure.
4. Add queue alerts for manual review, ticketing pending, cancellation requests, and payment proof using existing `OtaNotificationService` (refund action required implemented in EMAIL-S7-REFUND-ACTION-1).
5. Add status-specific universal payload factory methods for cancellation processed/rejected (refund approved/paid/rejected implemented in EMAIL-REFUND-C8-1).
6. Add targeted tests for resolver buckets and `communication_logs` rows before enabling any new send paths.

Recommended implementation order:

1. Resolver policy safety: add missing buckets and tests without adding new sends.
2. De-duplicate payment verified/rejected operational routing.
3. Fix cancellation customer payload and logging (done in EMAIL-CANCEL-CUSTOMER-PAYLOAD-SAFETY-1).
4. Add `TicketIssued` resolver buckets for agent/agency/staff use cases.
5. Implement agency-admin and agent-staff booking lifecycle notifications.
6. Implement queue alerts and digests after per-event routing is stable.

## Proposed AUTH-SECURITY-EMAIL-1 Implementation Plan

1. Decide policy: privileged-only security emails or all-role security emails.
2. Add explicit events for customer login success, staff/agent failed login, new device, suspicious login, and password reset requested logging.
3. Add noise controls: rate limits, thresholded failed-login notifications, and no account-enumerating emails for unknown addresses.
4. Route privileged and platform-risk alerts to platform admin; route personal account alerts to the affected user when safe.
5. Add `communication_logs` coverage for password reset, email verification, welcome, and admin signup sends or document them as intentional framework-managed exceptions.
6. Add tests for no raw password/token logging, correct recipient bucket, and config gates.

## Banned Brand Runtime String Check

No runtime brand strings for `Skyway Travels`, `Skyway`, `Asif Travels`, `Asif Travel`, `asif travels`, or `skyway` were found in the inspected runtime paths `app`, `resources`, `config`, `database`, `routes`, or `public`.

Project documentation may still mention the project name in non-runtime files; that is outside the runtime brand check.

## EMAIL-ROLE-MATRIX-2A Recipient Resolver Foundation

Date: 2026-06-13

Scope: communication routing only. No payment execution, supplier, PNR, ticketing, cancellation execution, auth, migrations, routes, UI, new mailables, new events/listeners, or legacy template deletion changes were made.

### Existing Recipient Buckets Found

- `admin`
- `staff_assigned`
- `finance`
- `customer_party`
- `agent_booking`
- `agent`
- `applicant`
- `ticket_creator`
- `ticket_assigned_staff`
- `ticket_forwarded_agent`
- `logged_in_user`

### Buckets Added / Normalized

- `booking_customer`: explicit customer booking email bucket; resolves booking contact/customer only.
- `booking_agent`: explicit booking agent email bucket; alias for the existing agent-booking resolver.
- `assigned_staff`: explicit assigned staff bucket; alias for existing `staff_assigned`.
- `platform_admin`: explicit platform admin bucket; alias for the existing platform-admin-only `admin` resolver.
- `platform_staff`: active staff users on the agency pivot.
- `agency_admin`: active agency admin users on the agency pivot.
- `agent_staff_creator`: resolves only when an active agent-staff actor/email/user id is already present in context.
- `operations_queue`: existing support-email fallback only; no invented recipients.

If any explicit bucket resolves no safe email, the resolver returns an empty bucket result, `OtaNotificationService` logs a warning, and the send continues with the remaining recipients or existing fallback behavior. Missing bucket emails do not crash booking/payment transactions.

### Payment Duplicate Risk Status

Risk fixed for C5/C6 payment verified/rejected duplicate customer delivery.

- The direct `BookingCommunicationService::sendPaymentVerified` and `sendPaymentRejected` customer emails remain the only customer-facing path.
- Operational `OtaNotificationService` routing for `payment_verified` and `payment_rejected` now uses internal buckets only: `platform_admin` and `assigned_staff`.
- Operational payment logs include `recipient_buckets`, `skipped_recipient_buckets`, and `deduplicated_recipient_buckets`.
- `deduplicated_recipient_buckets` records that `customer_party` and `agent_booking` were skipped because the direct booking communication email is the single customer-facing payment path.

### Unresolved Recipient Data Gaps

- `agent_staff_creator` resolves from a direct active agent-staff actor first, then from `booking.meta.creator_context.agent_staff_creator_user_id` for new bookings submitted by agent staff. Historical bookings without this context are skipped safely.
- `agency_admin` and `platform_staff` rely on active users attached to the agency pivot; if no users exist, the bucket is skipped/fallback behavior applies.
- `operations_queue` currently uses the existing agency/platform support email fallback. There is no separate configured queue inbox in this phase.
- Payment verified/rejected operational routing intentionally no longer emails `customer_party` or `agent_booking`; later agency/agent notifications should use dedicated non-duplicating events or explicitly separated buckets.

## EMAIL-COMMUNICATION-CLOSURE-1 Routing Closure

Date: 2026-06-13

Scope: email communication routing, payload safety, communication log metadata, and documentation only. No payment execution, Sabre connector, PNR creation, ticketing execution, cancellation execution, checkout business rule, auth/login email, database migration, or new booking status changes were made.

### Final Communication Routing Table

| Scenario | Customer / guest | Agency / agent | Agent staff | Platform staff / ops | Platform admin |
|---|---|---|---|---|---|
| Booking created (`booking_request_received`) | Direct `BookingCommunicationService` customer-safe booking request received email only | `booking_agent`, `agency_admin` B2B-safe booking-created universal email when an agency-side creator resolves (A1); plus `agent_staff_creator` when creator is agent staff (A2) | `agent_staff_creator` from active direct actor, then stored active booking creator context; missing creator bucket skipped/logged without blocking owner/admin delivery | Existing operational booking-created alert unchanged | Existing operational booking-created alert unchanged |
| Manual review (`customer_manual_review_required`, `booking_manual_review_required`, `staff_review_required`, `booking_manual_review_b2b`) | Direct `BookingCommunicationService` customer-safe universal email (`customerManualReviewRequired`) | `booking_agent`, `agency_admin` B2B-safe universal email (`agencyManualReviewRequired`, `notification_type=booking_manual_review_b2b`) | `agent_staff_creator` from direct actor, then stored booking creator context | `assigned_staff`, `operations_queue` internal staff-review alert (`staffReviewRequired`, `notification_type=staff_review_required`) | `platform_admin` |
| Cancellation request/status | Dedicated customer-safe cancellation update | `booking_agent`, `agency_admin` B2B-safe cancellation update | `agent_staff_creator` from direct actor, then stored booking creator context | `assigned_staff`, `operations_queue` for requests; `assigned_staff` for status updates | `platform_admin` |
| Refund updates | Status-specific customer refund update (`customerRefundUpdate`) | Status-specific B2B refund update (`agencyRefundUpdate`); customer buckets deduplicated | `agent_staff_creator` from direct actor, then stored booking creator context | Existing internal policy by refund event | `platform_admin`/admin policy by event |
| Ticket issued | Existing direct customer ticket-issued email | `booking_agent`, `agency_admin` B2B-safe ticket notice | `agent_staff_creator` from direct actor, then stored booking creator context | `assigned_staff` internal alert when expected | `platform_admin` internal alert |
| Supplier booking created / pending ticketing | No customer operational duplicate | No B2B customer duplicate | No agent-staff duplicate | `assigned_staff`, `operations_queue` ticketing queue/action alert | `platform_admin` |
| Payment proof submitted | No new customer payment path | B2B-safe `payment_proof_submitted_b2b` to `booking_agent`, `agency_admin`, `agent_staff_creator` when agency/agent booking context resolves | `agent_staff_creator` from stored booking creator context; missing creator skipped/logged | `finance`, `assigned_staff`, `operations_queue` | `platform_admin` |
| Payment verified/rejected | Direct `BookingCommunicationService` customer email only | B2B-safe `payment_verified_b2b` / `payment_rejected_b2b` to `booking_agent`, `agency_admin`, `agent_staff_creator` when agency/agent booking context resolves | `agent_staff_creator` from stored booking creator context; missing creator skipped/logged | `assigned_staff` | `platform_admin` |
| Supplier/ticketing failure | No raw supplier error customer email | No raw supplier/ticketing failure B2B email | No raw supplier/ticketing failure agent-staff email | `assigned_staff`, `operations_queue` internal failure alerts (`supplierFailureAlert`, `ticketingFailureAlert`, `ticketingNotSupportedAlert`) | `platform_admin` |
| Critical Sabre/GDS credential/auth/link failure (P1) | N/A | N/A | N/A | S3 generic supplier/ticketing failure alerts remain separate | Dedicated `supplier_connection_auth_failed` platform-admin-only alert when safe auth/credential classification exists on supplier/ticketing failure path |

### Duplicate Prevention Notes

- Customer booking-created remains single-path through `BookingCommunicationService`; B2B booking-created uses separate `booking_created_b2b` notification metadata and logs `customer_party` / `booking_customer` as de-duplicated buckets.
- Customer payment verified/rejected remains single-path through `BookingCommunicationService`; operational payment notifications continue to log `customer_party` and `agent_booking` as deduplicated/skipped buckets. B2B agency payment notifications use separate `payment_verified_b2b` / `payment_rejected_b2b` / `payment_proof_submitted_b2b` metadata with deduplicated `customer_party` / `booking_customer` buckets.
- Customer ticket-issued remains direct through `BookingCommunicationService`; B2B and internal ticket-issued sends use separate recipient buckets and payload copy.
- Customer cancellation uses `cancellation_requested_customer` / `cancellation_status_customer` on `booking_customer` only; internal cancellation uses `cancellation_requested_internal` / `cancellation_status_internal`; B2B cancellation uses `cancellation_update_b2b` with deduplicated `customer_party` / `booking_customer` buckets.
- Customer refund emails use status-specific `customerRefundUpdate` on `booking_customer` only; B2B refund emails use status-specific `agencyRefundUpdate` with `customer_party` / `booking_customer` logged as deduplicated buckets.
- Explicit bucket mode no longer falls back to support email when role buckets resolve empty, except for buckets that intentionally represent a safe fallback such as `operations_queue`, `platform_admin`/admin, or finance support fallback.
- `communication_logs.meta` records recipient buckets, skipped bucket reasons, deduplicated buckets, recipient type/scope, booking reference, payload, and skipped reason for skipped sends without requiring a migration.

## EMAIL-SOURCE-CONTEXT-1 Creator Context Persistence

Date: 2026-06-13

Scope: booking creator context persistence, communication recipient fallback, communication log metadata, and documentation only. No Sabre API, PNR creation, ticketing execution, cancellation execution, payment execution, checkout business rule, auth/login email, database migration, or new booking status changes were made.

### Creator Context Storage

New authenticated non-customer booking submissions persist minimal context in existing `bookings.meta.creator_context`:

- `creator_user_id`
- `creator_role`
- `creator_source`
- `agent_staff_creator_user_id` only when the actor is agent staff

No email address or additional personal data is stored in booking meta. Guest/customer bookings do not create agent-staff creator context. Existing `bookings.meta` safely supports this without a migration.

### Agent Staff Creator Resolution

`agent_staff_creator` now resolves in this order:

1. Direct active agent-staff actor/context.
2. Stored `booking.meta.creator_context.agent_staff_creator_user_id`.
3. Safe skip with `agent_staff_creator_source=missing`.

`communication_logs.meta` records `agent_staff_creator_source`, `agent_staff_creator_user_id`, `booking_creator_user_id`, and `booking_creator_role` for sent and skipped operational notifications where relevant.

### Still Partial / Impossible Without More Source Data

- AS1 remains implemented for agent-staff-created bookings: booking-created B2B notification routes to `booking_agent`, `agency_admin`, and `agent_staff_creator`; missing/unverifiable agent-staff creator bucket is skipped safely without blocking owner/admin delivery.
- AS2/AS3/AS4 are implemented for new bookings with persisted agent-staff creator context or direct agent-staff actor context; historical bookings without reliable context are skipped safely.
- S2 is implemented: `notifyManualReviewRequired()` sends a distinct internal staff-review alert with `notification_type=staff_review_required` to `assigned_staff`, `operations_queue`, and `platform_admin`; customer and B2B manual-review paths remain separate with deduplicated customer buckets.
- P1 is implemented on supplier/ticketing failure paths when safe auth/credential error codes or `entitlement_or_security_error` host classification are present (EMAIL-P1-P4-PLATFORM-OPS-ALERTS-1). Standalone search-only auth failures without a booking failure hook remain a monitoring gap.

## EMAIL-AS1-CLOSURE-1 Booking-Created Agent Staff Routing

Date: 2026-06-13

Scope: email communication routing, B2B-safe payload copy, communication log metadata, and documentation only. No Sabre API, PNR creation, ticketing execution, cancellation execution, payment execution, checkout business rule, auth/login email, database migration, or new booking status changes were made.

### AS1 Routing

`BookingService::submitBookingRequest()` now passes the submit actor into `BookingCommunicationService::sendBookingRequestReceived()`. The existing customer `booking_request_received` email remains unchanged and is still sent only through the direct customer booking email path.

When the booking has an agency-side creator (`agent`, `agency_admin`, or `agent_staff`) from either the direct submit actor or stored `bookings.meta.creator_context`, `sendBookingRequestReceived()` sends a separate B2B booking-created operational notification through `OtaNotificationService`:

- Event key: `booking_request_received`
- Universal payload type: `booking_created_b2b`
- A1 (`routing_policy=A1_agency_or_agent_admin`): recipient buckets `booking_agent`, `agency_admin`
- A2 (`routing_policy=A2_agent_staff`): recipient buckets `booking_agent`, `agency_admin`, `agent_staff_creator`
- De-duplicated/skipped customer buckets: `customer_party`, `booking_customer`

Customer/guest-created bookings do not enter the B2B route. Missing/unresolvable `agent_staff_creator` on A2 routes is skipped and logged by the resolver without blocking reliable `booking_agent` / `agency_admin` delivery.

## EMAIL-A1-A2-CLOSURE-1 Agency / Agent Staff Booking-Created Policy

Date: 2026-06-13

Scope: email communication routing, B2B-safe payload copy, communication log metadata, and documentation only. No Sabre API, PNR creation, ticketing execution, cancellation execution, payment execution, checkout business rule, auth/login email, database migration, or new booking status changes were made.

### A1 Policy — agency admin / agent admin booking-created

When a booking is created by an agency-side agent admin (`AccountType::Agent`) or agency admin (`AccountType::AgencyAdmin`), detected from the direct submit actor or stored `bookings.meta.creator_context`:

1. Customer path remains unchanged: direct `booking_request_received` to booking customer/contact only.
2. B2B path sends `BookingEmailPayloadFactory::b2bBookingCreated()` through `OtaNotificationService` with `notification_type=booking_created_b2b`.
3. Recipient buckets: `booking_agent`, `agency_admin`.
4. Customer buckets `customer_party` and `booking_customer` are deduplicated/skipped on the B2B send.
5. `agent_staff_creator` is not required for A1.

### A2 Policy — agent staff booking-created

When a booking is created by agent staff (`AccountType::AgentStaff`), detected from the direct submit actor or stored creator context:

1. Customer path remains unchanged and non-duplicated.
2. B2B path uses the same `booking_created_b2b` payload and event key.
3. Recipient buckets: `booking_agent`, `agency_admin`, `agent_staff_creator`.
4. `agent_staff_creator` resolves from direct active actor first, then stored `bookings.meta.creator_context.agent_staff_creator_user_id`.
5. If `agent_staff_creator` cannot resolve, `booking_agent` and/or `agency_admin` still receive the B2B notification when resolvable; the missing creator bucket is skipped and logged with reason instead of failing the route.

### Creator classification helpers

`BookingCommunicationService` now classifies booking-created routing with:

- `bookingCreatedByAgencySideActor()`
- `bookingCreatedByAgentStaff()`
- `bookingCreatedByAgentOrAgencyAdmin()`

Agency-side actors are `AccountType::Agent`, `AccountType::AgentStaff`, and `AccountType::AgencyAdmin`. Platform staff / platform admin created bookings remain out of scope for the B2B booking-created route unless a future product policy adds them.

### Required communication log meta

B2B booking-created sends and resolver skips record:

- `notification_type=booking_created_b2b`
- `booking_id`, `booking_reference`
- `booking_creator_user_id`, `booking_creator_role`, `booking_creator_source`
- `agent_staff_creator_user_id`, `agent_staff_creator_source` when applicable
- `recipient_buckets`, `skipped_recipient_buckets`, `deduplicated_recipient_buckets`
- `routing_policy` (`A1_agency_or_agent_admin` or `A2_agent_staff`)

## EMAIL-REFUND-C8-1 Status-Specific Refund Emails

Date: 2026-06-13

Scope: email communication payload/routing/logging only. No payment execution, refund business rule, ledger, wallet, Sabre API, PNR/ticketing/cancellation execution, checkout, auth/login email, migration, or new refund status changes were made.

### Refund Email Payloads

`BookingEmailPayloadFactory` now provides status-specific universal refund payloads:

- Customer: `customerRefundUpdate()` with types `refund_requested`, `refund_approved`, `refund_paid`, `refund_rejected`
- B2B: `agencyRefundUpdate()` with types `agency_refund_requested`, `agency_refund_approved`, `agency_refund_paid`, `agency_refund_rejected`
- Legacy alias: `refundPending()` delegates to `customerRefundUpdate(..., 'refund_requested')`

Customer copy is reassuring and does not expose internal finance notes, rejection reasons, ledger details, or supplier raw data. B2B copy includes booking reference, refund status, amount summary when available, and route/travel summary without passenger document data.

### Refund Routing

`BookingRefundService::notifyRefund()` sends separate operational notifications per refund state transition:

1. Customer path: `booking_customer` bucket with `customerRefundUpdate()`
2. B2B path: `booking_agent`, `agency_admin`, `agent_staff_creator` with `agencyRefundUpdate()` and deduplicated `customer_party` / `booking_customer` buckets
3. Internal action path (refund requested only): see EMAIL-S7-REFUND-ACTION-1

Operational payload meta includes `booking_refund_id`, `refund_status`, `booking_id`, and `booking_reference`. `communication_logs.meta` records recipient buckets, skipped buckets, deduplicated buckets, and agent-staff creator source metadata through `OtaNotificationService`.

## EMAIL-S7-REFUND-ACTION-1 Dedicated Refund Action Required Alert

Date: 2026-06-13

Scope: email communication payload/routing/logging only. No payment execution, refund business rule, ledger, wallet, Sabre API, PNR/ticketing/cancellation execution, checkout, auth/login email, migration, or new refund status changes were made.

### Internal Refund Action Payload

`BookingEmailPayloadFactory::refundActionRequired()` builds a platform-safe universal payload with:

- `type`: `refund_action_required`
- Title/status: Refund action required / Refund Requested
- Booking reference, route/travel summary, refund amount overlay when available
- Contact summary consistent with existing internal admin alerts
- Action note: review refund request, fare rules, supplier/payment implications, and update refund status
- No supplier credentials, payment gateway secrets, Sabre raw errors, or internal finance notes

### Internal Refund Action Routing

On `refund_requested` only, `BookingRefundService::notifyRefund()` sends a third operational notification (separate from customer and B2B paths):

1. Customer path (unchanged): `booking_customer` with `customerRefundUpdate()`
2. Internal action path (new): `finance`, `assigned_staff`, `operations_queue`, `platform_admin` with `refundActionRequired()`
3. B2B path (unchanged): `booking_agent`, `agency_admin`, `agent_staff_creator` with `agencyRefundUpdate()`

`refund_approved`, `refund_paid`, and `refund_rejected` do not receive the internal action alert.

Duplicate internal action alerts for the same `booking_refund_id` are skipped when a prior `communication_logs` row exists with `event=refund_requested`, `notification_type=refund_action_required`, and matching `meta.payload.booking_refund_id`.

## EMAIL-S2-STAFF-REVIEW-REQUIRED-1 Distinct Staff Review Required Alert

Date: 2026-06-13

Scope: email communication payload/routing/logging only. No booking status semantics, Sabre/GDS, PNR/ticketing/cancellation/refund/payment/checkout/ledger/auth changes, migration, or UI changes were made.

### Customer Manual Review Policy (C4, preserved)

- Event: `customer_manual_review_required`
- Path: direct `BookingCommunicationService::sendEmailForBooking()` only
- Payload: `BookingEmailPayloadFactory::customerManualReviewRequired()`
- Recipients: booking customer resolver only (`booking_customer`)
- Copy: reassuring under-review message; no supplier/Sabre raw errors, internal diagnostics, retry instructions, or staff queue details

### B2B Manual Review Policy (AS2, preserved + clarified)

- Event key: `booking_manual_review_required`
- Notification type: `booking_manual_review_b2b`
- Payload: `BookingEmailPayloadFactory::agencyManualReviewRequired()`
- Recipients: `booking_agent`, `agency_admin`, `agent_staff_creator` (direct actor first, then stored creator context)
- Customer buckets deduplicated: `customer_party`, `booking_customer`
- Duplicate B2B alerts skipped per booking + `notification_type=booking_manual_review_b2b`

### Internal Staff Review Required Policy (S2, new)

- Event key: `booking_manual_review_required`
- Notification type: `staff_review_required`
- Payload: `BookingEmailPayloadFactory::staffReviewRequired()`
- Recipients: `assigned_staff`, `operations_queue`, `platform_admin` only
- Payload includes booking reference, route/travel summary, safe reason when available, supplier/ticketing status labels in meta, and staff action note
- Does not expose secrets, tokens, credentials, full Sabre raw payloads, card/bank data, or customer-private document paths
- Customer/B2B buckets logged as deduplicated on internal send
- Duplicate internal staff-review alerts skipped per booking + `notification_type=staff_review_required`

### Trigger Sources

- `BookingCommunicationService::notifyManualReviewRequired()` (e.g. `SupplierBookingService` manual review branch)
- `BookingCommunicationService::sendBookingStatusChanged()` review-label guard calls `notifyManualReviewRequired()` after customer-safe status email when manual-review labels apply

## EMAIL-S3-SUPPLIER-TICKETING-FAILURE-1 Supplier / Ticketing Failure Routing Completion

Date: 2026-06-13

Scope: email communication payload/routing/logging only. No supplier execution, Sabre/GDS API, PNR creation/retry, ticketing execution, booking status semantics, cancellation/refund/payment/checkout/ledger/auth changes, migration, or UI changes were made.

### Internal Supplier Failure Policy (S3)

- Entry points: `BookingCommunicationService::notifySupplierFailure()`
- Wired source: `SupplierBookingService` non-manual-review failure branch
- Events / notification types: `supplier_booking_failed`, `supplier_readiness_failed`, `supplier_search_failed`, `supplier_order_failed`
- Payload: `BookingEmailPayloadFactory::supplierFailureAlert()`
- Recipients: `assigned_staff`, `operations_queue`, `platform_admin` only
- Customer/B2B buckets deduplicated: `customer_party`, `booking_customer`, `booking_agent`, `agency_admin`, `agent_staff_creator`
- Safe payload includes booking reference, route/travel summary, sanitized failure reason/classification when available, supplier/ticketing status labels, and staff action note
- Does not expose raw Sabre/GDS request/response payloads, supplier raw XML/JSON, access tokens, credentials, passenger document paths, card/bank data, full stack traces, or secrets
- Duplicate internal alerts skipped per booking + `notification_type` (and supplier attempt id when present in payload meta)

Readiness/search/order notification types classify from safe supplier `error_code` values on the existing supplier booking failure path (for example revalidation/payload validation → readiness, search → search, trip_orders/order → order). No separate readiness/search/order execution hooks were added in this phase.

### Internal Ticketing Failure Policy (S3)

- Entry point: `BookingCommunicationService::notifyTicketingFailure()`
- Wired source: `TicketingService` failure branch
- Events / notification types: `ticketing_failed`, `ticketing_not_supported`
- Payloads: `BookingEmailPayloadFactory::ticketingFailureAlert()` or `ticketingNotSupportedAlert()`
- Recipients: `assigned_staff`, `operations_queue`, `platform_admin` only
- Customer/B2B buckets deduplicated as above
- Duplicate internal alerts skipped per booking + `notification_type` (and ticketing attempt id when present in payload meta)

### Preserved Separate Paths

- C4 customer manual review remains customer-safe via `customerManualReviewRequired()`
- S2 staff review required remains separate via `staffReviewRequired()` / `staff_review_required`
- S4 pending ticketing queue alert remains separate on supplier booking created (`pnrCreated` / `SupplierBookingCreated`)
- P1 critical Sabre/GDS credential/auth/link failure implemented separately in EMAIL-P1-P4-PLATFORM-OPS-ALERTS-1 (see closure section below)

### Remaining Gaps After S3

- Standalone supplier readiness/search/order failure sources outside supplier booking create (no separate service call sites found; classification happens on supplier booking failure when safe `error_code` indicates readiness/search/order)
- P3 automated scheduler/cron for PNR/manual-review digest (manual `ota:send-pnr-manual-review-digest` implemented in EMAIL-P3-PNR-MANUAL-REVIEW-DIGEST-1)

## EMAIL-A5-AGENCY-PAYMENT-PROOF-1 Agency Payment / Payment Proof Update Policy

Date: 2026-06-13

Scope: email communication payload/routing/logging only. No payment execution, payment verification/rejection business logic, ledger, wallet, checkout, Sabre/GDS, PNR/ticketing/cancellation/refund, auth, migration, or UI changes were made.

### A5 Policy

**Customer payment notifications (C5/C6):**

- `BookingCommunicationService::sendPaymentVerified()` and `sendPaymentRejected()` remain the only customer-facing payment verified/rejected email path through direct `BookingUniversalNotification` payloads.
- No operational duplicate to `customer_party` or `booking_customer`.

**Internal platform payment alerts (S6 + operational C5/C6):**

- Payment proof submitted: `finance`, `assigned_staff`, `operations_queue`, `platform_admin` (unchanged).
- Payment verified/rejected operational alerts: `platform_admin`, `assigned_staff` with deduplicated `customer_party` / `agent_booking` buckets (unchanged).

**Agency/B2B payment notifications:**

Only when `bookingHasAgencyPaymentB2bContext()` resolves (agency-side creator from direct actor or stored `bookings.meta.creator_context`, or booking has `agent_id`):

1. **Payment proof submitted** — separate B2B send with `agencyPaymentProofSubmitted()` to `booking_agent`, `agency_admin`, and `agent_staff_creator` when agent-staff creator context exists.
2. **Payment verified** — separate B2B send with `agencyPaymentVerified()`; customer direct email unchanged.
3. **Payment rejected** — separate B2B send with `agencyPaymentRejected()`; customer direct email unchanged; internal rejection/finance notes are not exposed.
4. **Payment recorded** — remains internal-only (`finance`, `admin`, `staff_assigned` policy buckets). Documented as out of scope for B2B in this phase.

**B2B payload safety:**

- `payment_proof_submitted_b2b`, `payment_verified_b2b`, `payment_rejected_b2b` types in `BookingEmailPayloadFactory`.
- Agency-safe title/status, booking reference, route/travel summary, amount/currency overlay when available.
- No raw gateway errors, internal notes, finance-only notes, secrets, card/bank details, or uploaded document paths.

**B2B routing and logging:**

- Recipient buckets: `booking_agent`, `agency_admin`, `agent_staff_creator` (when agent-staff context exists).
- `agent_staff_creator` resolves from direct actor first, then stored `booking.meta.creator_context.agent_staff_creator_user_id`; missing creator is skipped/logged without blocking reliable owner/admin delivery.
- `communication_logs.meta` records `notification_type`, `booking_id`, `booking_reference`, `booking_payment_id`, `payment_event`, `payment_status`, recipient/skipped/deduplicated buckets, and creator context fields.
- Duplicate B2B sends for the same `booking_payment_id` + `notification_type` are skipped via existing `communication_logs` meta checks.

**Status:** A5 is **Implemented** for `payment_proof_submitted`, `payment_verified`, and `payment_rejected` B2B routes. `payment_recorded` remains **Partial** (internal-only; no B2B route).

## EMAIL-CANCEL-CUSTOMER-PAYLOAD-SAFETY-1 — Cancellation Customer Payload Safety

Date: 2026-06-13

Scope: cancellation email/notification routing, payload selection, deduplication, and logging only. No cancellation execution, Sabre/GDS, PNR, ticketing, payment, refund, checkout, ledger, auth, or UI changes. No migration.

### Customer cancellation policy (C7)

- Recipients: `booking_customer` bucket only (direct booking customer resolver).
- Payloads: `BookingEmailPayloadFactory::customerCancellationRequested()` (`notification_type=cancellation_requested_customer`) on `cancellation_requested`; `customerCancellationUpdate()` (`notification_type=cancellation_status_customer`) on status changes / processed outcomes.
- Customer copy excludes internal/admin notes, supplier raw errors, Sabre/GDS responses, finance-only notes, and staff queue wording.
- Duplicate customer sends for the same `cancellation_request_id` + `cancellation_status` + `notification_type` are skipped when already logged.

### Internal cancellation policy (S5)

- Recipients: `assigned_staff`, `operations_queue`, `platform_admin` on request; `platform_admin`, `assigned_staff` on status changes.
- Payloads: `cancellationRequested()` (`notification_type=cancellation_requested_internal`) and `adminCancellationAlert()` (`notification_type=cancellation_status_internal`).
- `customer_party` and `booking_customer` are logged as deduplicated buckets; internal payload never routes to customer buckets.

### B2B cancellation policy (A7 / AS4)

- Recipients: `booking_agent`, `agency_admin`, `agent_staff_creator` (direct actor first, then stored creator context; missing creator skipped/logged).
- Payload: `agencyCancellationUpdate()` (`notification_type=cancellation_update_b2b`).
- `customer_party` and `booking_customer` are deduplicated on B2B sends.
- Duplicate B2B sends for the same `cancellation_request_id` + `cancellation_status` are skipped when already logged.

### Logging metadata

Operational `communication_logs.meta` records `notification_type`, `booking_id`, `booking_reference`, `cancellation_request_id`, `cancellation_status`, `recipient_buckets`, `skipped_recipient_buckets`, `deduplicated_recipient_buckets`, and `agent_staff_creator_source` when applicable.

**Wrong-payload risk:** Resolved — `adminCancellationAlert` / internal cancellation payloads no longer route to `customer_party` / `booking_customer`.

**Status:** C7, A7, AS4, and S5 remain **Implemented** with explicit customer/internal/B2B payload split.

## AGENCY-NOTIFICATION-TEMPLATE-SEED-1 — Auto-seed on new agency

Date: 2026-06-13
Scope: agency message template seeding only. No booking lifecycle, auth behavior, Sabre/GDS, payment, ticketing, cancellation, refund, or UI changes.

### Behavior

When a new agency is created through **`AgentApplicationOnboardingService::resolveOrCreateAgency`** (approved agent application) or **`AgencyReconciliationService::createAgency`** (reconciliation repair), **`AgencyMessageTemplateSeeder::seedAllDefaultsForAgency`** runs immediately after the agency row is persisted and branding settings are initialized.

- Creates missing **`agency_message_templates`** email rows for all **`OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS`** and **`AUTH_SECURITY_EVENT_KEYS`**.
- Does not overwrite existing rows (customized subject/body/variables/enabled preserved).
- Seeding failures are logged and do not block agency creation.

### Backfill commands (historical repair)

**`php artisan ota:backfill-business-email-templates`** and **`php artisan ota:backfill-auth-email-templates`** remain idempotent repair tools for existing agencies. They delegate to **`AgencyMessageTemplateSeeder`** and retain **`--dry-run`**, **`--force`**, and **`--agency=`** options. Default mode creates missing rows only (existing rows are preserved).

## EMAIL-P1-P4-PLATFORM-OPS-ALERTS-1 — Platform Ops Alerts (P1 + P4)

Date: 2026-06-13

Scope: email/routing/logging/reporting notification policy only. No Sabre/GDS API, supplier execution, PNR/ticketing/payment/wallet/ledger/booking/cancellation/refund/auth/checkout behavior changes, migration, scheduler/cron, or UI changes.

### P1 — Critical Sabre/GDS credential/auth/link failure (Implemented)

- **Source event:** Existing supplier/ticketing failure hooks (`BookingCommunicationService::notifySupplierFailure()` / `notifyTicketingFailure()`) when safe classification indicates credential/auth/link failure:
  - `error_code` in `sabre_token_failed`, `sabre_auth_failed`, `sabre_booking_forbidden`, `invalid_client`, `invalid_grant`, `missing_credentials`
  - or persisted/host `failure_classification` / `host_classification_reason` = `entitlement_or_security_error` (`SabreHostErrorClassifier`)
- **Notification type:** `supplier_connection_auth_failed`
- **Recipients:** `platform_admin` only; `customer_party`, `booking_customer`, `booking_agent`, `agency_admin`, `agent_staff_creator`, `assigned_staff`, `operations_queue` deduplicated
- **Payload:** `BookingEmailPayloadFactory::supplierConnectionAuthFailureAlert()` — safe provider/connection label, sanitized classification/reason, staff action note; no tokens, secrets, raw payloads, or stack traces
- **Dedupe:** Same agency + `supplier_connection_id` (or booking when connection id missing) + `notification_type` within 60 minutes via existing `communication_logs` rows
- **Limitation:** No standalone connection-health counter or search-only auth failure hook; token probe / connection test diagnostics are not wired to email (by design — no risky new infrastructure)

### P4 — Agency wallet/deposit summary (Partial)

- **Source data:** Read-only `AgentWalletService::agencyWalletSummary()` plus pending deposit count and 30-day transaction count
- **Send method:** `AgentWalletService::sendAgencyWalletDepositSummary()` (manual/on-demand only)
- **Notification type:** `agency_wallet_deposit_summary` (`OtaNotificationEvent::AgencyWalletDepositSummary`)
- **Recipients:** `agency_admin` only — no customer, platform-wide ledger, or cross-agency data
- **Payload:** `BookingEmailPayloadFactory::agencyWalletDepositSummary()` — agency name, balance, pending deposits, pending request count, recent transaction count, period label
- **Dedupe:** Skips when same agency + `period_label` already logged for `agency_wallet_deposit_summary`
- **Blocker for full Implemented:** No existing scheduler/cron hook for automated wallet summaries (new scheduled command not added per sprint scope)

### P3 — Failed PNR / manual review digest (Partial)

Date: 2026-06-13

Scope: read-only reporting/email/digest/logging only. No Sabre/GDS API, supplier execution, PNR/ticketing/payment/wallet/ledger/booking/cancellation/refund/auth/checkout behavior changes, migration, or UI changes.

- **Source data:** Read-only aggregates from `bookings` (`supplier_booking_status`, `ticketing_status`, `pnr`, `supplier_reference`, `status`) and sanitized `supplier_booking_attempts.error_code` counts via `BookingReportService::buildPnrManualReviewDigestSummary()`
- **Send method:** `AdminReportMailerService::sendPnrManualReviewDigest()`; manual trigger `php artisan ota:send-pnr-manual-review-digest` (`--agency=`, `--from=`, `--to=`, `--force`)
- **Default period:** Last 24 hours in agency timezone (overridable via command options)
- **Notification type:** `pnr_manual_review_digest` (`OtaNotificationEvent::PnrManualReviewDigest`)
- **Recipients:** `platform_admin` only — no customer, B2B, staff, or operations_queue routing
- **Payload:** `BookingEmailPayloadFactory::pnrManualReviewDigest()` — period label, booking/failure counts, failed/manual-review ratio, top safe error codes, capped booking reference sample list; no raw Sabre/GDS payloads, credentials, tokens, or PII
- **Dedupe:** Skips when same agency + `period_start` + `period_end` already logged for `pnr_manual_review_digest` unless `--force`
- **Blocker for full Implemented:** No scheduler/cron hook wired to automated digest delivery (manual/on-demand only by design for this sprint)
- **Separation:** S2 staff-review and S3 supplier/ticketing failure alerts remain per-event; P3 is digest-only and does not replace them

## EMAIL-A3-AGENCY-BOOKING-ACTIVITY-SUMMARY-1 — Agency Booking Activity Summary (A3)

Date: 2026-06-13

Scope: read-only reporting/email/digest/logging only. No Sabre/GDS API, supplier execution, PNR/ticketing/payment/wallet/ledger/booking/cancellation/refund/auth/checkout behavior changes, migration, or UI changes.

### A3 — Agency booking activity summary (Implemented)

- **Source data:** Read-only aggregates from `bookings` scoped by `bookings.agency_id` via `BookingReportService::buildAgencyBookingActivitySummary()`
- **Send method:** `AdminReportMailerService::sendAgencyBookingActivitySummary()`; manual trigger `php artisan ota:send-agency-booking-activity-summary --agency=AGENCY_SLUG` (`--from=`, `--to=`, `--force`); scheduled trigger `php artisan ota:send-agency-booking-activity-summary --all-active-agencies` daily at 07:10 via `routes/console.php` when `AGENCY_BOOKING_ACTIVITY_SUMMARY_DAILY_ENABLED=true` (scheduler never passes `--force`)
- **Default period:** Last 24 hours in agency timezone (overridable via command options)
- **Notification type:** `agency_booking_activity_summary` (`OtaNotificationEvent::AgencyBookingActivitySummary`)
- **Recipients:** `agency_admin` only — no customer, platform_admin, staff, operations_queue, booking_agent, or agent_staff_creator routing
- **Metrics:** total bookings; pending/confirmed/ticketed/cancelled; manual review; pending payment; pending ticketing; agent-channel vs direct customer counts; agent-staff created count (when `meta.creator_context` stored); total booking value from `booking_fare_breakdowns`; capped booking reference sample list (10)
- **Payload:** `BookingEmailPayloadFactory::agencyBookingActivitySummary()` — period label, agency-scoped metrics, safe action note; no raw Sabre/GDS payloads, credentials, tokens, passenger details, or PII
- **Dedupe:** Skips when same agency + `period_start` + `period_end` already logged for `agency_booking_activity_summary` unless `--force` (manual only)
- **Scheduler:** Active agencies only (`is_active` or `status=active` when columns exist; otherwise all agencies); chunked iteration; per-agency scoped send; config gate `config('ota.agency_booking_activity_summary_daily_enabled')`
- **Separation:** A1/A2 booking-created, A5 payment proof, and A7/AS4 cancellation/refund event emails remain separate; A3 is digest-only and does not replace them

## EMAIL-A3-SCHEDULER-CLOSURE-1 — Agency Booking Activity Summary Scheduler (A3)

Date: 2026-06-13

Scope: scheduler/automation only for existing A3 digest. No digest logic, payload, recipient policy, booking/Sabre/payment, migration, or UI changes.

### A3 scheduler closure

- **Scheduler location:** `routes/console.php`
- **Schedule:** Daily at 07:10 app timezone (`Schedule::command(...)->dailyAt('07:10')`)
- **Command:** `ota:send-agency-booking-activity-summary --all-active-agencies` (no `--force`)
- **Config gate:** `AGENCY_BOOKING_ACTIVITY_SUMMARY_DAILY_ENABLED` → `config('ota.agency_booking_activity_summary_daily_enabled')` (default `true`)
- **Manual command preserved:** `ota:send-agency-booking-activity-summary --agency=AGENCY_SLUG` with optional `--from=`, `--to=`, `--force`
- **Recipient policy unchanged:** `agency_admin` only via existing resolver
- **Dedupe unchanged:** same agency + `period_start` + `period_end` + `notification_type=agency_booking_activity_summary`; scheduler relies on dedupe (no duplicate daily sends unless manual `--force`)
