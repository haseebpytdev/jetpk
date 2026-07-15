# OTA F9H Sabre EnhancedAirBook NO FARES/RBD/CARRIER Diagnostics

Phase: **OTA-DEVCP-F9H-SABRE-ENHANCED-AIRBOOK-NO-FARES-RBD-CARRIER-DIAGNOSTICS**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) reaches Sabre Passenger Records create and fails with application errors including:

- `ERR.SP.PROVIDER_ERROR — Unable to perform air booking step`
- `WARN.SWS.HOST.ERROR_IN_RESPONSE — EnhancedAirBookRQ: *NO FARES/RBD/CARRIER`

F9G exposed **response-side** application errors. The blocker is now the air-booking step rejecting fare/RBD/carrier mapping or CPNR `AirBook`/`AirPrice` wire shape — not Laravel approval/retry/fare gating.

**Note:** OTA builds `CreatePassengerNameRecordRQ.AirBook` (not a separate `EnhancedAirBookRQ` node). Sabre host messages may still reference `EnhancedAirBookRQ`.

## Solution

1. **`SabrePassengerRecordsPayloadDigest`** — safe structural digest of final CPNR wire before HTTP:
   - `AirBook` segment sell rows (carrier, RBD, route, datetimes; max 6)
   - Root `AirPrice` qualifiers (validating carrier, brand, PTC type codes/counts as `type_codes` / `type_code_counts`)
   - Context comparison vs selected booking segments
   - `no_fares_rbd_carrier_risk` + reason codes

2. **`SabreBookingService::inspectControlledPnrPayloadDigestForBooking()`** — rebuilds the same controlled wire as `sabre:controlled-create-pnr` (certified route style, allow-NN policy); no HTTP, no DB mutation.

3. **CLI `sabre:inspect-controlled-pnr-payload-digest`** — read-only; production requires `--confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST`.

4. **`sabre:controlled-create-pnr`** — failure output adds slim payload digest fields (`payload_digest_available`, `no_fares_rbd_carrier_risk`, `airbook_segment_count`, etc.).

5. **Live create path** — `buildCreatePayloadSafeSummaryForLiveAttempt()` attaches `passenger_records_payload_digest` on envelope build (no extra supplier call).

## Not changed

- No failure bypass or automatic retry
- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads, credentials, or passenger PII in output

## Verification (local)

```bash
php artisan test --filter=PassengerRecordsPayload
php artisan test --filter=ControlledPnrPayloadDigest
php artisan test --filter=SabreControlledCreatePnrCommandTest
php artisan sabre:inspect-controlled-pnr-payload-digest --help
```

## Live booking 53 (ops — after deploy)

1. Payload digest (read-only):

```bash
php artisan sabre:inspect-controlled-pnr-payload-digest \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST
```

2. Pair with F9G application-error inspect:

```bash
php artisan sabre:inspect-controlled-pnr-application-error \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-APPLICATION-ERROR
```

3. **Do not retry live create** until digest shows `no_fares_rbd_carrier_risk=false` and ops reviews mismatch reasons.

## Remaining gaps

- Host rejection may persist when wire structure is clean (inventory/fare availability beyond shape)
- AirPrice brand/validating-carrier qualifier fixes are follow-on only if digest identifies concrete mismatch
