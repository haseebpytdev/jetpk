# OTA F9J Controlled Retry After Clean AirPrice ValidatingCarrier Fix

Phase: **OTA-DEVCP-F9J-CONTROLLED-RETRY-AFTER-CLEAN-AIRPRICE-VALIDATING-CARRIER-FIX**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) consumed the F9F one-shot retry allowance on a live controlled create that reached Sabre and failed with:

- `ERR.SP.PROVIDER_ERROR` — Unable to perform air booking step
- `EnhancedAirBookRQ: *NO FARES/RBD/CARRIER`

F9I fixed the wire (AirPrice `ValidatingCarrier`, brand preservation). Post-F9I payload digest is structurally clean, but the second controlled create was blocked locally with `supplier_booking_retry_not_allowed` because F9F meta was already used.

## Solution

1. **`SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate`** — one-shot F9J allowance when:
   - Context is `controlled_pnr_command` with exact `CREATE-PNR-FOR-BOOKING-{id}` confirm
   - F9C manual review approval + F9E fare acceptance present
   - F9F `meta.controlled_supplier_retry_allowance.used=true` (already consumed)
   - F9J meta not already used
   - Prior meaningful attempt: `sabre_booking_application_error` + NO FARES/RBD/CARRIER host signal
   - Rebuilt payload digest clean (`hard_no_fares_rbd_carrier_risk=false`, VC present/match, brand OK, AirBook/AirPrice complete)
   - Only allowed warning: `legacy_revalidation_signal_used`

2. **`SupplierBookingPreflightGuard`** — F9J gate checked after F9F in `nonRetryableFailedAttempt()`.

3. **`SabreBookingService::createSupplierBooking()`** — enriches controlled context with `post_f9i_payload_digest_summary` before preflight; records `meta.controlled_supplier_retry_allowance_after_airprice_vc_fix` once before live create when F9J applies (mutually exclusive with F9F on same request).

4. **`SabrePassengerRecordsPayloadDigest`** — `isPostF9iCleanForControlledRetry()` / `postF9iCleanBlockers()`.

5. **CLI** — `sabre:controlled-create-pnr` (dry-run/live) and `sabre:inspect-controlled-pnr-payload-digest` emit F9J availability fields.

## Blocker location (unchanged base guard)

| File | Method | Error |
|------|--------|-------|
| `app/Support/Bookings/SupplierBookingPreflightGuard.php` | `preflightAutomatedCreate()` ~146–178 | Returns `supplier_booking_retry_not_allowed` |
| `app/Support/Bookings/SabrePnrFailureClassifier.php` | `classify()` | Prior failed create → `retry_allowed: false` unless F9F/F9J gate allows |

## Not changed

- No broad retry enablement; no `$explicitRetry=true` for public/admin generic paths
- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads / PII in meta or command output
- F9F fields and behavior unchanged

## Verification (local)

```bash
php artisan test tests/Feature/ControlledSupplierBookingRetryAfterAirpriceVcFixTest.php
php artisan test --filter=ControlledSupplierBookingRetry
php artisan test --filter=SabreControlledCreatePnrCommandTest
php artisan test --filter=PassengerRecordsPayload
```

Note: `--filter=RetryAfterAirPriceValidatingCarrier` matches no test class name; use the F9J test file path above.

## Live booking 53 (ops — after deploy)

1. Read-only payload digest:

```bash
php artisan sabre:inspect-controlled-pnr-payload-digest \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST
```

Expect: `post_f9i_payload_digest_clean=true`, `controlled_retry_after_airprice_vc_fix_available=true`, `controlled_retry_after_airprice_vc_fix_blockers=[]`.

2. Controlled create dry-run:

```bash
php artisan sabre:controlled-create-pnr --booking=53 --dry-run
```

3. **Single** live attempt (only after dry-run shows F9J available):

```bash
php artisan sabre:controlled-create-pnr --booking=53 --confirm=CREATE-PNR-FOR-BOOKING-53
```

Expect: `controlled_supplier_retry_after_airprice_vc_fix_used=true` and actual Sabre HTTP (not preflight `supplier_booking_retry_not_allowed`).

Do not run live create from Cursor.
