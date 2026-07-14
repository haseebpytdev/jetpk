# EMAIL-UNIVERSAL-1 Legacy Email Template Audit

## Active Universal Replacement

Booking-related customer and operational emails now route through:

- `app/Mail/BookingUniversalNotification.php`
- `app/Services/Communication/BookingEmailPayloadFactory.php`
- `resources/views/emails/layouts/universal.blade.php`
- `resources/views/emails/booking/universal-notification.blade.php`

The existing `communication_logs` table is reused. `event`, `recipient_email`, `subject`, `status`, `error_message`, and `sent_at` remain first-class columns. `notification_type`, `recipient_type`, `attempts`, and `universal_mailable` are stored in `meta`.

## Legacy Template Candidates

| Old file path | Current references | Replacement notification type | Safe to delete |
|---|---:|---|---|
| `resources/views/emails/bookings/request-received.blade.php` | None in active PHP send code | `booking_received` / `booking_request_received` | No, defer until post-release verification |
| `resources/views/emails/bookings/status-changed.blade.php` | None in active PHP send code | `booking_status_changed`, `booking_confirmed`, `booking_cancelled` | No, defer until post-release verification |
| `resources/views/emails/bookings/payment-verified.blade.php` | None in active PHP send code | `payment_verified` | No, defer until post-release verification |
| `resources/views/emails/bookings/payment-rejected.blade.php` | None in active PHP send code | `payment_rejected` | No, defer until post-release verification |
| `resources/views/emails/bookings/ticket-issued.blade.php` | None in active PHP send code | `ticket_issued` | No, defer until post-release verification |
| `resources/views/emails/bookings/itinerary-ready.blade.php` | None in active PHP send code | `itinerary_ready` | No, defer until post-release verification |
| `resources/views/emails/auth/google-customer-welcome.blade.php` | None in active PHP send code | Out of EMAIL-UNIVERSAL-1 booking scope | No |
| `resources/views/emails/marketing/abandoned-flight-search.blade.php` | None in active PHP send code | Out of EMAIL-UNIVERSAL-1 booking scope | No |

## Intentionally Retained

- `resources/views/emails/layouts/modern.blade.php` remains active for non-booking mail renderers such as auth welcome, marketing, settings test, and generic operational/resend paths without a booking universal payload.
- Old booking Mailable classes remain in `app/Mail` for this phase and are not deleted.
- Generic non-booking `OtaNotificationService` sends continue to use `OtaOperationalNotificationMail`.

## Reference Notes

- Active customer booking send points in `BookingCommunicationService` now instantiate `BookingUniversalNotification`.
- Booking operational notifications that include a `universal_email` payload render through `BookingUniversalNotification` inside `OtaNotificationService`.
- No database string is used as a Blade view name for the universal path.
