# JP-FULLSTACK-01B — Search, Results and Fare-Detail Connectivity Closure

**Phase:** JP-FULLSTACK-01B
**Branch:** `phase/jetpk-fullstack-01b-public-search-results-fare-connectivity`
**Baseline:** `e40d9c232614d1e7f508d84221f3b8dbefc1c234`
**Status:** Implementation complete — **not committed** (stop for review)

## Objective

Close 01B gaps for public search connectivity: nearby-date strip, multicity inquiry handoff, return-options verification, and return-combo handoff documentation.

## Gap closure

| Gap ID | Status | Implementation |
|--------|--------|----------------|
| JP-FS01-GAP-007 | **CLOSED** | `fetchNearbyDates` + `NearbyDateStrip` on results (non-return-split) |
| JP-FS01-GAP-008 | **CLOSED** | `MulticityInquiryActions` + `submitMulticityInquiry` form POST |
| JP-FS01-GAP-010 | **CLOSED** | `frontend/tests/flight-return-options.spec.ts` |
| JP-FS01-GAP-014 | **CLOSED** | Handoff allowlist tests + documented Blade fallback |

## Laravel changes

None required beyond existing contracts. Nearby-dates and multicity inquiry routes unchanged; Blade HTML preserved.

## Frontend changes

| Area | Files |
|------|-------|
| Nearby dates | `NearbyDateStrip.tsx`, `nearby-dates.ts`, `flight-results-api.ts` |
| Multicity inquiry | `MulticityInquiryActions.tsx`, `FlightResultCard.tsx`, `FareSelectionPage.tsx` |
| Return handoff | existing `submitReturnComboSelection` (verified by tests) |
| Types | `flight-results/types/index.ts` |

## Intentional Blade fallbacks

- `POST /flights/select-return-combo` — browser form POST → Laravel redirect to checkout/passengers
- `POST /flights/multicity/inquiry` — form POST → Laravel `support.submitted` Blade thank-you

## Tests

| Command | Result |
|---------|--------|
| `npx playwright test tests/flight-return-options.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0` | **3 passed**, exit 0 |
| `npx playwright test tests/flight-results.spec.ts tests/flight-details.spec.ts tests/search-laravel-handoff.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0` | **34 passed**, exit 0 |
| `php artisan test tests/Feature/NearbyDateFareStripTest.php tests/Feature/PublicMulticityFlightResultsTest.php` | **5 passed**, 22 assertions, exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run build` | exit 0 — Next.js 15.5.22 |

**No Playwright coverage** for nearby-date strip UI or multicity inquiry UI (Laravel Feature tests only).

## Supplier calls

No live supplier calls during implementation. Nearby-date strip uses Laravel endpoint which may use fakes/cache in tests only.

## Remaining limitations

- Nearby-date strip hidden during return-split flow (matches Blade behavior)
- Multicity inquiry success page remains Laravel `support.submitted` until 01G CMS/support Next work
- Nearby strip cheapest PKR depends on Laravel supplier orchestration (not fabricated client-side)

## Rollback

Revert files listed in git diff on branch; no migrations.
