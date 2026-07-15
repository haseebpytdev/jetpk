# OTA F9K Sabre CPNR AirPrice ValidatingCarrier Schema Compatibility

Phase: **OTA-DEVCP-F9K-SABRE-CPNR-AIRPRICE-VALIDATING-CARRIER-SCHEMA-COMPATIBILITY**

Generated: 2026-06-18

## Problem

Booking 53 F9J controlled create reached Sabre HTTP but failed with:

- `error_code=sabre_booking_validation_failed`
- Pointer: `/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers`
- Message: `object instance has properties which are` (Sabre JSON schema additionalProperties)
- `application_error_digest_available=false` (API gateway rejection before ApplicationResults)

F9I placed `ValidatingCarrier` under `PricingQualifiers` (B79 compare-only pattern). Sabre CPNR v2.4 schema allows only `PassengerType`, `Brand`, and related keys under `PricingQualifiers`. Validating carrier belongs at `OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline.Code`.

F9J meta was consumed before host processing completed, blocking recovery after wire fix.

## Root cause

1. **Wire placement:** `PricingQualifiers.ValidatingCarrier` is not in Sabre Passenger Records v2.4 JSON schema for IATI-like CPNR.
2. **Missing local guard:** `wire_traditional_pnr_contract_valid` does not run for `iati_like_cpnr_v2_4_gds`.
3. **F9J accounting:** `recordUsage()` ran before schema validation / before meaningful host response.

## Solution

1. **`SabreBookingPayloadBuilder::buildIatiLikeCpnrV24GdsWire`** — F9K merges VC at `FlightQualifiers.VendorPrefs.Airline.Code`; strips forbidden `PricingQualifiers.ValidatingCarrier`. Brand + PassengerType unchanged.

2. **`SabreCpnrIatiWireSchemaValidator`** — pre-HTTP validation for IATI AirPrice optional qualifiers; safe `cpnr_schema_validation_*` diagnostics.

3. **`SabreBookingService::createBooking()`** — blocks IATI live POST when local schema fails (`live_call_attempted=false`); records F9J usage only after schema pass, immediately before HTTP.

4. **`SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate`** — schema-only failures do not fully consume allowance; booking 53 recovery when `schema_validation_failed=true` without `host_application_results_received`.

5. **`SabrePassengerRecordsPayloadDigest`** — VC from FlightQualifiers; `post_f9i_payload_digest_clean` requires schema pass.

6. **CLI** — `cpnr_schema_validation_*` on inspect digest and controlled-create dry-run/failure.

## Not changed

- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No schema validation bypass
- B79 compare wire (`PricingQualifiers.ValidatingCarrier`) unchanged (inspect-only)

## Verification (local)

```bash
php artisan test tests/Unit/SabreCpnrIatiWireSchemaValidationTest.php
php artisan test tests/Unit/SabreAirPriceValidatingCarrierTest.php
php artisan test tests/Feature/ControlledSupplierBookingRetryAfterAirpriceVcFixTest.php
```

## Live booking 53 (ops — after deploy)

Read-only digest:

```bash
php artisan sabre:inspect-controlled-pnr-payload-digest \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST
```

Dry-run:

```bash
php artisan sabre:controlled-create-pnr --booking=53 --dry-run
```

Expect: `cpnr_schema_validation_status=pass`, `controlled_retry_after_airprice_vc_fix_available=true`, blockers empty.

Live create (once only, not from Cursor):

```bash
php artisan sabre:controlled-create-pnr --booking=53 --confirm=CREATE-PNR-FOR-BOOKING-53
```
