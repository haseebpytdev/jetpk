# SABRE-BRANDED-FARES-BF7-A — Branded Fare PNR Readiness Audit

**Date:** 2026-06-15  
**Scope:** Audit and local/test-only comparison. No live Sabre send, no public PNR enablement, no ticketing, no payment mutation, no production flag changes.

---

## Executive summary

Production BF6 branded-fare checkout stores **FREEDOM / FL / fl-pi3** in `booking.meta.selected_fare_family_option`. Sabre Passenger Records validation returns HTTP 422:

| Field | Value |
|-------|--------|
| pointer | `/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/Brand/0` |
| message | `object instance has properties` |

OTA emits `Brand: [{ "Code": "FL" }]` on the **IATI-like CPNR v2.4** wire only. IATI/Binham operational PHP uses the **same** shape. Sabre REST JSON schema likely expects **string** array elements at `Brand[0]`, not objects with a `Code` property.

**BF7-B recommendation:** compare-only wire variant `Brand: ["FL"]` behind `SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED` (default false), plus merge `meta.selected_fare_family_option` into `_sabre_booking_context` before wire build.

---

## Files inspected

| File | Role |
|------|------|
| `app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php` | `traditionalPnrApplyRootAirPriceBrandQualifier`, B59 strip, IATI-like wire |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | `decidePassengerRecordsPayloadStyle`, `createBooking`, fare-context inspect |
| `app/Http/Controllers/Frontend/BookingController.php` | `selected_fare_family_option`, deferred snapshot merge |
| `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php` | `applyBrandedFareOptionToOfferSnapshot`, `buildSabreBookingContextHandoff` |
| `app/Support/Suppliers/SabreTraditionalCpnrIatiWireStructureDiagnostic.php` | Frozen IATI GDS CPNR template |
| `Binham/Iati_new/modules/flights/sabre/helper.php` | Live IATI CPNR Brand wiring |
| `Binham/public_html/modules/flights/sabre/helper.php` | Same (public_html copy) |
| `docs/sabre-branded-fares-bf1-audit.md` | Prior branded-fare discovery |
| `tests/Unit/SabreIatiLikeCpnrV24GdsWireTest.php` | Brand.0.Code assertions |
| `app/Support/Bookings/SabreBookingValidationManualRequestPolicy.php` | Non-blocking validation (BF6-FIX6) |

---

## Current rejected Brand node shape

### PHP (before JSON)

`SabreBookingPayloadBuilder::traditionalPnrApplyRootAirPriceBrandQualifier()`:

```php
$pq['Brand'] = [['Code' => $brandCode]];  // e.g. FL
```

### JSON path (production error)

```
CreatePassengerNameRecordRQ.AirPrice[0].PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand[0] = { "Code": "FL" }
```

### Full AirPrice row (IATI-like v2.4 after B59 + brand apply)

```json
"AirPrice": [{
  "PriceRequestInformation": {
    "Retain": true,
    "OptionalQualifiers": {
      "PricingQualifiers": {
        "PassengerType": [{ "Code": "ADT", "Quantity": "1" }],
        "Brand": [{ "Code": "FL" }]
      }
    }
  }
}]
```

### Why Sabre rejects `Brand/0`

JSON Schema at `Brand[0]` expects a **scalar/string** token. OTA sends an **object** `{ "Code": "FL" }`. Message `object instance has properties` = validator disallows properties on that node.

Same REST strictness class as BF6-FIX6 `AirPrice[0].message` (forbidden key on AirPrice row).

### Where Brand is applied

| Wire style | Brand on wire? |
|------------|----------------|
| `traditional_pnr_create_passenger_name_record_v1` (v2.5) | **No** — B59 `unset($pq['Brand'])` |
| `iati_like_cpnr_v2_4_gds` (v2.4) | **Yes** — after B59 normalize |

Production pointer at `Brand/0` implies validation used **`iati_like_cpnr_v2_4_gds`** (`decidePassengerRecordsPayloadStyle` + certified/config gating).

---

## Brand resolution chain

