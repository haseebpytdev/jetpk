# Regression

| Area | Result | Notes |
|---|---|---|
| Return search init | NO (intermittent OLS connect) / retest searching on success path | One Way N30 ready; return probe flaked once then retested |
| Pair/Segmented | NO code touch | Next closure preserved |
| Nearby dates | HTTP 200 | `NEARBY_STALE_FARE_FLASH=0` (no UI flash change) |
| Groups | Deploy smoke `/groups=200` | Architecture untouched |
| Fare→Traveler | NO Next/frontend change | Closure preserved |
| Search correctness | search_id + criteria intact | Instrumentation only + memo |
| Duplicate supplier calls | 0 observed | Sequential once each |

`RETURN_SEARCH_REGRESSION=NO` (representative init OK when reachable)
`PAIR_SEGMENTED_REGRESSION=NO`
`GROUPS_REGRESSION=NO`
`NEARBY_STALE_FARE_FLASH=0`
`TRAVELER_READY_TO_FULL_SKELETON_REGRESSION=0`
