# OTA F9I Sabre AirPrice ValidatingCarrier Qualifier Fix

Phase: **OTA-DEVCP-F9I-SABRE-AIRPRICE-VALIDATING-CARRIER-QUALIFIER-FIX**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) on controlled certified `iati_like_cpnr_v2_4_gds` reached Sabre Passenger Records create with structurally correct AirBook rows but AirPrice missing `ValidatingCarrier`. F9H digest flagged `airprice_missing_validating_carrier`; F9G host signal: `EnhancedAirBookRQ: *NO FARES/RBD/CARRIER`.

## Solution

1. **`SabreBookingPayloadBuilder::buildIatiLikeCpnrV24GdsWire`** — after Brand qualifier, always merges root `AirPrice[0]...PricingQualifiers.ValidatingCarrier.Code` from draft `validating_carrier` via existing `traditionalPnrApplyRootAirPriceValidatingCarrierCompareQualifier` (F9I; independent of D2C `SABRE_TRADITIONAL_CPNR_AIRPRICE_VALIDATING_CARRIER` gate).

2. **`SabrePassengerRecordsPayloadDigest` (F9I)** — hard vs warning risk split:
   - `hard_no_fares_rbd_carrier_risk` / `hard_no_fares_rbd_carrier_risk_reasons`
   - `warning_reasons` (`legacy_revalidation_signal_used`, `missing_revalidation_linkage`)
   - `no_fares_rbd_carrier_risk` = hard only (backward compatible)
   - `airprice_validating_carrier_present`, `airprice_validating_carrier`
   - Brand consistency: `selected_context_brand_code`, `payload_airprice_brand_code`, `validated_offer_brand_code`, `accepted_fare_change_brand_code`, `brand_match`, `brand_mismatch_reason`

3. **`SabreBookingService::inspectControlledPnrPayloadDigestForBooking`** — passes validated/accepted fare brand codes into digest context.

4. **CLI** — `sabre:inspect-controlled-pnr-payload-digest` and `sabre:controlled-create-pnr` (dry-run + failure) emit new digest summary fields.

## Not changed

- No failure bypass, automatic retry, ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads, credentials, or passenger PII in command output
- No change to `SABRE_TRADITIONAL_CPNR_AIRPRICE_VALIDATING_CARRIER` default (traditional V1 checkout unchanged)

## Verification (local)

```bash
php artisan test --filter=PassengerRecordsPayload
php artisan test --filter=ControlledPnrPayloadDigest
php artisan test --filter=SabreControlledCreatePnrCommandTest
php artisan test --filter=AirPriceValidatingCarrier
```

## Live booking 53 (ops — after deploy)

Read-only payload digest:

```bash
php artisan sabre:inspect-controlled-pnr-payload-digest \
  --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST
```

**Live create safety gate (digest must show):**

- `hard_no_fares_rbd_carrier_risk=false`
- `airprice_validating_carrier_present=true`
- `validating_carrier_match=true`
- `brand_match=true` or `brand_match` null (no proven mismatch)

`warning_reasons` containing only `legacy_revalidation_signal_used` is acceptable.

Do not run live create from Cursor. Ops may consider controlled create only after digest is clean per above.
