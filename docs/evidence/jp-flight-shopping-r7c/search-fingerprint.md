# Search fingerprint / cache audit

## Fingerprint fields

`trip_type`, `from`, `to`, `depart`, `return_date`, `adults`, `children`, `infants`, `cabin`, stops/nearby/flexible flags, `sort`, currency/context via Laravel search criteria; new `search_id` UUID per authoritative search.

## Collision

`ONEWAY_RETURN_CACHE_KEY_COLLISION=NO` — trip_type + return_date differentiate; new search_id on material submit.

## Edit search

Material edit creates new results URL / search_id. Old poll responses guarded by `requestSeq`.

## Cache layers (no global flush)

| Layer | Purpose | Freshness |
|---|---|---|
| Browser/Next navigation | page state | replaced on new search URL |
| Laravel FlightSearchResultStore | search payload/offers | search_id keyed TTL |
| Sabre offer freshness meta | refresh/stale windows | 300s refresh / 600s stale |
| Reprice/revalidation | checkout transition | live + search rematch recovery |

`SEARCH_CACHE_ROOT_CAUSE=NO` for hang; fare failure was live revalidation empty response, not cache collision.
