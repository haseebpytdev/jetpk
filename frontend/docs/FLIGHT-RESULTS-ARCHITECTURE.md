# Flight Results Architecture (JP-FE-05)

## Overview

Next.js owns **presentation only** for public flight search results. Laravel remains authoritative for search execution, offer normalization, pricing, branded fares, revalidation, and booking handoff.

## Routes

| Route | Purpose |
| --- | --- |
| `/flights/results` | Operational results page (search_id or criteria query) |
| `/flights/return-options` | Return-split step 2 (authoritative combo selection) |

Laravel Blade `/flights/results` remains available as fallback during migration.

## Laravel endpoints consumed

| Endpoint | Method | Role |
| --- | --- | --- |
| `/flights/results/search` | GET | Search init (blocking); returns `search_id` |
| `/flights/results/data` | GET | Paginated/filtered/sorted offers |
| `/flights/results/revalidate-offer` | POST | IATI (and other) pre-checkout revalidation |
| `/flights/return-options/data` | GET | Return-split compatible combos |
| `/flights/select-return-combo` | POST | Combo selection → Laravel checkout redirect |

Proxied via Next.js `/laravel/*` rewrite with session cookies.

## Result JSON contract

Primary shape from `FlightController::resultsData`:

```json
{
  "search_id": "uuid",
  "page": 1,
  "per_page": 12,
  "total": 40,
  "has_more": true,
  "filters": { "airlines": [], "stops": [], "price_range": {} },
  "offers": [],
  "warnings": [],
  "empty_message": "",
  "search_freshness": { "expires_at": "...", "expires_display": "..." }
}
```

Return-split outbound uses `flow: "return_split_outbound"` and `outbound_options[]` instead of `offers[]`.

Offer rows are mapped by `mapOfferForResultsApi` — opaque `offer_id`, authoritative `displayed_price` / `price_display`, branded fare arrays, `select_url`, `can_book`, segment display fields.

## Search lifecycle

1. Homepage search → `initFlightSearch` → navigate to `/flights/results?search_id=…&criteria…`
2. Results page fetches `/flights/results/data` (no client-side supplier calls)
3. Filters/sort update URL query → refetch from Laravel
4. Load more increments `page` (server pagination)
5. Expiry: HTTP 410 → expired state; countdown from `search_freshness.expires_display` only

No polling — Laravel search is synchronous at init.

## Sorting (UI → Laravel `sort` query)

Next.js never re-sorts offers client-side. Sort changes update the URL `sort` key (UI value) and refetch `flights.results.data` with the mapped Laravel value.

| UI label | URL `sort` (UI key) | Laravel `sort` |
| --- | --- | --- |
| Recommended | `recommended` | `recommended` |
| Lowest Price | `lowest_price` | `cheapest` |
| Earliest Departure | `earliest_departure` | `earliest_departure` |
| Latest Departure | `latest_departure` | `latest_departure` |
| Shortest Duration | `fastest` | `fastest` |

Laravel also accepts `price_desc`, `airline_az`, `arrival_time`, `duration` (Blade-only extras). `cheapest` is the authoritative lowest-price value (`FlightController::sortOffers`, Blade `#ota-filter-sort`, `Phase22CFlightSearchRulesTest`).

## Selection / handoff

- **One-way / combined RT offers:** `select_url` + `offer_id` + `fare_option_key` + `search_id` → Laravel `/booking/passengers`
- **IATI:** POST `revalidate-offer` first, then `passengers_url`
- **Return split:** outbound → `/flights/return-options` → POST `select-return-combo` (full `combo_id`)

Next.js never recalculates price or stitches return pairs.

## Return results decision

- **Return split enabled** (`OTA_RETURN_SPLIT_SELECT_ENABLED`): outbound cards in Next.js; return combos on `/flights/return-options`
- **Combined supplier RT offers** (non-split): rendered as normal `offers[]` pair itineraries
- No manual outbound+return stitching

## Multi-city decision

- Results may display with `multicity_inquiry_only` when `PublicMulticityInquiryPolicy` blocks checkout
- Inquiry CTA uses Laravel `inquiry_url`; no Next.js checkout

## Feature structure

```
frontend/features/flight-results/
├── components/   # UI (cards, filters, states, carousel)
├── hooks/        # use-flight-results, use-offer-selection
├── services/     # flight-results-api.ts
├── types/
├── utils/
└── index.ts
```

## Security

- No supplier credentials in frontend
- No raw supplier payloads in URLs
- Offer selection always validated by Laravel
- CSRF on POST handoffs

## Known limitations

- Offer details route remains Laravel stub redirect
- Return combo select uses form POST to Laravel (not JSON)
- Multi-city automatic checkout not supported
- Debug/diagnostic fare fields not exposed in Next.js

## Next phase

JP-FE-06: flight details, fare rules, deeper return UX, revalidation surfaces.
