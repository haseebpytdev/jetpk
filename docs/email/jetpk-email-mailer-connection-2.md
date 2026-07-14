# JetPK Email Mailer Connection — Phase 2

**Phase:** `JETPK-EMAIL-TEMPLATE-MAILER-CONNECTION-2`  
**Prerequisite:** `JETPK-EMAIL-TEMPLATE-PACKAGE-INSTALL-1` (templates, resolvers, preview/audit commands installed; no live mail wired).

---

## Goal

Connect existing OTA mail send paths to JetPK Blade views **only when** `client_slug === 'jetpk'`. Master / Parwaaz behaviour must remain unchanged for all other clients.

---

## Guard pattern (apply in each connection)

```php
use App\Support\Emails\JetpkEmailBrandingResolver;
use App\Support\Emails\JetpkEmailViewResolver;

$slug = $this->resolveClientSlug($booking ?? $user ?? null); // booking meta, session, or ClientLoginOtpGate

if ($slug === JetpkEmailViewResolver::CLIENT_SLUG) {
    $view = JetpkEmailViewResolver::resolve('booking_created', $slug);
    if ($view !== null) {
        return view($view, [
            'emailBrand' => JetpkEmailBrandingResolver::resolve($slug),
            // …mapped payload from booking/payment/user
        ]);
    }
}

// existing Master/modern renderer path — untouched
```

**Never** change `MAIL_FROM_ADDRESS`. Client display name / reply-to may use `ClientMailBrandingResolver` (already JetPK-aware for OTP).

---

## Connection map

| JetPK type key | JetPK view | Existing OTA send path | Class / service to modify |
|---|---|---|---|
| `otp` | `emails.themes.jetpakistan.auth.otp` | Login OTP delivery | `App\Mail\LoginOtpMail` + `App\Services\Auth\LoginOtpService` (build via `AuthEmailRenderer::loginOtp` today) |
| `sign_in_success` | `…auth.sign-in-success` | Post-login security notice | `App\Services\Communication\AuthSecurityEmailNotificationService::notifyLoginSuccess` → `OtaNotificationService` |
| `password_reset` | `…auth.password-reset` | Laravel reset link | `App\Http\Controllers\Auth\PasswordResetLinkController` + framework `ResetPassword` notification (inspect `App\Models\User::sendPasswordResetNotification`) |
| `account_created` | `…auth.account-created` | Customer registration welcome | `App\Mail\CustomerWelcomeMail` + `AuthEmailRenderer::customerWelcome` |
| `booking_created` | `…booking.booking-created` | New booking request received | `App\Mail\BookingRequestReceivedMail` + `CustomerFacingEmailRenderer::bookingRequestReceived` |
| `booking_pending_manual_payment` | `…booking.booking-pending-manual-payment` | Awaiting manual/bank payment | Inspect booking checkout + `BookingPaymentService` / status notifications for pending-manual state |
| `booking_confirmed` | `…booking.booking-confirmed` | Ticket issued / confirmed | `App\Mail\TicketIssuedMail` + `CustomerFacingEmailRenderer::ticketIssued`; also `BookingItineraryReadyMail` for itinerary-ready variant |
| `booking_failed` | `…booking.booking-failed` | Booking failure | `BookingStatusChangedMail` / `OtaNotificationService` booking failure events |
| `booking_cancelled` | `…booking.booking-cancelled` | Cancellation confirmed | Booking cancellation services + `BookingStatusChangedMail` / `OtaNotificationService` |
| `booking_updated` | `…booking.booking-updated` | Generic status change | `App\Mail\BookingStatusChangedMail` + `CustomerFacingEmailRenderer::bookingStatusChanged` |
| `booking_expiring` | `…booking.booking-expiring` | Hold / payment deadline reminder | Scheduled job / notification event (search `booking_expir` / hold reminder) |
| `manual_payment_received` | `…payment.manual-payment-received` | Guest/admin payment proof received | `GuestBookingLookupController` payment-proof path + admin payment review |
| `payment_success` | `…payment.payment-success` | Payment verified | `App\Mail\PaymentVerifiedMail` + `CustomerFacingEmailRenderer::paymentVerified` |
| `payment_failed` | `…payment.payment-failed` | Payment rejected | `App\Mail\PaymentRejectedMail` + `CustomerFacingEmailRenderer::paymentRejected` |
| `invoice` | `…payment.invoice` | Invoice / receipt email | `ManualBookingCommunicationMail` (admin console) + any invoice event in `OtaNotificationService` / `EmailTemplateRegistry` |
| `refund_requested` | `…payment.refund-requested` | Refund request opened | Refund workflow services + `OtaNotificationService` refund events |
| `refund_updated` | `…payment.refund-updated` | Refund status change | Refund approval/payment services + `OtaNotificationService` |
| `support_ticket_created` | `…support.support-ticket-created` | New support ticket | `App\Services\Support\SupportTicketService::createTicket` / `createPublicTicket` → `OtaNotificationService` |
| `support_reply` | `…support.support-reply` | Staff reply to ticket | `SupportTicketService` reply/notify paths |
| `notification` | `…generic.notification` | Catch-all operational | `App\Mail\BookingUniversalNotification`, `OtaOperationalNotificationMail` via `OtaNotificationService` |

---

## Client slug resolution (phase 2 task)

Implement one shared helper (suggested: `App\Support\Emails\JetpkEmailClientSlugResolver`) that resolves slug from, in order:

1. Explicit `$clientSlug` on mailable / notification context  
2. `ClientLoginOtpGate::resolvedClientSlug()` for auth flows  
3. Booking `meta.client_slug` or linked client profile  
4. `is_client_preview()` + `current_client_slug()`  
5. **Never** default Master slug to JetPK

---

## Payload mapping notes

- Map `CustomerFacingEmailRenderer` / `AuthEmailRenderer` output fields to the contract in `docs/email/jetpk-email-template-contract.md`.
- Passengers: **names only** — strip passport/document numbers.
- Payment: transaction reference only — never card/CVV/PAN.
- PNR: show when confirmed; otherwise omit or use template fallback text.
- Attachments (itinerary PDF): keep on mailable; JetPK body should note attachment when present.

---

## Verification (phase 2)

```bash
php artisan ota:jetpk-email-template-audit
php artisan ota:jetpk-email-preview --all
# After wiring one path at a time:
php artisan ota:jetpk-otp-mail-probe --client=jetpk --dry-run  # if available
```

Manual: send test to internal address for JetPK preview context only; confirm Master still uses modern/Master layout.

---

## Out of scope for phase 2

- Changing SMTP / `MAIL_FROM_ADDRESS`
- Removing `RendersModernCustomerEmail` for non-JetPK clients
- Supplier, PNR, ticketing, or payment gateway logic changes
