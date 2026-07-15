# OTA F9B Sabre Revalidation Context Recovery Report

Generated: 2026-06-18  
Phase: **OTA-DEVCP-F9B-SABRE-REVALIDATION-CONTEXT-RECOVERY**  
Classification: **READ-ONLY preparation + readiness bridge** (no public auto-PNR, no checkout PNR, no ticketing, no live cancellation)

## Objective

Recover/normalize controlled-certified Sabre context for bookings with legacy revalidation success but strict `has_revalidation_linkage=false`, so F9 controlled PNR readiness advances to admin confirmation gating instead of permanent `missing_revalidation_context`.

## What changed

| Component | Path | Role |
|-----------|------|------|
| Context digest | `app/Support/Bookings/SabreControlledPnrContextDigest.php` | Read-only classifier; freshness blockers; no HTTP/DB/raw payloads |
| Context CLI | `app/Console/Commands/SabreControlledPnrContextCommand.php` | `sabre:controlled-pnr-context` (production `--confirm=READONLY-CONTROLLED-PNR-CONTEXT`) |
| Readiness bridge | `app/Support/Bookings/SabreControlledPnrReadiness.php` | Uses digest for revalidation recovery; explicit freshness gates; admin confirm semantics |
| Reason codes | `app/Support/Sabre/SabreReadinessReasonPresenter.php` | `revalidation_expired`, offer refresh / price-change confirmation messages |
| Admin / Dev CP | `AdminSabreDiagnosticPanelsPresenter`, `AdminBookingSupplierActions`, `DevCpMonitoringSnapshotService`, `sabre.blade.php` | Controlled context status + F9B command visibility |

## Commands

### Read-only context digest (safe on production with confirm)

```bash
php artisan sabre:controlled-pnr-context --booking={ID} --confirm=READONLY-CONTROLLED-PNR-CONTEXT
php artisan sabre:controlled-pnr-context --reference={REF} --json
```

### Readiness (unchanged gate)

```bash
php artisan sabre:controlled-pnr-readiness --booking={ID} --confirm=READONLY-CONTROLLED-PNR-READINESS
```

### Controlled create (DO NOT run live yet)

```bash
php artisan sabre:controlled-create-pnr --booking={ID} --dry-run
# php artisan sabre:controlled-create-pnr --booking={ID} --confirm=CREATE-PNR-FOR-BOOKING-{ID}
```

## Expected bookings 53/54 shape (after deploy)

- `has_revalidation_context=true`
- `has_usable_controlled_pnr_context=true` (when meta complete: pricing_snapshot, safe refresh context, certified route)
- `can_attempt_supplier_pnr=true` when structural gates pass
- `live_supplier_call_allowed=false` until exact create confirm
- Warnings: `controlled_certified_context_used`, `legacy_revalidation_signal_used`, `public_auto_pnr_disabled`
- No `missing_revalidation_context`

**Note:** Live bookings missing `pricing_snapshot` or incomplete `sabre_safe_refresh_context` will still block with explicit digest reason — verify via context command first.

## Mutation posture (unchanged)

| Capability | Status |
|------------|--------|
| Public auto-PNR | **disabled** |
| Checkout auto-PNR | **disabled** |
| Ticketing | **disabled** |
| Live cancellation | **disabled** |
| Inspect booking commands (local/testing) | **unchanged** — production protection preserved |

## Tests added/extended

- `tests/Unit/Support/Bookings/SabreControlledPnrContextDigestTest.php`
- `tests/Feature/SabreControlledPnrContextCommandTest.php`
- `tests/Support/Bookings/ControlledPnrContextTestFixtures.php`
- Extended `SabreControlledPnrReadinessTest.php`

## Remaining gaps (F10+)

- Live controlled PNR create burn-in for bookings 53/54
- Strict BFM revalidation linkage still absent on legacy bookings — controlled path is explicit certified fallback only
- Automated Sabre ticketing HTTP
