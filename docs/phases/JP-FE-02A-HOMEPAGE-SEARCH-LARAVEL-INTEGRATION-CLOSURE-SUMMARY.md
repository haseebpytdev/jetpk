# JP-FE-02A — Homepage Search Laravel Integration Closure

## Phase metadata

| Field | Value |
| --- | --- |
| Phase | JP-FE-02A-HOMEPAGE-SEARCH-LARAVEL-INTEGRATION-CLOSURE |
| Branch | `phase/jetpk-fe-02-homepage-search` |
| Objective | Connect Next.js homepage search to existing Laravel OTA search engine |
| Final status | **COMPLETE** |

## Audited Laravel contract

### Flight search (`PublicFlightSearchRequest`)

| Blade / query field | Laravel meaning |
| --- | --- |
| `trip_type` | `one_way`, `round_trip`, `multi_city` |
| `from`, `to` | IATA origin / destination |
| `depart` | Outbound date `Y-m-d` |
| `return_date` | Required for `round_trip` |
| `multi_from[]`, `multi_to[]`, `multi_depart[]` | Multi-city segments |
| `adults`, `children`, `infants`, `cabin` | Passenger counts + cabin |
| `stops=direct` | Direct flights only |
| `include_nearby=1` | Nearby airports on origin only |
| `flexible_dates=1` | ±1 day on outbound departure |

**Routes:**
- Init (JSON): `GET /flights/results/search` → `FlightController::resultsSearchData`
- Results handoff (HTML): `GET /flights/results` → `FlightController::results`

### Group ticketing (`GroupTicketingSearchRequest`)

| Query field | Meaning |
| --- | --- |
| `sector` | Destination / sector label |
| `date_from` | Travel date |
| `category` | Category slug (omit when `all`) |

**Route:** `GET /groups/search` → `GroupTicketingSearchController::index`

## Integration method selected

**Preferred path implemented:**

1. Next.js builds Laravel-compatible query params (`buildFlightSearchQueryParams`).
2. Same-origin `GET /laravel/flights/results/search?...` (Next rewrite → Laravel).
3. Laravel validates, runs existing `runSearch`, returns JSON including `results_page_url`.
4. Browser navigates to Laravel `results_page_url` (existing Blade results workflow).

**Group ticketing:** direct browser handoff to `GET /groups/search?...` (matches Blade `groups-panel`).

**Additive Laravel adapter:** `PublicFlightSearchRequest::failedValidation` returns JSON `422` when `expectsJson()` (HTML redirect preserved for Blade).

**Additive response field:** `results_page_url` on `/flights/results/search` JSON.

## Next.js request payload (example one-way)

```
trip_type=one_way
from=ISB
to=DXB
depart=2026-08-15
cabin=economy
adults=1
children=0
infants=0
stops=direct            # optional
include_nearby=1      # optional
flexible_dates=1      # optional
```

## Laravel response contract

```json
{
  "search_id": "uuid",
  "results_page_url": "https://…/flights/results?…",
  "initial_results_url": "https://…/flights/results/data?search_id=…",
  "criteria": { "direct_only": true, "nearby_airports": true, "flexible_dates": true },
  "warnings": []
}
```

Validation failure (`422`):

```json
{
  "message": "The given data was invalid.",
  "errors": { "depart": ["…"] }
}
```

## Changed files

### Laravel
- `app/Http/Requests/PublicFlightSearchRequest.php`
- `app/Http/Controllers/Frontend/FlightController.php`
- `tests/Feature/Frontend/NextjsFlightSearchInitJsonTest.php`

### Frontend
- `frontend/next.config.ts` — `/laravel/*` rewrite proxy
- `frontend/services/flight-search.ts` — init + handoff
- `frontend/services/airports.ts` — Laravel airport autocomplete
- `frontend/features/search/utils/laravel-payload.ts`
- `frontend/features/search/utils/laravel-errors.ts`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/features/search/components/SearchStatusBanner.tsx`
- `frontend/features/search/components/*Form.tsx`
- `frontend/features/search/components/AirportField.tsx`
- `frontend/tests/search-laravel-payload.spec.ts`
- `frontend/tests/search-laravel-handoff.spec.ts`
- `frontend/tests/homepage.spec.ts`
- `frontend/docs/HOMEPAGE-AND-SEARCH.md`

## Test results

| Suite | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| Playwright (22 tests) | **22/22 PASS** |
| `php artisan test tests/Feature/Frontend/NextjsFlightSearchInitJsonTest.php` | **2/2 PASS** |

## Known limitations

- Results page remains Laravel Blade until JP-FE results cutover.
- Cross-origin cookies require production Nginx same-origin `/laravel` proxy.
- Airport autocomplete falls back to fixtures when Laravel is unreachable.
- Group form origin field is presentation-only (Laravel group search uses sector/date/category).

## Safety confirmation

No supplier logic duplicated in TypeScript. No changes to booking, payment, PNR, or dashboard code. Blade search flow preserved.

## Next recommended phase

**JP-FE-03-PUBLIC-CONTENT-PAGES-ABOUT-SUPPORT-FAQ-CONTACT-AND-CMS-SHELL**
