# OTA F9E Controlled Fare Change Acceptance Before PNR Retry

Phase: **OTA-DEVCP-F9E-CONTROLLED-FARE-CHANGE-ACCEPTANCE-BEFORE-PNR-RETRY**

Generated: 2026-06-18

## Problem

Booking 53 passed F9C approval and F9D defer override, but controlled PNR create failed safely with:

- `error_code=sabre_offer_refresh_requires_acceptance`
- `offer_refresh_price_changed=true`
- `offer_refresh_requires_customer_confirmation=true`
- `offer_refresh_accepted=false`

Controlled retry must not bypass fare-change acceptance silently.

## Solution

1. **`SabreControlledPnrFareChangeAcceptance`** — eligibility + safe acceptance record builder (fingerprints only; no raw payloads/PII).

2. **`sabre:accept-controlled-pnr-fare-change`** — meta-only operator gate:
   - `--dry-run` / missing `--confirm` → no DB mutation
   - Exact `--confirm=ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-{id}` writes:
     - `meta.controlled_pnr_fare_change_acceptance`
     - `offer_refresh_accepted=true` (+ accepted_at/by/source)
   - Historical `offer_refresh_price_changed` and defer meta preserved

3. **`SabreControlledPnrReadiness`** — explicit fare-change blockers until F9E acceptance; exposes `controlled_pnr_fare_change_accepted`.

4. **`SabreControlledPnrContextDigest`** — warning `controlled_fare_change_accepted` when acceptance meta present.

5. **`SabreControlledPnrApprovalOverrideGate`** — requires F9E acceptance when fare-change gate active.

6. **`sabre:controlled-create-pnr`** — outputs historical fare flags + `controlled_pnr_fare_change_accepted`; live create requires F9C + F9E when applicable.

## Not changed

- No supplier HTTP from acceptance command
- No ticketing, cancellation, public auto-PNR, checkout auto-PNR enablement
- No clearing of historical defer or fare-change audit flags

## Verification (local)

```bash
php artisan test tests/Feature/SabreAcceptControlledPnrFareChangeCommandTest.php
php artisan test tests/Unit/Support/Bookings/SabreControlledPnrReadinessTest.php
php artisan test tests/Feature/SabreControlledCreatePnrCommandTest.php
php artisan test tests/Unit/Support/Bookings/SabreControlledPnrApprovalOverrideGateTest.php
```

Note: `--filter=ControlledPnrFareChangeAcceptance` matches no test class name; use file path above.

## Live booking 53 (ops — after deploy)

1. Fare acceptance dry-run
2. Fare acceptance exact confirm
3. Readiness re-check
4. Controlled create dry-run
5. Live create only when dry-run + readiness pass (do not skip)
