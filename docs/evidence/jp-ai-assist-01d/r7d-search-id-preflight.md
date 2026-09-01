# R7D search_id semantics preflight

## Question

Does `search_id` mean:

- **A** browser/search-session correlation whose criteria may change; or
- **B** immutable transactional search authority?

## Live proof (Edit Search One Way → Return)

Artifact: `tmp/r7d-search-id-preflight.json`

| Step | Result |
|------|--------|
| OW init | `search_id=036ee357-e619-4bdc-b2ba-70c6fe7105ff`, `trip_type=one_way` |
| Edit → Return handoff URL | **omits** `search_id`; carries new criteria only |
| New init after submit | `search_id=44cbcf0a-db63-43c4-a479-b4f42c802f5e`, `round_trip`, `return_date=2026-09-25` |
| `same_search_id` | **false** |
| Store probe for new id | returns `paired_options` for new id |

## Verdict

```
SEARCH_ID_SEMANTICS=B_TRANSACTIONAL_STORE_KEY
SEARCH_AUTHORITY_IDENTIFIER=search_id (UUID allocated by FlightSearchResultStore::beginSearch / resultsSearchData)
SEARCH_CRITERIA_VERSION_IDENTIFIER=store payload criteria snapshot keyed by search_id (+ supplier criteria_fingerprint for cache)
MATERIAL_EDIT_PRESERVES_SEARCH_ID=NO
MATERIAL_EDIT_CREATES_NEW_TRANSACTIONAL_AUTHORITY=YES
INCOMPATIBLE_OFFER_REUSE_POSSIBLE=NO
```

## Note on R7D matrix wording

The R7D note `material_edit_may_preserve_search_id_while_updating_return_criteria` was a **harness measurement false positive** (`NEW_SEARCH_CONTEXT=NO`), not production behavior. Browser cert and this dedicated preflight show material Edit Search allocates a **new** `search_id`.

`buildFlightSearchQueryParams` does not carry `search_id`; bootstrap without id → `initFlightSearch` → new `beginSearch`.

**AI work may continue.**
