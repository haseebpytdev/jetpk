# Flight Details and Revalidation Architecture (JP-FE-06 / JP-FE-06A)

## Overview

Next.js owns flight-details presentation and revalidation UX. Laravel remains authoritative for offer identity, pricing, fare rules, baggage, revalidation, and passenger handoff.

## Laravel contract

| Route | Method | Purpose |
|-------|--------|---------|
| `/flights/results/offer` | GET (`Accept: application/json` or `format=json`) | Authoritative offer details |
| `/flights/results/revalidate-offer` | POST | Pre-passenger revalidation (IATI, Sabre) |
| `/flights/select-return-combo` | POST | Return combo handoff (unchanged) |
| `/flights/results/offer` | GET (HTML) | Blade fallback redirect (unchanged) |

### Details request

```
GET /flights/results/offer?search_id={uuid}&offer_id={id}&fare_option_key={key}&outbound_key={key}&combo_id={id}&format=json
```

### Details response (success)

- `offer` — sanitized results-row shape plus `fallback_details` (overview, baggage, fare_breakdown, fare_rules)
- `return_combo` — when `combo_id` resolves to a return-split combo
- `search_freshness`, `revalidation_required`, `multicity_inquiry_only`

HTTP **410** on details load maps to drawer `offer-expired-state` (search/session expired).

### Revalidation

Providers:

- **IATI** — `/fare` confirmation; may return `status: fare_changed` with `revalidation.price_changed`, `original_total` / `confirmed_total` (and `old_total` / `new_total` aliases)
- **Sabre** — selected-offer refresh gate; when search refresh updates customer display total, returns the same fare-change contract (`status: fare_changed`, `requires_fare_change_acceptance: true`) before passenger handoff
- **Duffel / others** — no public revalidation endpoint; Continue uses `select_url` handoff only

### Fare-change contract (IATI + Sabre)

When the authoritative total changes, Laravel returns:

```json
{
  "success": true,
  "status": "fare_changed",
  "requires_fare_change_acceptance": true,
  "message": "The airline fare has changed…",
  "revalidation": {
    "price_changed": true,
    "revalidation_status": "changed",
    "original_total": 150000,
    "confirmed_total": 158500,
    "old_total": 150000,
    "new_total": 158500,
    "currency": "PKR"
  },
  "passengers_url": "/booking/passengers?…"
}
```

Explicit acceptance: POST `accept_fare_change=1` on the same revalidation endpoint. Laravel re-runs refresh; on success returns `status: success` and the authoritative `passengers_url`. Next.js must not auto-navigate on `fare_changed`.

Normalization lives in `App\Support\FlightSearch\PublicOfferRevalidationPresenter`.

### Passenger handoff

- Only Laravel-provided `passengers_url` or `select_url` (built with authoritative IDs)
- Frontend allowlist mirrors `PublicFlightSearchSecurity::isAllowedInternalUrl`
- No client price in handoff URLs

## Next.js surface

**Architecture choice: drawer-first**

- Full-width drawer on mobile; side drawer on desktop
- Filters and result list remain mounted when drawer closes

### Feature module

```
frontend/features/flight-details/
├── components/   FlightDetailsDrawer, FareChangeDialog, SegmentDetails, …
├── hooks/        use-flight-details, use-revalidation
├── services/     flight-details-api
├── types/
└── utils/        handoff allowlist
```

### Revalidation state machine

```
idle → loading → success | fare_change | unavailable | expired | timeout | error
fare_change → (user accepts) → POST accept_fare_change → loading → success | fare_change | error
```

`acceptFareChange()` calls `revalidateOffer({ acceptFareChange: true })` — never blind navigation on first fare change.

## Multi-city

When `multicity_inquiry_only` is true, details render with inquiry notice; Continue is disabled.

## Security

- No raw supplier payloads in API or URL
- No trusted client price or fare rules
- CSRF on POST revalidation (session cookies)
- Cross-search offer access rejected with `offer_not_found`

## Tests

- Laravel: `tests/Feature/FlightSearch/JpFe06FlightOfferDetailsJsonTest.php`, `tests/Feature/FlightSearch/JpFe06aSabreFareChangeRevalidationTest.php`
- Playwright: `frontend/tests/flight-details.spec.ts` (IATI + Sabre fare-change, details 410, acceptance, second failure)
- Frontend: `npm run typecheck`, `npm run lint`, `npm run build`
