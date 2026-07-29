# Flight Details and Revalidation Architecture (JP-FE-06)

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

### Revalidation

Providers:

- **IATI** — `/fare` confirmation; may return `revalidation.price_changed` with `original_total` / `confirmed_total`
- **Sabre** — selected-offer refresh gate; returns `passengers_url` on success
- **Duffel / others** — no public revalidation endpoint; Continue uses `select_url` handoff only

### Passenger handoff

- Only Laravel-provided `passengers_url` or `select_url` (built with authoritative IDs)
- Frontend allowlist mirrors `PublicFlightSearchSecurity::isAllowedInternalUrl`
- No client price in handoff URLs

## Next.js surface

**Architecture choice: drawer-first with URL state**

- `/flights/results?search_id=...&offer=...&fare_option=...`
- Full-width drawer on mobile; side drawer on desktop
- Filters and result list remain mounted when drawer closes

### Feature module

```
frontend/features/flight-details/
├── components/   FlightDetailsDrawer, SegmentDetails, RouteTimeline, …
├── hooks/        use-flight-details, use-revalidation
├── services/     flight-details-api
├── types/
└── utils/        handoff allowlist
```

### Revalidation state machine

```
idle → loading → success | fare_change | unavailable | expired | timeout | error
fare_change → (user accepts) → loading → success
```

## Multi-city

When `multicity_inquiry_only` is true, details render with inquiry notice; Continue is disabled.

## Security

- No raw supplier payloads in API or URL
- No trusted client price or fare rules
- CSRF on POST revalidation (session cookies)
- Cross-search offer access rejected with `offer_not_found`

## Tests

- Laravel: `tests/Feature/FlightSearch/JpFe06FlightOfferDetailsJsonTest.php`
- Playwright: `frontend/tests/flight-details.spec.ts`
- Frontend: `npm run typecheck`, `npm run lint`, `npm run build`
