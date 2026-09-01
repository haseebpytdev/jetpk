# Performance

## Return search (10 runs)

| Metric | ms |
|---|---|
| RETURN_SEARCH_P50 | 2594 |
| RETURN_SEARCH_P95 | 4244 |
| RETURN_FIRST_USABLE_PAIR_P50 | 1057 |
| RETURN_FIRST_USABLE_PAIR_P95 | 2092 |
| RETURN_TERMINAL_STATE_P95 | 4244 |
| RETURN_OVER_30S_COUNT | 0 |

No regression vs R7C (~10s/25s).

## Fare continue (3 runs, post handoff fix)

| Metric | ms |
|---|---|
| FARE_VALIDATE_P50 | 733 |
| FARE_VALIDATE_P95 | 2023 |
| FARE_TO_TRAVELER_P50 | 5469 |
| FARE_TO_TRAVELER_P95 | 6954 |
| FARE_OVER_30S_COUNT | 0 |
| FARE_OVER_45S_COUNT | 0 |

R7C ~55s application tail removed (was select-return-combo form nav after successful revalidate).
