# OTA F9D Controlled PNR Approval Override — Service Bridge

Phase: **OTA-DEVCP-F9D-CONTROLLED-PNR-APPROVAL-OVERRIDE-SERVICE-BRIDGE**

Generated: 2026-06-18

## Problem

F9C approval cleared readiness (`eligible=true`, empty blockers) but `sabre:controlled-create-pnr` still returned:

- `supplier_status=skipped`
- `error_code=defer_supplier_booking_to_manual_review`

Root cause: `SupplierBookingPreflightGuard::preflightAutomatedCreate()` honored historical `meta.defer_supplier_booking_to_manual_review` without a controlled-command override path. F9C approval only affected `SabreControlledPnrReadiness`, not the supplier booking preflight gate.

## Solution (narrow)

1. **`SabreControlledPnrApprovalOverrideGate`** — validates override only when:
   - `attemptSource=controlled_pnr_command`
   - `allowControlledStaffPnr=true`
   - controlled operation context with exact confirm phrase
   - `meta.controlled_pnr_manual_review` approved for `controlled_pnr_create`
   - historical defer meta present
   - readiness snapshot: eligible, `can_attempt_supplier_pnr`, `live_supplier_call_allowed`, `has_usable_controlled_pnr_context`
   - no existing PNR, not ticketed, not cancelled

2. **`SupplierBookingPreflightGuard`** — fourth defer bypass path via override gate when controlled context is passed.

3. **`SabreBookingService::createSupplierBooking()`** — optional 7th argument `?array $controlledOperationContext`; passes context to preflight; records `controlled_manual_review_defer_override_used` in create options / safe summary when override applies.

4. **`SabreControlledCreatePnrCommand`** — builds controlled context from readiness evaluation; outputs:
   - `controlled_manual_review_override_used`
   - `historical_defer_supplier_booking_to_manual_review`
   - `historical_supplier_pnr_deferred_reason`

Historical defer meta is **not** cleared.

## Not changed

- Public auto-PNR, checkout auto-PNR, ticketing, live cancellation
- Pricing / revalidation / stale / duplicate PNR gates
- `meta.defer_supplier_booking_to_manual_review` retention

## Dev CP (non-blocking)

`SupplierConnectionService::credentialKeysPresent()` fail-soft when `connection.provider` is null (fixes `dev_cp_sabre_status_snapshot_failed` TypeError).

## Verification (local)

```bash
php artisan test --filter=SabreControlledCreatePnrCommandTest
php artisan test --filter=SabreControlledPnrApprovalOverrideGate
php artisan test --filter=SupplierConnectionServiceNullProvider
```

## Live booking 53 (ops — after deploy)

1. Dry-run readiness + create dry-run
2. Re-run readiness JSON
3. Live create only when dry-run passes and ops approves

Do **not** run live create until dry-run and readiness re-check pass.
