# JP-FE-06A — Sabre Fare-Change Structured UX Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-06A-SABRE-FARE-CHANGE-STRUCTURED-UX-CLOSURE |
| Branch | `phase/jetpk-fe-06a-sabre-fare-change-closure` |
| Objective | Close JP-FE-06 gap: Sabre fare changes surface the same structured confirmation UX as IATI, with Laravel-authoritative totals and explicit acceptance |
| Final status | **COMPLETE** (targeted tests pass; production untouched) |

## Included scope

- Additive Laravel fare-change normalization via `PublicOfferRevalidationPresenter`
- Sabre revalidation returns `status: fare_changed`, `requires_fare_change_acceptance`, `revalidation` block with old/new totals
- `accept_fare_change` continuation on IATI and Sabre revalidation POST
- Next.js `acceptFareChange()` posts authoritative acceptance to Laravel (no blind navigation)
- Sabre matcher preserves customer pricing fields after search refresh
- Playwright: Sabre fare-change, acceptance, second price change, second failure, no auto-navigation, IATI regression, details-load HTTP 410
- Laravel feature test for normalized Sabre fare-change response
- Architecture documentation update

## Excluded scope

- Supplier credentials, booking, PNR, payment, ticketing, cancellation
- Blade passenger review markup changes
- Duffel revalidation (unchanged direct handoff)
- Production deployment

## Investigation findings

| Area | Finding |
|------|---------|
| Sabre revalidation | Search refresh updated offer in store but API returned `status: success` without fare-change detection |
| IATI revalidation | Already returned `revalidation.price_changed`; normalized to `fare_changed` status when acceptance required |
| Blade | Uses `validationResult['price_changed']` and `booking.accept-updated-fare` — unchanged |
| Matcher gap | `SabreSelectedOfferDeterministicMatcher::matchArrayOffers()` dropped `final_customer_price` on DTO round-trip, preventing price-delta detection |
| Next.js | `extractFareChange()` only mapped `original_total`/`confirmed_total`; acceptance navigated without `accept_fare_change` POST |

## Root causes

1. Sabre controller path did not compare pre/post refresh customer display totals.
2. Matcher normalization stripped authoritative customer price fields from refreshed offers.
3. Frontend acceptance bypassed Laravel continuation token/flag.

## Files changed

### Backend
- `app/Support/FlightSearch/PublicOfferRevalidationPresenter.php` (new)
- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Support/FlightSearch/SabreSelectedOfferDeterministicMatcher.php`
- `tests/Feature/FlightSearch/JpFe06aSabreFareChangeRevalidationTest.php` (new)

### Frontend
- `frontend/features/flight-details/hooks/use-revalidation.ts`
- `frontend/features/flight-results/services/flight-results-api.ts`
- `frontend/features/flight-results/types/index.ts`
- `frontend/tests/flight-details.spec.ts`
- `frontend/docs/FLIGHT-DETAILS-AND-REVALIDATION-ARCHITECTURE.md`

### Documentation
- `docs/phases/JP-FE-06A-SABRE-FARE-CHANGE-STRUCTURED-UX-CLOSURE-SUMMARY.md`

## Routes changed

None (additive JSON fields on existing `POST /flights/results/revalidate-offer`).

## Database changes

None.

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| Playwright `flight-details.spec.ts` | 13/13 PASS |
| Laravel `JpFe06aSabreFareChangeRevalidationTest` | 3/3 PASS |

## Assertion counts

- Playwright: 13 tests
- Laravel JP-FE-06A: 3 tests, 26 assertions

## Accessibility / responsive

- Fare-change dialog retains `role="dialog"`, labelled title, keyboard-cancel via Go back
- Mobile drawer + fare-change dialog verified in existing mobile overflow test

## Known limitations

- Second Sabre acceptance may hit `already_fresh` gate without another supplier search (by design when recent revalidation is valid)
- CSRF proxy warnings in Playwright when Laravel is not running locally (tests mock API routes; non-blocking)

## Risks

- Low: additive API fields; Blade and Duffel paths unchanged
- Matcher pricing preservation applies only to matched offer IDs from search refresh

## Rollback

Revert merge commit on `main` or reset to pre-phase baseline.

## Git SHAs

| Item | SHA |
|------|-----|
| Feature branch commit | `672b2b874fe3ef6bf78690170f2313fca06b2953` |
| Merge to `main` | Fast-forward to `672b2b874fe3ef6bf78690170f2313fca06b2953` (no separate merge commit) |

## Production

Not deployed. No SFTP upload.
