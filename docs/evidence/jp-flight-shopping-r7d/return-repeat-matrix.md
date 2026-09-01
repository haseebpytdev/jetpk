# Return repeat matrix

Source: sanitized `tmp/r7d-return-matrix.json` (10 Edit-Search Return runs, ISB→DXB).

| Metric | Value |
|---|---|
| RETURN_RUN_COUNT | 10 |
| RETURN_SUCCESS_COUNT | 10 |
| RETURN_EMPTY_COUNT | 0 |
| RETURN_ERROR_COUNT | 0 |
| RETURN_TIMEOUT_COUNT | 0 |
| RETURN_OVER_30S_COUNT | 0 |
| RETURN_INFINITE_SKELETON | 0 |
| RETURN_SEARCH_P50 | 2594 ms |
| RETURN_SEARCH_P95 | 4244 ms |
| RETURN_FIRST_USABLE_PAIR_P50 | 1057 ms |
| RETURN_FIRST_USABLE_PAIR_P95 | 2092 ms |
| RETURN_TERMINAL_STATE_P95 | 4244 ms |

Each run: Paired view, 12 Book Now, Copy/WhatsApp 12/12, `trip_type=round_trip`, return_date retained.

## Edit matrix A–E

| Case | RESULT_OR_TERMINAL | INFINITE | Notes |
|---|---|---|---|
| A OW→Return same depart | YES (12) | 0 | return_date retained |
| B OW→Return change depart | YES (12) | 0 | |
| C Return change return | YES (12) | 0 | |
| D Return→OW | YES | 0 | script observed URL trip_type lag; cards terminal |
| E Return change both | YES (12) | 0 | |

Material edit may preserve `search_id` while updating trip_type/return_date (R7C search_id preserve). No stale OW overwrite observed (STALE_RESPONSE_APPLIED=0).
