# JP-FIX-MAIL-SABRE-01 — Implementation Summary

## Phase
- **Name:** JP-FIX-MAIL-SABRE-01
- **Branch:** `phase/jp-master-unfinished-closure-10`
- **Objective:** Fix systemic mail/Sabre URL leaks, booking/payment state presentation, payment eligibility, notification semantics, agent application identity, and subject taxonomy.
- **Status:** Implementation complete — local/safe verification PASS. **Not deployed.**

## Architecture

### Canonical outbound URLs
- Extended `App\Support\Url\PublicActionUrl` with `route()`, `sanitize()`, and `/index.php` stripping.
- Added `App\Support\Url\PublicUrlOriginPolicy` for private/loopback origin detection and fail-safe rewrite to `config('app.url')`.
- Replaced `route(..., absolute: true)` in email/CTA paths across booking communication, email base variables, contextual CTA resolver, auth/customer renderers.

### Payment eligibility
- Added `App\Support\Bookings\BookingPaymentEligibility` as single gate for validation state, payable authority, cancelled/expired/paid denial.
- Wired into `PaymentTransactionService`, `BookingPaymentService`, and payment reminder dispatch.

### Booking email state
- `BookingEmailPayloadFactory::payment()` derives `final_payable_status`, validation labels, and payment state from eligibility — no hardcoded `Pending validation`.

### Notification semantics
- `operationalUniversalPayload()` maps `BookingStatusChanged` to `adminStatusChangedAlert()` instead of new-booking alert.
- Draft→Pending submission suppresses creation-time status-change notifications.
- Status-change emails include previous→new transition when known.

### Agent applications
- Migration adds nullable unique `application_reference` with backfill + `creating` hook generation.
- `AgentApplicationNotificationPayload` centralizes nested `application` / `agent_application` blocks and canonical review URLs.

### Subject taxonomy
- `EmailOperationalSubjectFormatter` centralizes `[ADMIN]` / `[AGENT]` / `[STAFF]` booking and application subjects.

## Tests
```bash
php artisan test tests/Unit/Support/Url/PublicActionUrlTest.php \
  tests/Unit/Support/Bookings/BookingPaymentEligibilityTest.php \
  tests/Feature/Communication/BookingEmailAdminUrlHostTest.php \
  tests/Feature/Communication/BookingStatusChangeEmailSemanticsTest.php \
  tests/Feature/AgentApplicationAdminEmailPayloadTest.php \
  tests/Feature/BookingBrandedFareBookingEmailTest.php
```
**Result:** 18 passed, 0 failed.

## Migration safety
- **Name:** `2026_09_11_180000_add_application_reference_to_agent_applications_table.php`
- **Existing rows:** backfilled with compact references using sanitized company prefix.
- **Rollback:** drops unique index + column.
- **Production:** not run in this loop.

## Excluded / not done
- No production deploy, migration, email send, Sabre mutation, or live payment.
- Browser UI payment flow verification deferred to deployment UAT loop.
- Residual `route(..., absolute: true)` in non-email internal paths intentionally unchanged.
