# Payment lifecycle (JP-OPS-CLOSURE-01)

## Authority (after engineering slice 1)

```
BUSINESS_PAYMENT_WINDOW=OTA_PAYMENT_WINDOW_MINUTES (default 120)
SUPPLIER_DEADLINE_SOURCE=payment_required_by | price_guarantee_expires_at | pnr_expires_at | meta supplier expiry keys
SAFETY_BUFFER=OTA_PAYMENT_DEADLINE_SAFETY_BUFFER_MINUTES (default 15)
EFFECTIVE_DEADLINE_RULE=min(submitted_at + business window, supplier − buffer) via PaymentDeadlineService
EXPIRY_JOB=ota:expire-unpaid-bookings (every minute)
REMINDER_JOB=ota:send-payment-reminders (every five minutes)
AUTO_CANCEL_JOB=supplier cancel on expiry DISABLED by default (OTA_UNPAID_EXPIRY_SUPPLIER_CANCEL_ENABLED=false)
```

## Applied on submit

`BookingService::submitBookingRequest` writes `payment_due_at`.

## Countdown

Confirmation UI uses server `payment_due_at` ISO via `fare-session-countdown` (refresh-safe).

## Layer A tests

`tests/Feature/UnpaidBookingExpiryAndReminderTest.php` — 6 passed / 20 assertions

- submit sets deadline
- supplier buffer respected
- unpaid expires once (idempotent)
- paid barrier blocks expiry
- reminder dedupe + paid suppress
- artisan command

## Production config

Production effective values must be verified separately; QA may shorten window via config/Carbon without changing production defaults.
