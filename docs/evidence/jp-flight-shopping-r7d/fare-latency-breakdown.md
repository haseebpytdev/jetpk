# Fare latency breakdown

## R7C residual

FARE_VALIDATE / FARE_TO_TRAVELER p95 ≈ 55s on Return continue after revalidate success.

## Live network (pre-fix)

On a successful Return Continue:

- `revalidate-offer` request→response ≈ **1.8–2s**, HTTP 200
- Wall continue→Traveler still **~55–80s**

## Root cause (application)

`use-revalidation.ts` Return-combo path after successful revalidation ignored `passengers_url` and performed a full-document `POST /flights/select-return-combo` form navigation. That duplicated handoff work and produced the pathological customer tail.

Not:

- cache-key collision
- stale client cache
- need for broad cache clearing

## R7D fix

After successful Return-combo revalidation (no fare-change modal):

1. Enrich `passengers_url` with `combo_id` / `outbound_key` / fare keys
2. Soft-nav via existing `navigateHandoff` (same as One Way)
3. Form POST retained only as fallback when `passengers_url` missing
4. Warm-start enabled for Return combos
5. Progress copy: “Checking the latest fare…” → after 8s “Refreshing availability…” → “Preparing your trip…”

## Live post-fix sample

| Metric | Value |
|---|---|
| FARE_TO_TRAVELER | **≈ 3199 ms** (first sample, Traveler reached) |
| FARE_OVER_30S_COUNT | 0 (sample) |
| FARE_OVER_45S_COUNT | 0 (sample) |
| FARE_PROGRESS_UX | PASS (`Preparing your trip…`) |
| FRESH_FARE_TO_TRAVELER | PASS |

Phase timings when rematch not required (successful live revalidate):

| Phase | Approx |
|---|---|
| REVALIDATE_INITIAL_MS | ~2s (supplier) |
| EMPTY_RESPONSE_CLASSIFY_MS | ~0 (not empty) |
| RECOVERY_SEARCH_MS | 0 (not used) |
| REMATCH_MS | 0 |
| FINALIZE_MS | included in revalidate success |
| NAVIGATION_MS | soft handoff ≪ 3s wall after response |

Additional fare samples recorded in `tmp/r7d-return-matrix.json` when matrix completes.
