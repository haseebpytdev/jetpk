# OTA F9L Controlled Retry Recovery After F9J Pre-HTTP Schema Fix

Phase: **OTA-DEVCP-F9L-CONTROLLED-RETRY-RECOVERY-AFTER-F9J-PREHTTP-SCHEMA-FIX**

Generated: 2026-06-18

## Problem

Booking 53 (PAR-4JWVIB37) after F9K:

- `cpnr_schema_validation_status=pass`, `post_f9i_payload_digest_clean=true`
- F9J meta consumed (`controlled_supplier_retry_allowance_after_airprice_vc_fix.used=true`) by pre-HTTP `sabre_booking_validation_failed`
- F9J dry-run blockers: `f9j_retry_allowance_already_used`, `no_prior_no_fares_rbd_carrier_error`
- Live create blocked: `supplier_booking_retry_not_allowed`

F9K fixed wire/schema but recovery accounting still required the original F9J NO-FARES prior on the **latest meaningful attempt**, which no longer carries that clue after the F9J schema failure attempt.

## Root cause

1. **F9J `assessAvailability`** always requires `no_prior_no_fares_rbd_carrier_error` from the meaningful attempt or current application digest — not F9J meta `previous_host_message`.
2. **Legacy F9J `recordUsage`** set `schema_validation_failed=false` before pre-HTTP failure; F9K recovery detection missed booking 53 when attempt safe_summary lacked schema pointer needles.
3. **Preflight** only bypassed retry block via F9J gate — not a post-schema-fix recovery lane.

## Solution

1. **`SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate`** (F9L) — separate one-shot gate when F9J consumed + pre-HTTP schema failure proven + current digest/schema pass + F9C/F9E/F9F satisfied.
2. **`SupplierBookingPreflightGuard`** — F9L `allows()` after F9J in `nonRetryableFailedAttempt()`.
3. **`SabreBookingService`** — `controlled_f9l_schema_recovery_eligible`; records `meta.controlled_supplier_retry_after_airprice_vc_schema_fix` after schema pass, before HTTP.
4. **CLI** — `f9j_accounting_state`, `f9k_schema_recovery_*`, `controlled_retry_after_airprice_vc_schema_fix_available` on inspect digest + controlled-create dry-run.

## Not changed

- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No broad retry bypass — F9L only on `controlled_pnr_command` + exact confirm + one-shot meta
- F9J NO-FARES retry lane semantics unchanged

## Verification (local)

```bash
php artisan test --filter=SchemaFixRetryRecovery
php artisan test --filter=ControlledSupplierBookingRetry
php artisan test --filter=SabreControlledCreatePnrCommandTest
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

Expect: `f9k_schema_recovery_available=true`, `controlled_retry_after_airprice_vc_schema_fix_available=true`, blockers empty.

Live create (once only, not from Cursor):

```bash
php artisan sabre:controlled-create-pnr --booking=53 --confirm=CREATE-PNR-FOR-BOOKING-53
```
