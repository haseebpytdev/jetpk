# JP-FE-06 — Flight Details, Fare Rules, Revalidation, and Deeper Return UX

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-06-FLIGHT-DETAILS-FARE-RULES-REVALIDATION-AND-DEEPER-RETURN-UX |
| Branch | `phase/jetpk-fe-06-flight-details-revalidation` |
| Objective | Operational Next.js flight-details drawer with Laravel-authoritative data, revalidation, fare-change UX, and return-combo details |
| Final status | **COMPLETE** (targeted tests pass; production untouched) |

## Included scope

- Additive Laravel JSON on `GET /flights/results/offer?format=json`
- `frontend/features/flight-details/` module (drawer, segments, timeline, baggage, fare rules, price breakdown, revalidation, fare-change dialog, return combo summary)
- Details action on result cards and return options
- IATI/Sabre revalidation before passenger handoff; Duffel direct handoff
- Fare-change explicit acceptance (IATI `price_changed`)
- Unavailable / expired / timeout / network error states
- Architecture and phase documentation

## Excluded scope

- Passenger forms / checkout in Next.js
- Supplier credential or payment changes
- URL deep-link sync for `offer` query param (deferred; drawer uses React state)
- Dashboard changes
- Production deployment

## Investigation findings

### Laravel routes audited

| Route | Method | Role |
|-------|--------|------|
| `/flights/results/offer` | GET | Blade redirect (preserved); JSON when `wantsJson` or `format=json` |
| `/flights/results/revalidate-offer` | POST | IATI + Sabre revalidation |
| `/flights/select-return-combo` | POST | Return combo handoff |
| `/flights/return-options/data` | GET | Return options list |
| `/flights/results/data` | GET | Results list (includes `fallback_details` on offers) |
| `/booking/passengers` | GET/POST | Passenger handoff destination |

### Services / presenters

- `FlightOfferDisplayPresenter` — segments, layovers, branded fares
- `FlightOfferFallbackDetailsPresenter` — baggage, fare breakdown, fare rules
- `IatiSelectedOfferRevalidationGate` / `SabreSelectedOfferRevalidationGate`
- `PublicFlightSearchSecurity` — sanitization and handoff URL allowlist
- `ReturnSplitComboService` — return combo journey mapping

## Root causes addressed

- No JSON details endpoint existed (`resultsOfferDetails` only redirected for Blade)
- Result cards had no Details action separate from price/select
- Non-IATI selection skipped user review of rules/baggage before checkout
- Return options lacked pre-selection details surface

## Files changed

### Backend
- `app/Http/Controllers/Frontend/FlightController.php` — `resultsOfferDetailsJson()`
- `tests/Feature/FlightSearch/JpFe06FlightOfferDetailsJsonTest.php`

### Frontend
- `frontend/features/flight-details/**` (new module)
- `frontend/features/flight-results/components/FlightResultCard.tsx`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/features/flight-results/components/ReturnOptionsPage.tsx`
- `frontend/features/flight-results/types/index.ts`
- `frontend/tests/flight-details.spec.ts`
- `frontend/docs/FLIGHT-DETAILS-AND-REVALIDATION-ARCHITECTURE.md`

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| Playwright `flight-details.spec.ts` | 8/8 PASS |
| Laravel `JpFe06FlightOfferDetailsJsonTest` | 6/6 PASS |

## Known limitations

- URL `offer`/`fare_option` query sync not enabled (drawer state only)
- Duffel/others: no server revalidation endpoint; Continue uses `select_url`
- Sabre fare-change at revalidation may surface as failure rather than structured dialog (IATI has structured `price_changed`)
- Details load 410 triggers in-drawer expired state in code; Playwright covers revalidation-expired path

## Security

- No raw supplier payloads in JSON
- Handoff URLs validated against internal allowlist
- Cross-search offer access returns `offer_not_found`
- CSRF preserved on POST revalidation

## Rollback

Revert merge commit on `main` or reset branch to pre-phase baseline `052f318`.

## Git SHAs

| Item | SHA |
|------|-----|
| Feature commit | `7d08b76bfb28f3ad1d5915ec0fd569ea9a2a9de5` |
| Merge commit | `dfbadcf5205a35b741bb230a1560232d648ff228` |
| Docs commit | _see `git log -1` on main after docs push_ |

## No deployment

Production was not modified.

## Next phase

JP-FE-07-GROUP-TICKETING-RESULTS-PACKAGE-DETAILS-PASSENGERS-REVIEW-MANUAL-PAYMENT-AND-CONFIRMATION
