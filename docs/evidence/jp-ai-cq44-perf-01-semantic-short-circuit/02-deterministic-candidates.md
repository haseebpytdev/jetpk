# Deterministic short-circuit candidates

## Implemented bypasses (`DETERMINISTIC_AUTHORITY_COMPLETE`)

| Class | Examples | Gate |
| --- | --- | --- |
| Origin follow-up | from Lahore, Lahore se | prior dest + origin_only resolved |
| Destination correction | Make it Doha, actually Dubai again | prior origin + destination_only |
| Date refinement | next Friday, kal | active travel + resolved depart/return |
| Pax refinement | 2 adults, hum dono | active travel + PassengerExpressionResolver |
| Cabin refinement | economy, business class | active travel + TravelConstraintResolver |
| Contextual return | wapis Sunday, come back on Sunday | active travel + resolved return date |

## Kept on Qwen

| Class | Reason |
| --- | --- |
| Destination-led start | No prior origin |
| Explicit A→B route | Keep planner in PERF-01 v1 |
| Open-jaw multi-leg NL | ≥2 legs |
| Ambiguous cities | LocationResolver ambiguous |
| GK / CURRENT / HIGH_RISK | Existing early paths |

## Bypass flags

| Flag | Value |
| --- | --- |
| BYPASS_PAX | YES |
| BYPASS_CABIN | YES |
| BYPASS_DATE | YES |
| BYPASS_DESTINATION_CORRECTION | YES |
| BYPASS_ORIGIN_FOLLOWUP | YES |
| BYPASS_CONTEXTUAL_RETURN | YES |
