# CQ46.1 — Scope cleanup & resolver-family closure

## Blocker A removed

`TravelConstraintResolver` bare `business` cabin expansion **reverted**.

Pre-CQ46 cabin business tokens restored exactly:

- business class
- business cabin
- make it business / make that business

Bare `business` → cabin=null (unchanged pre-CQ46 semantics).

`BUSINESS_CABIN_VOCABULARY_CHANGED=NO`

Mixed cabin+ranking now uses canonical phrase:

`business class and cheapest` → cabin=business, ranking=CHEAPEST.

## Soft path intent preservation

`trySoftTravelPreferenceTurn` now preserves prior `intent` alongside material travel slots so active `flight_search` is not silently downgraded.

## Full resolver-family endpoint coverage

Ranking: cheapest/cheap/sasti/سستی, fastest/fast/jaldi, shortest layover + long layover nahi, best value/best option/best.

Time: morning/subah/صبح, evening/shaam/شام, night/raat/رات.

## Explicit-name collisions

Characterization only (not CQ46.1 PASS claims):

See `08-name-controls.json` for `EXPLICIT_NAME_*_BEHAVIOR` labels.

## Gates

Backend regression after cleanup: see `09-nonregression.json`.
Frontend continuity 11/11 + typecheck PASS.
