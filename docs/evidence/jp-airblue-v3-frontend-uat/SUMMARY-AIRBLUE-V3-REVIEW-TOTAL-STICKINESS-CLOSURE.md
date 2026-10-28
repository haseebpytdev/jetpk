# SUMMARY — AirBlue V3 Review Total Stickiness Closure

## Status

`AIRBLUE_FINAL_UAT_REVIEW_TOTAL_STICKINESS_BLOCKER_CLOSED` (engineering / PR — **not** merged or deployed)

## Branch / base

| Field | Value |
|---|---|
| BASE_MAIN_SHA | `0950593947ce36caf1dbbf107cb18ccb8ebc36fb` |
| BRANCH | `fix/airblue-grouped-fare-booking-total-stickiness` |
| Scope | Draft booking + revalidation **persistence** only |
| Provider calls this loop | **0** |

## Root cause

Selected AirBlue grouped FareType **EF** customer payable (`26500`) was resolved correctly via `resolveAuthoritativeSelectedBrandedFareTotal()`, but two write sites ignored it:

1. **Initial draft** — `attachFareBreakdown(... 'total' => $pricing['final_total'])` wrote parent EV commercial total (`25000` / `25500`).
2. **Revalidation** — `revalidateCheckoutBeforeConfirmation()` computed `$selectedBrandedTotal` / `$newTotal` correctly, then overwrote `booking_fare_breakdowns.total` with `$pricing['final_total']` again.

`BookingPayableResolver::fareTotal()` / review JSON read `fare_breakdown.total` → review showed **PKR 25,000**.

Additionally, passengers **POST** only stored draft branded intent; GET alone projected `applySelectedFareFamilyOptionToOffer`. Without POST projection, validated snapshots could remain parent EV for supplier layers.

`buildCheckoutProtectionState` / revalidation wrote `meta.supplier_total` from customer `offer.total` (`pricing.final_total`), conflating supplier payable with customer payable.

## Fix

| Site | Change |
|---|---|
| Passengers POST | Project selected FareType onto `$offer` before `validateSelectedOffer` (mirror GET) |
| Initial `attachFareBreakdown` | `total` = `$selectedFareTotal` when `> 0`, else pricing fallback |
| Revalidation `attachFareBreakdown` | `total` = `$selectedBrandedTotal` / `$newTotal` when set |
| `resolveAuthoritativeSelectedBrandedFareTotal` | Customer keys only — never `supplier_total` as customer payable |
| `resolveSupplierTotalForBookingMeta` | Prefer `supplier_total_source` / `fare_breakdown.supplier_total` over customer `total` |
| Revalidation supplier meta | Keep prior EF supplier (`26000`) when snapshot regresses to parent EV |

## Commercial invariant (EF)

| Layer | Amount |
|---|---|
| Customer payable (booking / review / payable resolver) | **26500** |
| Supplier payable (meta / AirDemandTicket) | **26000** |
| Customer payable used for ticketing | **NO** |

## Tests

| Test | Result |
|---|---|
| `AirBlueGroupedFareBookingTotalStickinessTest` (initial + revalidation) | PASS |
| Targeted branded / AirBlue / checkout helpers | PASS (1 skipped: Sabre staff-confirmation gate, unrelated) |
| Provider calls | 0 |

Raw: `_raw-stickiness-targeted-final.txt`

## Safety

| Gate | Value |
|---|---|
| BOOKING_D/E/F changed | NO |
| Conn22 changed | NO |
| Public allow-list changed | NO |
| Frontend source changed | NO |
| Hotfix production | NO |
| Merge / deploy | NO — wait for release guards |

## Prior blockers (preserved, not rewritten as PASS)

1. Baggage-array `/flights/results/data` 500 — closed via PR **#136** (deployed).
2. Review fare-stickiness / customer total collapse — **this closure** (PR pending).

## Next path

`FINAL_REVIEW_MERGE_SCOPED_DEPLOY_THEN_RESUME_FINAL_AIRBLUE_MUTATION_UAT`
