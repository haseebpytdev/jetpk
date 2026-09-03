# JP-DEEP-CLOSURE-01 — Iteration checkpoints (continuous closure loop)

## Production baseline (post ITERATION_02 activate)
- RUNTIME_SHA=9bbb9c165aa287c01f2928bf307223055faab24e
- PUBLIC_BUILD_ID=XZ7Lahyn3D9lKHj99gRl6
- ACTIVATE=PASS FINAL_VERIFIED_ROLLBACK_COUNT=2

## ITERATION_01 (historical)
ROOT_CAUSE=Full Sabre batch priced before first onProgress (~1.5–2.5s post-network)
FIX=Early first-priced-offer progressive publish + fare store re-read cuts + processing UX
RESULT=Deployed as 7a86699d; Return P95 still >>4500; Traveler fare-bound

## ITERATION_02
ROOT_CAUSE=Home navigate waited for results shell before supplier init; early publish fired without persist
FIX=9bbb9c16 SearchModule init-before-navigate + earlyProgress only after pair persist
RESULT_DEPLOY=PASS (9bbb9c16 / XZ7Lahyn3D9lKHj99gRl6)
RESULT_RETURN_N30=FAIL first_useful P50=6773 P95=11251 (server FIRST_VALID_PAIR_PERSISTED ~2.7s)
RESULT_NOTE=init_error UTF-8 BOM on /laravel/flights/results/search — search_id overlap never engaged

## ITERATION_03
ROOT_CAUSE=config/ota-flights.php UTF-8 BOM (EF BB BF) prepended to every Laravel JSON response → JSON.parse fails → SearchModule/cert cannot use search_id
FIX=Strip BOM from config/ota-flights.php; harden initFlightSearch + cert harness to strip \uFEFF
RESULT_DEPLOY=PASS (84543e9e / BPsy9Vjym-lwJQzYb7qTs)
RESULT_RETURN_N30_COLD=P50=4068 P95=6449 (init works; still over 4500)
RESULT_TRAVELER_N30=P50=3530 P95=5018; FARE_P95=3508 supplier floor; RESPONSE_TO_NAV=0; shell_p95=524; skeleton=0

## ITERATION_04
ROOT_CAUSE=Poll loop started only after first init loadPage; cold Next contexts inflated P95
FIX=200ms poll; schedulePoll immediately; homepage prefetch of /flights/results (b7e51260)
RESULT_DEPLOY=PASS (b7e51260 / 5awYV0VBokLCj8sCbfcKS)
RESULT_RETURN_N30_COLD=P50=4225 P95=7650 (cold-context overstated)
RESULT_RETURN_N30_WARM=P50=3366 P95=4922 under4500=27/30 — still ~422ms over absolute gate

## ITERATION_03_BOM_GATE (re-verified this loop)
OTA_FLIGHTS_UTF8_BOM_PRESENT_AFTER_FIX=NO
JSON_RESPONSE_PREFIX_BYTES_VALID=YES
JSON_PARSE_FAILURE_COUNT=0
SEARCH_ID_AVAILABLE=YES
SEARCHMODULE_SEARCH_ID_USED=YES (30/30 warm)
Evidence: bom-proof-iter03.md

## ITERATION_05
ROOT_CAUSE=Poll loop awaited loadPage then always slept +200ms → ACTUAL_POLL_INTERVAL_P95≈933ms; empty polls still ran merge/setData churn; aborted/stale polls could halt cadence
FIX=Compensate poll delay; generation-guarded abort/stale continue; skip empty progressive merge/setData (1312a4c9) + TS cast for Next build
RESULT_DEPLOY=PASS (1312a4c9 / 9fwREQn-veL1kXfV9UwJO) after emergency restore from failed typed build
RESULT_RETURN_N30_WARM=P50=3689 P95=5297 under4500=26/30 — poll_interval_p95 rose to 1661 (empty setState thrash); pair_persist_p95=4163
NOTE=Failed first activate rolled back via emergency .next restore; OLS gate held

## ITERATION_06
ROOT_CAUSE=Empty progressive polls called setStatus/setMessage every tick after cadence compensation
FIX=Throttle empty-poll React message updates to ≥1s (4d6b798d)
RESULT_DEPLOY=PASS (4d6b798d / 2gcTeIoPYbh2QbaS8imqU)
RESULT_RETURN_N30=pending

## OPEN
RETURN_LATENCY_CLOSED=NO
TRAVELER_LATENCY_CLOSED=NO
PRODUCTION=4d6b798d / 2gcTeIoPYbh2QbaS8imqU
BOM_GATE=PASS (see bom-proof-iter03.md)
