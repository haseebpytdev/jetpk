# OTA F9N Sabre Controlled Fresh Context Apply Before PNR

Phase: **OTA-DEVCP-F9N-SABRE-CONTROLLED-FRESH-CONTEXT-APPLY-BEFORE-PNR**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) is on sellability lane `refresh_required_before_retry` with F9M fresh probe reporting `ready_to_apply` / high confidence, but stale offer/revalidation context blocks a safe controlled PNR retry. F9M also had two diagnostic bugs:

1. `--reference` queried non-existent `reference_code` column instead of `booking_reference`.
2. `same_rbd_list` was always false because `SabreBookingOfferRefreshService::refresh()` never emitted that key.

## Solution

1. **F9M fixes** — `--reference` resolves `bookings.booking_reference`; `same_rbd_list` / `fare_basis_match` computed via normalized list comparison in `SabreControlledPnrSellabilityDiagnostics`.

2. **`SabreControlledFreshPnrContextApply`** — eligibility gates + safe meta marker `controlled_fresh_pnr_context_apply`.

3. **CLI `sabre:controlled-apply-fresh-pnr-context`** — dry-run (fresh shop probe, no DB write) or live apply (`--confirm=APPLY-FRESH-CONTEXT-FOR-BOOKING-{id}`) calling `SabreBookingOfferRefreshService::refresh($booking, true)`.

4. **Read-only flag** — after apply, F9M exposes `controlled_pnr_retry_after_fresh_context_apply_requires_new_approval=true` (no automatic retry allowance).

## Eligibility (summary)

- Sabre booking, no PNR/supplier_reference, not ticketed/cancelled
- F9C manual review approved
- Lane `refresh_required_before_retry`, `hard_payload_risk=false`, `cpnr_schema_validation_status=pass`
- Fresh probe: high confidence, same flights/RBD/fare-basis, `ready_to_apply`
- Price change: F9E acceptance or live confirm acknowledges refreshed fare context

## Not changed

- No PNR create, ticketing, cancellation, or retry bypass
- No public/checkout auto-PNR enablement
- No raw supplier payload/response/PII in CLI output

## Verification (local)

```bash
php artisan test --filter=SabreControlledApplyFreshPnrContext
php artisan test --filter=ControlledPnrSellability
php artisan sabre:controlled-apply-fresh-pnr-context --help
```

## Live booking 53 ops (after deploy)

1. Sellability (read-only):

```bash
php artisan sabre:inspect-controlled-pnr-sellability \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-SELLABILITY
```

2. Fresh probe (verify `same_rbd_list=true`):

```bash
php artisan sabre:inspect-controlled-pnr-sellability \
  --reference=PAR-4JWVIB37 \
  --probe-fresh-revalidate \
  --confirm=READONLY-CONTROLLED-PNR-SELLABILITY-FRESH-PROBE
```

3. Dry-run apply:

```bash
php artisan sabre:controlled-apply-fresh-pnr-context \
  --reference=PAR-4JWVIB37 \
  --dry-run
```

4. Live apply (once, only if dry-run `would_apply=true`):

```bash
php artisan sabre:controlled-apply-fresh-pnr-context \
  --reference=PAR-4JWVIB37 \
  --confirm=APPLY-FRESH-CONTEXT-FOR-BOOKING-53
```
