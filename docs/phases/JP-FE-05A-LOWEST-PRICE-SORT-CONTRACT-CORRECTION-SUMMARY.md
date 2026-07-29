# JP-FE-05A — Lowest Price Sort Contract Correction

## Phase metadata

| Field | Value |
| --- | --- |
| Phase | JP-FE-05A-LOWEST-PRICE-SORT-CONTRACT-CORRECTION |
| Branch | `phase/jetpk-fe-05a-lowest-price-sort-fix` |
| Issue | Next.js mapped both “Recommended” and “Lowest Price” to Laravel `sort=recommended` |
| Outcome | **Preferred fix applied** — Lowest Price → `cheapest` |
| Production | **Untouched** |

## Laravel sort audit

**Source:** `FlightController::sortOffers`, Blade `#ota-filter-sort`, `Phase22CFlightSearchRulesTest::test_sort_cheapest_orders_by_final_customer_price_in_json_endpoint`

| Laravel `sort` value | Behavior |
| --- | --- |
| `recommended` (default) | Bookable first, then ascending `final_customer_price` |
| `cheapest` | Same price ordering (explicit lowest-price contract; tested) |
| `price_desc` | Descending price |
| `earliest_departure` / `departure_time` | Departure time asc |
| `latest_departure` | Departure time desc |
| `fastest` / `duration` | Duration asc |
| `airline_az` / `airline_name` | Airline name asc |
| `arrival_time` | Arrival time asc |

Blade exposes “Cheapest” as `sort=cheapest`. Laravel supports a distinct authoritative lowest-price query value.

## Final UI → Laravel mapping

| UI label | URL key | Laravel `sort` |
| --- | --- | --- |
| Recommended | `recommended` | `recommended` |
| Lowest Price | `lowest_price` | `cheapest` |
| Earliest Departure | `earliest_departure` | `earliest_departure` |
| Latest Departure | `latest_departure` | `latest_departure` |
| Shortest Duration | `fastest` | `fastest` |

`parseUiSort` accepts legacy URL `cheapest` → `lowest_price` UI key.

No client-side price sorting. Sort changes refetch `flights.results.data`.

## Files changed

- `frontend/features/flight-results/utils/sorting.ts`
- `frontend/docs/FLIGHT-RESULTS-ARCHITECTURE.md`
- `frontend/tests/flight-results.spec.ts`

## Tests

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/flight-results.spec.ts` | **20/20 PASS** |
| Laravel tests | Not run — no backend changes |

## Commit SHAs

| Commit | SHA |
| --- | --- |
| Fix | `00ae405` |
| Merge to main | `53453c7` |
