# JP-FE-07A — Group Search Authoritative Sector Facets Closure

## Phase name

JP-FE-07A-GROUP-SEARCH-AUTHORITATIVE-SECTOR-FACETS-CLOSURE

## Branch

`phase/jetpk-fe-07a-group-search-facets-closure`

## Objective

Make Group search sector and category options fully Laravel-authoritative in the normal Next.js runtime path, with honest loading, empty, failure, and retry states. Remove operational fixture fallback for sectors/categories.

## Included scope

- Laravel `GET /groups/search/facets` public JSON contract
- `GroupInventoryFacetService::forPublicSearch()`
- Next.js `useGroupSearchFacets` hook with in-flight dedupe and module cache
- `GroupTicketingForm` loading/empty/error/retry states
- Homepage and `/groups/search` facet-driven validation
- Stale URL sector/category rejection without results fetch
- Targeted Playwright and Laravel contract tests
- Architecture documentation update

## Excluded scope

- Group booking, holds, payments, passenger validation, repeat-offender controls
- Standard flight search/booking
- Blade Group Ticketing removal or behavior change
- Production deployment
- Supplier/PNR/ticketing/dashboard logic

## Investigation findings

### Previous fixture behavior (JP-FE-07)

- `GroupTicketingForm` imported `GROUP_DESTINATION_FIXTURES` and `GROUP_CATEGORY_FIXTURES` as dropdown options until/unless Laravel data arrived.
- Homepage `SearchModule` and `GroupSearchPage` could present non-authoritative sectors (e.g. UK — London) as selectable live inventory.

### Audited Laravel facet source

| Facet | Source | Filtering |
|-------|--------|-----------|
| Sectors | `GroupInventoryFacetService::sectors()` — distinct `sector` from active inventory | `is_active`, available seats > 0 |
| Categories | `GroupInventoryFacetService::categoriesWithInventory()` | Active categories with inventory only (inventory-derived, not hard-coded KSA/UAE/Muscat) |
| Date bounds | Min/max `departure_date` from active inventory `departureDates()` | Not invented; `null` when no dates |

Blade search uses the same service via `GroupInventoryFacetService::all()` (`$groupFacets`).

Legacy `GET /groups/facets` unchanged for Blade/homepage backward compatibility.

## Endpoint and response contract

**Route:** `GET /groups/search/facets` (`group-ticketing.search.facets`)

**Method:** GET

**Response (JSON):**

```json
{
  "sectors": [{ "value": "LHE-JED", "label": "LHE-JED" }],
  "categories": [{ "value": "ksa", "label": "KSA" }],
  "date_bounds": { "minimum": "2026-08-01", "maximum": "2026-12-31" }
}
```

`date_bounds` is `null` when no departure dates exist. Category `value` is slug expected by `GroupTicketingSearchRequest`. No internal DB IDs or supplier data exposed.

## Next.js loading / empty / failure behavior

| State | UI | Submit |
|-------|-----|--------|
| Loading | Sector disabled; “Loading sectors…” option; status text | Blocked |
| Loaded | Laravel sectors/categories rendered | Allowed when valid |
| Empty | “No group sectors are currently available” | Blocked |
| Error | Message + “Retry loading sectors” button | Blocked |
| Stale URL sector | Sector cleared; validation message; no results fetch | Blocked until reselection |

“All” category remains local presentation-only; omits `category` query param on submit.

## Fixture removal from runtime

- Operational imports of `GROUP_DESTINATION_FIXTURES` / `GROUP_CATEGORY_FIXTURES` removed from `GroupTicketingForm` and `SearchModule`.
- Fixture files retained for Playwright/unit tests only (comment updated).

## Caching decision

- Module-level in-memory cache + in-flight request deduplication in `useGroupSearchFacets`.
- No `localStorage` / indefinite browser storage.
- Retry clears cache and refetches once.
- Playwright test helper `window.__jpResetGroupSearchFacetsCache` for isolation.

## Files changed

### Laravel

- `app/Services/GroupTicketing/GroupInventoryFacetService.php`
- `app/Http/Controllers/Frontend/GroupTicketingSearchController.php`
- `routes/web.php`
- `tests/Feature/GroupTicketing/GroupSearchFacetsContractTest.php` (new)

### Frontend

- `frontend/features/group-ticketing/hooks/use-group-search-facets.ts` (new)
- `frontend/features/group-ticketing/types/index.ts`
- `frontend/features/group-ticketing/services/group-ticketing-api.ts`
- `frontend/features/group-ticketing/components/GroupSearchPage.tsx`
- `frontend/features/group-ticketing/index.ts`
- `frontend/features/search/components/GroupTicketingForm.tsx`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/features/search/utils/validation.ts`
- `frontend/features/search/fixtures/group-categories.ts`
- `frontend/tests/group-search-facets.spec.ts` (new)
- `frontend/tests/group-ticketing.spec.ts`
- `frontend/tests/search-laravel-group-handoff.spec.ts`
- `frontend/docs/GROUP-TICKETING-BOOKING-ARCHITECTURE.md`

## Tests executed

| Suite | Result |
|-------|--------|
| `php artisan test tests/Feature/GroupTicketing/GroupSearchFacetsContractTest.php` | 3 passed, 14 assertions |
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass |
| Playwright `group-search-facets.spec.ts` | 8 passed |
| Playwright `group-ticketing.spec.ts` + `search-laravel-group-handoff.spec.ts` | 7 passed |

## Known limitations

- Sector labels mirror inventory `sector` string (same as Blade); no separate origin/destination label enrichment in this phase.
- Short-lived module cache may briefly reuse facets within a single tab session until retry or navigation refresh.

## Risks

- Low: additive Laravel route; Blade `/groups/facets` unchanged.
- Module cache could show stale options if inventory changes without retry (acceptable short interval).

## Rollback

Revert merge commit on `main` or restore JP-FE-07 fixture imports in `GroupTicketingForm` / `SearchModule` and remove `/groups/search/facets` route.

## Commit SHAs

- Feature: `bc0b150`
- Docs: `e1a36bc`
- Merge: `c81b1a7`
- Final SHA doc: `6d00c98`

## Final status

FINAL_PASS — all targeted tests green; operational fixture sectors removed from runtime.

## Next phase

JP-FE-08-STANDARD-FLIGHT-PASSENGER-CONTACT-DOCUMENTS-AND-BOOKING-SESSION-FLOW

## No-deployment confirmation

Production untouched.
