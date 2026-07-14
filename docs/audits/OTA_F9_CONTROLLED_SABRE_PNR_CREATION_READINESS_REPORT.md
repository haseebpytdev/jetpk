# OTA F9 Controlled Sabre PNR Creation Readiness Report

Generated: 2026-06-18  
Phase: **OTA-DEVCP-F9-CONTROLLED-SABRE-PNR-CREATION-READINESS**  
Classification: **READ-ONLY preparation** (no public auto-PNR, no checkout PNR, no ticketing, no live cancellation)

## Objective

Bridge safe booking-flow readiness (F8) and real Sabre supplier mutation by adding a unified, gated, auditable controlled PNR lane for admin/command use only.

## What changed

| Component | Path | Role |
|-----------|------|------|
| Controlled readiness facade | `app/Support/Bookings/SabreControlledPnrReadiness.php` | Composes operational/cert/pricing evaluators; normalized blockers |
| Read-only CLI | `app/Console/Commands/SabreControlledPnrReadinessCommand.php` | `sabre:controlled-pnr-readiness` |
| Create shell CLI | `app/Console/Commands/SabreControlledCreatePnrCommand.php` | `sabre:controlled-create-pnr` (dry-run default) |
| Admin state | `app/Support/Bookings/AdminBookingSupplierActions.php` | `controlled_pnr_readiness` + additive POST guard |
| Admin panel | `app/Support/Bookings/AdminSabreDiagnosticPanelsPresenter.php` | Compact controlled PNR group |
| Reason codes | `app/Support/Sabre/SabreReadinessReasonPresenter.php` | F9 blocker messages + aliases |
| Dev CP | `app/Services/Developer/DevCpMonitoringSnapshotService.php`, `resources/views/developer/monitoring/sabre.blade.php` | Controlled lane + route readiness |

## Current PNR paths (unchanged behavior)

| Path | Entry | Live create? |
|------|-------|--------------|
| Public checkout | `BookingController` → `runPublicReviewDryRun` | Only when operational flags ON (remain OFF) |
| Admin/staff | `admin.bookings.supplier-booking` | When existing gates pass |
| Manual attach | `admin.bookings.manual-pnr` | No HTTP |
| CLI cert | `sabre:certify-pnr --send` | Local/testing |
| **F9 controlled** | `sabre:controlled-create-pnr` | Dry-run default; live only with exact confirm + gates |

## Mutation posture after F9

| Capability | Status |
|------------|--------|
| Public auto-PNR | **disabled** |
| Checkout auto-PNR | **disabled** |
| Ticketing | **disabled** |
| Live cancellation | **disabled** |
| `SABRE_BOOKING_LIVE_CALL_ENABLED` | **unchanged** (expected false on live) |
| Controlled PNR create command | **shell ready** — do not run live create yet |

## Commands

### Read-only readiness (safe on production with confirm)

```bash
php artisan sabre:controlled-pnr-readiness --booking={ID} --confirm=READONLY-CONTROLLED-PNR-READINESS
php artisan sabre:controlled-pnr-readiness --reference={REF} --json
```

### Controlled create (DO NOT run live yet)

```bash
# Safe — readiness only
php artisan sabre:controlled-create-pnr --booking={ID} --dry-run

# BLOCKED while booking_live_call_enabled=false and/or readiness blockers present
# php artisan sabre:controlled-create-pnr --booking={ID} --confirm=CREATE-PNR-FOR-BOOKING-{ID}
```

## Target production sequence (F9 prepares, F10+ executes)

1. Search → 2. Revalidate/pricing context → 3. Confirm eligible booking → 4. Admin/command explicit confirmation → 5. Create Sabre PNR → 6. Retrieve PNR → 7. Store supplier reference → 8. Update diagnostics → 9. Manual review fallback

## Tests added

- `tests/Unit/Support/Bookings/SabreControlledPnrReadinessTest.php`
- `tests/Feature/SabreControlledPnrReadinessCommandTest.php`
- `tests/Feature/SabreControlledCreatePnrCommandTest.php`
- Extended `AdminBookingSupplierActionsTest`, `BookingManagementControllerSmokeTest`

## Remaining gaps (F10+)

- Live PNR create production burn-in
- Automated Sabre ticketing HTTP
- Live cancellation admin workflow
- Verified public auto-PNR lane at checkout (intentionally dormant)
- Unified `supplier_mutation` single env flag (uses `booking_live_call_enabled` + platform modules today)

## Safety confirmation

- No DB migrate/fresh
- No public-flow supplier mutation
- No raw credentials/payloads/PII in CLI or admin panels
- Docs/summary local only — not uploaded to live
