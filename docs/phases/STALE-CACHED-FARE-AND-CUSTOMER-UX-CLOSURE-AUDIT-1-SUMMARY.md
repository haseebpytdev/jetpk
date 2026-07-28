# STALE-CACHED-FARE-AND-CUSTOMER-UX-CLOSURE-AUDIT-1

## Scope extension
Browser-to-Sabre stale cached fare and customer UX closure audit, extending
`SABRE-GDS-REVALIDATION-TO-PNR-CREATION-READINESS-AUDIT-1`.

No live supplier calls.

## End-to-end path audited

```
FlightController::results / resultsData / revalidateSelectedOffer
  → FlightSearchResultStore (flight_search:{uuid}, TTL 1800s)
  → booking.passengers (search_id + offer_id + fare_option_key)
  → Booking row + meta (checkout_search_id, snapshots, sabre_booking_context)
  → booking.review GET (fare-change modal sync)
  → booking.review POST
       → guardSabreOfferFreshnessAtBookingSubmit
       → revalidateCheckoutBeforeConfirmation
       → PublicCheckoutFareChangeState / SabreOfferRefreshAcceptance gates
       → Cache::lock public-booking-review-submit
       → maybeAbortDuplicatePublicSabreBookingSubmit
       → applySabreOfferRefreshBeforePublicPnr
       → SabreBookingService::runPublicReviewDryRun → createBooking
  → booking.confirmation
```

## 18-point proof matrix

| # | Requirement | Status | Primary enforcement |
|---|-------------|--------|---------------------|
| 1 | Browser cannot submit authoritative price | **ENFORCED** | `StoreBookingPassengersRequest` has no fare total fields; pricing from `FlightSearchResultStore` + `OfferValidationService` |
| 2 | Browser cannot alter carrier/segment/class/brand/total/currency trusted | **ENFORCED** | Offer resolved from cache by `search_id`+`offer_id`; `applyValidatedFareOptionSelection`; Sabre context from `selected_fare_family_option` |
| 3 | Selection via correlation + fingerprint | **PARTIAL** | `search_id` (UUID) + `offer_id` + `fare_option_key`; `accepted_fare_context_hash` on acceptance; no public `safe_offer_fingerprint` like scenario path |
| 4 | Cache is selection context only | **ENFORCED** | Fresh revalidation mandatory before PNR; cache miss → 410 / redirect results |
| 5 | Fresh Sabre revalidation before PNR | **ENFORCED** | `runRevalidationBeforeBooking` in `createBooking`; `revalidateCheckoutBeforeConfirmation` on review POST |
| 6 | PNR uses uniquely linked revalidated candidate | **ENFORCED** (post prior phase) | Unique linkage gate in `runRevalidationBeforeBooking` |
| 7 | Expired/unavailable → zero PNR calls | **ENFORCED** | Freshness guards, revalidate fail redirect, `createBooking` short-circuit |
| 8 | Changed fare → zero PNR until explicit accept | **ENFORCED** | `requiresCustomerAcceptance`, modal, `acceptUpdatedFare`, `confirmationTotalMismatchBlocksSubmit` |
| 9 | Changed-fare UI complete | **ENFORCED** | JetPK `review-body.blade.php` modal: old/new/delta, Accept, Back to search |
| 10 | Acceptance tied to short-lived token | **PARTIAL** | Server `accepted_fare_context_hash` + `fare_change_accepted_at` (not separate expiring token) |
| 11 | Refresh/double-click no duplicate PNR | **ENFORCED** | `Cache::lock`, `maybeAbortDuplicatePublicSabreBookingSubmit`, attempt rows |
| 12 | Back-button no consumed token reuse | **PARTIAL** | POST blocks `submitted_at`; no GET review→confirmation redirect; no single-use submit token |
| 13 | Second submit returns existing booking | **ENFORCED** | `submitted_at` / PNR / successful attempt → `booking.confirmation` |
| 14 | Timeout → pending UI, no auto-retry | **PARTIAL** | Backend cooldown + processing block; no labeled reconciling screen |
| 15 | No raw Sabre errors to customer | **ENFORCED** | `SabreBookingValidationManualRequestPolicy::customerSafeMessage`, sanitized API |
| 16 | Customer-safe error mapping | **ENFORCED** | Mapped messages in review/freshness paths (fare changed, unavailable, processing) |
| 17 | JetPakistan branding | **ENFORCED** | `themes/frontend/jetpakistan/**`; leak audit commands |
| 18 | Theme/mobile/nav/loading | **ENFORCED** | `MobileViewPreference`, mobile booking views, JetPK CSS |

## Gaps requiring follow-up phase

1. **GET review redirect** when `submitted_at` set (point 12)
2. **Labeled pending/reconciling** customer screen after ambiguous PNR transmission (point 14)
3. **Public search_correlation_id** end-to-end (point 3)
4. **Public offer fingerprint** continuity shop→revalidate→PNR (point 3)
5. **Explicit expiring fare-acceptance token** vs context hash only (point 10)
6. Retire legacy `confirm_updated_fare` checkbox vs modal-only path

## Files referenced (no changes in this UX audit doc-only extension)

See prior phase app files plus:
- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Support/Bookings/PublicCheckoutFareChangeState.php`
- `app/Support/Bookings/SabreOfferRefreshAcceptance.php`
- `app/Services/FlightSearch/FlightSearchResultStore.php`
- `resources/views/themes/frontend/jetpakistan/frontend/booking/partials/review-body.blade.php`

## Tests

```bash
php artisan test --filter=StaleCachedFareAndCustomerUxClosureAuditPhaseTest
php artisan test --filter="SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest|SabreRevalidation|PublicCheckoutStabilizationTest|SabreOfferRefreshPublicCheckoutTest"
```

## Final status
**AUDIT PASS with documented PARTIAL gaps** — safe to proceed to controlled plan/PNR only after deploying linkage hardening; UX follow-up recommended before high-traffic launch.
