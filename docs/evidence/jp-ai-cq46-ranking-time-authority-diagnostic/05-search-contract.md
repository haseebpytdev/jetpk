# CQ46 search & results contract

## AiShoppingTools::searchFlights

Deep-link query params today:

`trip_type`, `from`, `to`, `depart`, `return_date`, `adults`, `children`, `infants`, `cabin`

| Capability | Status |
| --- | --- |
| FLIGHT_SEARCH_SUPPORTS_RANKING | **NO** (no `sort=` on AI deep-link) |
| FLIGHT_SEARCH_SUPPORTS_TIME_PREFERENCE | **NO** (no `departure_window=`) |
| FLIGHT_SEARCH_SUPPORTS_MAX_STOPS | **NO_QUERY_PARAM** (stub `stops` only for DealRanking labels) |

`DealRankingService::rank()` runs on a zero-price stub — **not** real flight offers.

## /flights/results (FlightController)

| Capability | Status |
| --- | --- |
| RESULTS_SUPPORT_CHEAPEST | YES (`sort=cheapest` / price sort) |
| RESULTS_SUPPORT_FASTEST | YES (`sort=fastest` / duration) |
| RESULTS_SUPPORT_TIME_WINDOW | YES (`departure_window` = early_morning\|morning\|afternoon\|evening) |
| RESULTS_SUPPORT_MAX_STOPS | YES_FILTER (stops filters on results) |

## DealRankingService

- Labels: CHEAPEST, FASTEST, DIRECT, SHORTEST_LAYOVER, BEST_VALUE
- Flight AI path: stub only → `FLIGHT_REAL_OFFER_RANKING_AVAILABLE=NO_VIA_AI_DEEP_LINK`
- Group search: applied to real inventory → `GROUP_REAL_OFFER_RANKING_AVAILABLE=YES_LABELS_ON_INVENTORY`

## Soft vs material (current)

| Class | Classification |
| --- | --- |
| CHEAPEST / FASTEST | SOFT_PREFERENCE_ONLY at AI layer; MATERIAL possible on results page if `sort` wired |
| SHORTEST_LAYOVER / BEST_VALUE | SOFT_PREFERENCE_ONLY / label-only; no first-class results sort param |
| MORNING / EVENING / NIGHT | SOFT_PREFERENCE_ONLY at AI; MATERIAL filter possible via `departure_window` if wired (night maps imperfectly — results windows are early_morning/morning/afternoon/evening) |