```
results UI (fare_option_key=fl-pi3)
  → BookingController stores meta.selected_fare_family_option { brand_code: FL, brand_name: FREEDOM, ... }
  → prepareSabreOfferForCheckoutHandoff() — does NOT call applyBrandedFareOptionToOfferSnapshot (BF6 defer)
  → meta.sabre_booking_context from base offer handoff (may lack selected tier brand_code)
  → buildInternalDraft() → _sabre_booking_context
  → traditionalPnrResolveBrandCodeFromDraft() reads:
       1. _sabre_booking_context.brand_code
       2. _sabre_booking_context.selected_brand_code
       3. _sabre_booking_context.selected_fare_family_option.brand_code (nested — not populated today)
       4. fare_family.brand_code / .code (inactive — fare_family is string in draft)
  → iati_like wire only: traditionalPnrApplyRootAirPriceBrandQualifier()
```

**BF6 gap:** `meta.selected_fare_family_option.brand_code` is **not** merged into `_sabre_booking_context` before wire build. Wire may omit Brand or use stale base-handoff code unless handoff already carried FL from shop.

---

## IATI / Binham reference shape

Frozen OTA template (`SabreTraditionalCpnrIatiWireStructureDiagnostic`):

```php
'Brand' => [['Code' => 'BRAND']],
```

IATI operational (`helper.php` GDS branch):

```php
$selectedBrand ? ['Brand' => [['Code' => $selectedBrand['code']]]] : []
```

Endpoint: `/v2.4.0/passenger/records?mode=create`.

### OTA vs IATI diff (Brand node)

| Aspect | OTA | IATI |
|--------|-----|------|
| Path | `...PricingQualifiers.Brand[0].Code` | Same |
| PHP shape | `[['Code' => $code]]` | Same |
| PassengerType sibling | Present (B59) | Present |

**No structural diff on Brand node** — rejection is Sabre REST schema vs legacy XML-hybrid object shape, not OTA-only typo.

---

## Revalidation

No `Brand` in any `SabreRevalidationPayloadBuilder` style (`bfm_revalidate_v1`, `iati_like_bfm_revalidate_v1`, etc.). Branded tier must be preserved via CPNR AirPrice (or fare basis/RBD slices), not revalidate payload.

---

## Candidate corrected shapes (BF7-B)

| Priority | Shape | Notes |
|----------|-------|-------|
| **1** | `Brand: ["FL"]` | Fixes object-at-index-0 if schema expects string array |
| 2 | `Brand: "FL"` | Scalar under PricingQualifiers |
| 3 | `Brand: { "Code": "FL" }` | Single object, not array |
| 4 | Omit Brand + per-segment FareBasis compare | `traditional_pnr_..._airprice_per_segment_fare_basis_compare_v1` |
| 5 | Keep `[{ "Code": "FL" }]` | Matches IATI; likely still 422 on strict REST |

---

## BF7-A deliverables (this sprint)

- This audit document
- `SabreBookingPayloadBuilder::summarizeAirPriceBrandQualifierForInspect()`
- `php artisan sabre:inspect-booking-payload --airprice-brand-diagnostics` (local/testing only)
- `tests/Unit/SabreBrandedFareCpnrAirPriceAuditTest.php`
- Config gate `branded_fares_airprice_brand_shape_compare_enabled` (default false)

---

## BF7-B files (planned)

| File | Change |
|------|--------|
| `SabreBookingPayloadBuilder.php` | Brand shape compare variant |
| `SabreBookingService.php` | Merge selected fare family into handoff |
| `SabreFlightSearchNormalizer.php` or `BookingController.php` | Gated `applyBrandedFareOptionToOfferSnapshot` |
| `config/suppliers.php` | Shape selector env |
| Tests | Contract + handoff merge |

**Sequencing:** local compare + tests first; controlled CERT dry-run via `sabre:compare-booking-endpoints --send` only after BF7-A closure and explicit approval — not public PNR flags.

---

## Closure checklist

- [x] Rejected Brand shape documented (PHP + JSON + error interpretation)
- [x] IATI reference documented (diff = none on Brand node)
- [x] Brand resolution chain documented (meta vs handoff gap)
- [x] Candidate shape #1 (`Brand: ["FL"]`) identified with compare-gate plan
- [x] Local inspect command + unit tests added
- [x] BF7-B file list and CERT-vs-local sequencing recorded

---

## Local verification

```powershell
php artisan test --filter=SabreBrandedFareCpnrAirPriceAuditTest
php artisan sabre:inspect-booking-payload --booking={id} --airprice-brand-diagnostics --style=iati_like_cpnr_v2_4_gds
```
