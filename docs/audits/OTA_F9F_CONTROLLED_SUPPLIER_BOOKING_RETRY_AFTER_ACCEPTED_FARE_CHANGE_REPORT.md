# OTA F9F Controlled Supplier Booking Retry After Accepted Fare Change

Phase: **OTA-DEVCP-F9F-CONTROLLED-SUPPLIER-BOOKING-RETRY-AFTER-ACCEPTED-FARE-CHANGE**

Generated: 2026-06-18

## Problem

Booking 53 passed F9C approval and F9E fare-change acceptance. Controlled create readiness and defer override passed, but live `sabre:controlled-create-pnr` failed at preflight with:

- `error_code=supplier_booking_retry_not_allowed`
- Prior meaningful attempt: `sabre_offer_refresh_requires_acceptance` (`needs_review`)

The retry guard is code-level in **`SupplierBookingPreflightGuard::nonRetryableFailedAttempt()`**, not stored booking meta.

## Solution

1. **`SabreControlledPnrRetryAllowanceGate`** — one-shot controlled retry allowance after F9E when:
   - Context is `controlled_pnr_command` with exact `CREATE-PNR-FOR-BOOKING-{id}` confirm
   - F9C manual review approval + F9E fare acceptance + `offer_refresh_accepted=true`
   - Historical fare gate flags present (`offer_refresh_price_changed` or `offer_refresh_requires_customer_confirmation`)
   - Readiness snapshot eligible with empty blockers
   - Prior meaningful attempt outcome is fare-acceptance compatible
   - Allowance meta not already used

2. **`SupplierBookingPreflightGuard`** — F9F gate checked before host-noop / NN / classifier retry block; passes `$controlledOperationContext` into `nonRetryableFailedAttempt()`.

3. **`SabreBookingService::createSupplierBooking()`** — records `meta.controlled_supplier_retry_allowance` once before live create when allowance applies; merges safe summary flags.

4. **`sabre:controlled-create-pnr`** — outputs `controlled_supplier_retry_allowance_used` and `controlled_supplier_retry_allowance_reason` (dry-run always false).

## Blocker location

| File | Lines / method | Error |
|------|----------------|-------|
| `app/Support/Bookings/SupplierBookingPreflightGuard.php` | `preflightAutomatedCreate()` ~146–178 | Returns `supplier_booking_retry_not_allowed` |
| `app/Support/Bookings/SabrePnrFailureClassifier.php` | `classify()` ~111–117 | `sabre_offer_refresh_requires_acceptance` → `retry_allowed: false` |

## Not changed

- No broad retry enablement; no `$explicitRetry=true` for public/admin generic paths
- No clearing of historical defer or fare-change meta
- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads / PII in meta or command output

## Verification (local)

```bash
php artisan test tests/Feature/ControlledSupplierBookingRetryAfterFareAcceptanceTest.php
php artisan test tests/Unit/Support/Bookings/SabreControlledPnrRetryAllowanceGateTest.php
php artisan test tests/Feature/SabreControlledCreatePnrCommandTest.php
```

## Live booking 53 (ops — after deploy)

1. Readiness dry-run
2. Controlled create dry-run — expect `controlled_supplier_retry_allowance_used=false`
3. Live create only when dry-run passes — expect `controlled_supplier_retry_allowance_used=true` and actual Sabre HTTP (not preflight retry block)
4. If Sabre host fails, no automatic second F9F retry (`meta.controlled_supplier_retry_allowance.used=true`)
