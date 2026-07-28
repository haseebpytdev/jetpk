# JP-FE-02A-GROUP-SEARCH-IGNORED-FIELD-CORRECTION

## Phase name
JP-FE-02A-GROUP-SEARCH-IGNORED-FIELD-CORRECTION

## Branch name
`phase/jetpk-fe-02-homepage-search`

## Objective
Remove Group Ticketing search fields that are not submitted to Laravel `/groups/search`, and verify every visible field affects the handoff request.

## Included scope
- Removed Origin and Travelers & Cabin from the Group Ticketing homepage form
- Kept only `sector`, `date_from` (Travel date), and `category`
- Updated `GroupSearchDraft` to mirror submitted Laravel parameters
- Updated payload builders, validation, and handoff tests
- Added `frontend/tests/search-laravel-group-handoff.spec.ts`

## Excluded scope
- Laravel group inventory, supplier logic, and `/groups/search` backend behavior
- One Way, Return, and Multi-City search flows
- Frontend-only group filtering

## Investigation findings
- Audited Laravel contract: `GET /groups/search` accepts only `sector`, `date_from`, and `category` (`category` omitted when `all`)
- Previous form exposed Origin and Travelers fields that were never sent to Laravel
- Passenger counts are collected later in the group booking flow, not during search

## Root causes
- Group form was modeled after flight search UI instead of the authoritative Laravel group search request
- `GroupSearchDraft` retained non-submitted fields (`origin`, `destination`, `passengers`)

## Exact files changed
- `frontend/features/search/components/GroupTicketingForm.tsx`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/features/search/types/index.ts`
- `frontend/features/search/utils/laravel-payload.ts`
- `frontend/features/search/utils/validation.ts`
- `frontend/services/flight-search.ts`
- `frontend/docs/HOMEPAGE-AND-SEARCH.md`
- `frontend/tests/homepage.spec.ts`
- `frontend/tests/search-laravel-payload.spec.ts`
- `frontend/tests/search-laravel-group-handoff.spec.ts` (new)

## Routes changed
- None (frontend-only correction)

## Database changes
- None

## Backend changes
- None

## Frontend changes
- Group Ticketing form now renders Sector, Travel date, and Category only
- Group handoff builds query params exclusively from those fields
- Tests assert absent Origin/Travelers controls and verify each visible field in the handoff URL

## Tests executed
- `npm run typecheck` — pass
- `npm run lint` — pass
- `npm run build` — pass
- Playwright group tests — 6/6 pass
  - `homepage.spec.ts` (group tab)
  - `search-laravel-group-handoff.spec.ts`
  - `search-laravel-payload.spec.ts` (group payload cases)

## Assertion counts
- Playwright: 6 passed
- Payload unit assertions: 2 group cases in `search-laravel-payload.spec.ts`

## Screenshots
- Not captured (form field removal; covered by Playwright assertions)

## Responsive verification
- Not re-run manually; group form uses existing responsive grid patterns unchanged

## Accessibility verification
- Sector select and category radiogroup retain labels; removed fields no longer create misleading inputs

## Known limitations
- Group sector options remain fixture-driven until inventory API is connected

## Risks
- Low: Blade `/groups/search` contract unchanged; only homepage group form fields reduced

## Rollback instructions
- Revert this commit on `phase/jetpk-fe-02-homepage-search`
- Rebuild frontend if deployed

## Commit SHA
457b0a8

## Final status
PASS — group search form fields align with Laravel `/groups/search`; targeted tests green
