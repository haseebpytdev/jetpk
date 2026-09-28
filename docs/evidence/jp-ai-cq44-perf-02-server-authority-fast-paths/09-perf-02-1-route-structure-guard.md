# CQ44-PERF-02.1 — explicit-route structural guard

## Characterization (before fix)

Message: `Lahore to Dubai via Doha next Monday`

| Field | Value |
|---|---|
| VIA_ROUTE_CURRENT_COMPLETE | YES (unsafe) |
| VIA_ROUTE_CURRENT_CLASSES | explicit_route_complete |
| VIA_ROUTE_CURRENT_ORIGIN | LHE |
| VIA_ROUTE_CURRENT_DESTINATION | DXB |
| VIA_ROUTE_CURRENT_DATE | 2026-10-05 |

Hybrid also dropped via Doha (`legs=null`, `max_stops=null`). Qwen bypass would have confirmed a false simple A→B.

## Fix

- `LocationResolver::resolvedLocationMentions()` — distinct CITY_TO_IATA / KNOWN_IATA codes in message order
- `deterministicAuthorityComplete` blocks `explicit_route_complete` when `count(mentions) > 2` → `multi_location_requires_semantic`

## After fix

| Field | Value |
|---|---|
| VIA_ROUTE_CURRENT_COMPLETE | NO |
| VIA_ROUTE_CURRENT_REASON | multi_location_requires_semantic |
| VIA_ROUTE_FAST_PATH | BLOCKED |
| IATA_VIA_ROUTE_FAST_PATH | BLOCKED |

## Controls

| Case | Result |
|---|---|
| Lahore to Dubai via Doha next Monday | BLOCKED (Qwen) |
| LHE to DXB via DOH next Monday | BLOCKED (Qwen) |
| Lahore to Dubai through Doha next Monday | BLOCKED (Qwen) |
| Lahore to Dubai on Emirates next Monday | AIRLINE_CONSTRAINED_EXPLICIT_ROUTE=PASS (MODEL_CALLS=0) |
| Lahore to Dubai direct next Monday | DIRECT_EXPLICIT_ROUTE_FAST_PATH=PASS (max_stops=0 preserved) |
| Simple 2-city dated A→B corpus | still MODEL_CALLS=0 |
