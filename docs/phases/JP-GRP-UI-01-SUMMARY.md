# JP-GRP-UI-01 — SUMMARY

## Phase name
JP-GRP-UI-01 — Group Search UI + Dynamic Category Cards + CMS Live-Truth

## Branch name
`phase/jp-grp-ui-01`

## Objective
Unify JetPakistan Groups search on homepage + `/groups/search` with inventory-backed filters, dynamic category cards, exact-then-nearby date matching, and CMS live-truth for group-search hero — without enabling booking/reservation or Al-Haider token generation.

## Included scope
- Homepage tab rename Groups + SVG icons; client-side tab switch
- Shared `SharedGroupSearch` (form + category cards)
- Airline/sector/date/category independent filters
- Facets airlines + category inventory counts
- Travel date EXACT_THEN_NEARBY ±3
- group-search CMS public API exposure + live-truth matrix
- Protected production deploy + live evidence

## Excluded scope
- Group booking / reservation / payment / ticketing
- Al-Haider token login/renew
- Dashboard redesign
- Media mutation for pages without group-search media schema

## Investigation findings
- UI previously labeled “Group Ticketing”, required sector+date, omitted airlines in public facets
- `date_from` previously open-ended `>=` (false far matches)
- Category cards missing on Next homepage Groups state
- CMS presenter allowlist had GROUP_SEARCH but route `where` blocked `group-search` (404)

## Root causes
1. Product label + form contract outdated vs inventory facets
2. Date filter semantics not centralized exact-then-nearby
3. Route constraint not updated when allowlist gained `group-search`

## Exact files changed (engineering)
See commits `717691fb` and `636584a3`.

## Routes changed
- `routes/web.php` — allow `group-search` on `api/public/content/pages/{pageKey}`

## Database changes
None

## Backend changes
- Facet public payload airlines + inventory counts + travel_date_match
- Search service exact-then-nearby ±3
- PublicContent allowlist + route constraint

## Frontend changes
- Shared Groups search + category cards
- Homepage SearchModule wiring
- Groups page CMS hero consumption

## Tests executed
- PHP: GroupSearchFacetsContractTest + GroupInventorySearchTravelDateTest → 6 passed / 25 assertions
- Playwright focused suite → 24 passed
- Frontend typecheck: phase files clean (pre-existing unrelated `base-offer-fare.test.ts` TS5097)

## Screenshots
`docs/evidence/jp-grp-ui-01/20260827T091300Z/`

## Responsive / a11y
Desktop + mobile screenshots captured; labels on selects; category cards `aria-pressed`; Clear named button.

## Known limitations
- CMS media fields for group-search: none in schema (media tested=0 this phase)
- SEO em-dash encoding mojibake in stored group-search SEO title pre-existed; not mutated here beyond restore of hero fields

## Risks
Low — commercial gates remain OFF; token generation remains 0.

## Rollback
Restore from backup `jp-grp-ui-01-20260827T085240Z` (UI) / `jp-grp-ui-01-20260827T102732Z` (route) via progressive rollback package under `/home/pkjetp/releases/jetpk-rollback-<BACKUP_ID>-*`.

## Commit SHA
- FINAL_ENGINEERING_SHA=`636584a395cbc93221d7f005fcde7311915f973e`
- UI pack SHA=`717691fb1c8c0661f228024be12bcfbfb9742f28`

## Final status
READY_FOR_CHATGPT_REVIEW
