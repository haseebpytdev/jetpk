# OTA F9G Sabre Passenger Records Application Error Diagnostics

Phase: **OTA-DEVCP-F9G-SABRE-PASSENGER-RECORDS-APPLICATION-ERROR-DIAGNOSTICS**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) reached live Sabre Passenger Records create (F9F) and failed with:

- `error_code=sabre_booking_application_error`
- Generic message: Incomplete/NotProcessed without PNR locator
- No persisted `meta.sabre_passenger_records_application_digest` or convenience fields (`sabre_last_create_*`, `sabre_booking_application_*`)

Probe data existed only in `supplier_booking_attempts.safe_summary` via `flattenBookingDiagnostics`, not on booking meta for ops inspect.

## Where `sabre_booking_application_error` is set

| Layer | File | Method |
|-------|------|--------|
| HTTP 200 classification | `app/Services/Suppliers/Sabre/Core/SabreBookingClient.php` | `normalizePassengerRecordsCpnrHttp200Response()` (~783–817) |
| Safe message | same | `safeMessageForPassengerRecordsApplicationBookingFailure()` |
| Create pipeline | `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | `createBooking()` merge on `$live['error_code']` |
| Attempt + status | same | `mapCreateBookingArrayToSupplierResult()` (~8051) |
| Public checkout | same | `finalizePublicCheckoutSabreStorage()` (~3495) |

## Solution

1. **`SabrePassengerRecordsApplicationResultDigest`** — safe ApplicationResults extractor (errors/warnings/messages, counts, key samples; no raw body/PII/secrets).

2. **`SabreBookingClient`** — attaches `passenger_records_application_digest` on Incomplete/NotProcessed application failures (no classification change).

3. **`SabreBookingService::persistPassengerRecordsApplicationFailureMeta()`** — writes:
   - `meta.sabre_passenger_records_application_digest`
   - Convenience keys: `sabre_last_create_*`, `sabre_booking_application_*`, `supplier_booking_error_*`
   - Called from controlled create + public checkout application-error paths.

4. **CLI `sabre:inspect-controlled-pnr-application-error`** — read-only; production requires `--confirm=READONLY-CONTROLLED-PNR-APPLICATION-ERROR`; fallback from latest meaningful attempt `safe_summary`.

5. **`sabre:controlled-create-pnr`** — failure output adds digest availability + first safe error fields.

## Not changed

- No failure bypass or automatic retry
- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads, credentials, or passenger PII in output

## Verification (local)

```bash
php artisan test --filter=PassengerRecordsApplication
php artisan test --filter=ControlledPnrApplicationError
php artisan test --filter=SabreControlledCreatePnrCommandTest
php artisan sabre:inspect-controlled-pnr-application-error --help
```

## Live booking 53 (ops — after deploy)

1. Inspect first (read-only):

```bash
php artisan sabre:inspect-controlled-pnr-application-error \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-APPLICATION-ERROR
```

2. If `application_error_digest_available=false`, one controlled create may be needed to capture digest (do not retry until inspect reviewed):

```bash
php artisan sabre:controlled-create-pnr \
  --booking=53 \
  --confirm=CREATE-PNR-FOR-BOOKING-53
```

3. Re-run inspect before any further retry decisions.

## Remaining gaps

- Root cause of QR LHE-DOH-JED Incomplete/NotProcessed (inventory/wire/host) — F9G exposes Sabre application signals only
- Pre-F9G attempt on booking 53 has no full digest until post-deploy create
- Admin UI does not surface digest (CLI/meta only)
