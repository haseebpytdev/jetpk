# CQ46 Soft Preference Authority — Baseline

## Verified starting state

| Item | Value |
| --- | --- |
| PR52 (diagnostic) | MERGED |
| PR52_HEAD | `8be0e25fa731d5b8f4cbd97f7e80fab55b6f823e` |
| PR52_BASE | `15ce02cc7b79e8dc14d32ec7f271039ee4e5cfa7` |
| PR52_MERGE_SHA / MAIN after | `699832fdf5199ad52d2603c89734b0dd7bc7d680` |
| APPLICATION_RUNTIME_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` (unchanged; no deploy) |
| CQ45 | PRODUCTION CERTIFIED |
| CQ44_PERFORMANCE_PROGRAM | CLOSED |
| IFRAME_PILOT | HOLD |

## Root cause (confirmed)

`TravelConstraintResolver` already resolved ranking/time, but `ServerTravelSignals::progressiveTravelAuthority()` did not expose `ranking_refinement` / `time_refinement`, so `isTravelAuthorityTurn=false` and pending lead FSM captured `cheapest` / `fastest` / `morning` via `looksLikeBareName`.

## Scope lock

- CONFIRMATION_SNAPSHOT_SCHEMA_CHANGED=NO
- AI_DEEP_LINK_PREFERENCE_WIRING=NOT_ENABLED
- FLIGHT_SEARCH_DEEP_LINK_CHANGED=NO
- RANKING_DETERMINISTIC_COMPLETE=NO
- TIME_DETERMINISTIC_COMPLETE=NO
