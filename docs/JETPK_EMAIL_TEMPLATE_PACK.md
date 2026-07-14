# JetPK Email Template Pack

Config: `config/jetpk_email.php`  
Resolver: `App\Support\Emails\JetpkEmailViewResolver`  
Previews: `php artisan ota:jetpk-email-preview {type}`

## Coverage matrix (39 types)

| Category | Type | Blade path | Audit |
|----------|------|------------|-------|
| Auth | otp | `auth/otp` | pass |
| Auth | sign_in_success | `auth/sign-in-success` | pass |
| Auth | password_reset | `auth/password-reset` | pass |
| Auth | account_created | `auth/account-created` | pass |
| Auth | email_verification | `auth/email-verification` | pass (9G) |
| Auth | password_changed | `auth/password-changed` | pass (9G) |
| Auth | security_notice | `auth/security-notice` | pass (9G) |
| Booking | booking_created | `booking/booking-created` | pass |
| Booking | booking_confirmed | `booking/booking-confirmed` | pass |
| Booking | booking_failed | `booking/booking-failed` | pass |
| Booking | booking_cancelled | `booking/booking-cancelled` | pass |
| Booking | booking_updated | `booking/booking-updated` | pass |
| Booking | booking_expiring | `booking/booking-expiring` | pass |
| Booking | booking_pending_manual_payment | `booking/booking-pending-manual-payment` | pass |
| Booking | pnr_created | `booking/pnr-created` | pass (9G) |
| Payment | payment_success / failed / invoice / manual / refund_* | `payment/*` | pass |
| Group | group_reservation_created | `group-ticketing/reservation-created` | pass (9G) |
| Group | group_reservation_expiring | `group-ticketing/reservation-expiring` | pass (9G) |
| Support | support_ticket_created / support_reply | `support/*` | pass |
| Agent | agent_registration_received / approved | `agent/*` | pass (9G) |
| Admin | admin_operational_notification | `admin/operational-notification` | pass (9G) |
| Generic | notification | `generic/notification` | pass |

## Sender

- From name: JetPakistan (via `JetpkEmailBrandingResolver` + agency communications)
- From address: `ota@jetpakistan.pk` (env `MAIL_FROM_ADDRESS`)

## Audit

```bash
php artisan ota:jetpk-email-template-audit
```

Expected: `fail_count=0`, no Parwaaz/YD/Master strings in rendered HTML.
