# JP-UI-PERF-STABILITY-01D3 — Group Search Graph + Prefetch Policy

## Bases

| Item | Value |
|---|---|
| MAIN_BASE_SHA | `d22a52764b2aeaf3a2518624debf428272f7e3c7` |
| PRODUCTION_FRONTEND_BASE_SHA | `06b9aa465d26c45fe685aa48fc31eab78338b058` |
| PHASE_D3_PRODUCTION_MAIN_PREIMAGE_PARITY | **9_OF_9_PASS** |
| CANONICAL_BRANCH | `perf/jp-ui-perf-stability-01d3-group-prefetch` |

## BASE ownership

`DOES_GROUP_PREFETCH_PULL_AUTH_CSRF_OR_BOOKING_PAYMENT_CODE=YES`

Homepage idle `/groups/search` priority prefetch transferred shared chunks containing `ensureLaravelCsrfToken` / `X-XSRF-TOKEN` and co-located booking/payment API strings via monolithic `group-ticketing-api.ts`. Barrel import `@/features/group-ticketing` also merged booking pages into the search route graph.

## Experiments

| ID | Result | Evidence |
|---|---|---|
| **D3-A** API split + direct search page import | **ACCEPTED** | Group prefetch JS 96888→53945 (−42943, −44.3%); total homepage JS 883416→828392 (−55024) |
| **D3-B** Groups intent-only priorityPrefetch | **REJECTED** | Soft-nav home→groups P95 182→453 (material); restored priorityPrefetch |
| **D3-B2** delayed background Groups warm | **REJECTED** | Soft-nav P95 still 367 vs BASE 182; restored nav to baseline policy |
| **D3-C** flights/results card intent | **REJECTED / N_A** | Same-host idle flights RSC=0; CMS links often absolute; no measurable idle RSC to cut |

## Accepted runtime

- `group-api-base.ts` (new)
- `group-search-api.ts` (new)
- `group-ticketing-api.ts` (mutations only; no search re-exports)
- `use-group-search-facets.ts`, `GroupSearchPage.tsx` → search-api
- `app/(public)/groups/search/page.tsx` direct component import (no barrel)

## Frozen

- Login `priorityPrefetch=true` unchanged
- Groups `priorityPrefetch=true` restored (B/B2 rejected)
- `PHASE_D_A_REJECTION_REMAINS_VALID_FOR_OLD_GRAPH=YES`
- Server caching unchanged
- PRODUCTION_DEPLOYED=NO

## Gates

- GROUP_SEARCH_API_IMPORTS_AUTH_CSRF=NO
- Group facets Playwright: 9 passed
- Soft-nav policy unchanged vs BASE for shipped candidate
- Ask lazy / A/B/C media-branding-floating: no touch

## Final

```text
ACCEPTED_A=YES
ACCEPTED_B=NO
ACCEPTED_B2=NO
ACCEPTED_C=NO
FINAL_STATUS=JP_UI_PERF_STABILITY_01D3_GROUP_PREFETCH_CANDIDATE_CERTIFIED
```
