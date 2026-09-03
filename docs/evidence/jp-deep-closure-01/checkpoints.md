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

## ITERATION_03 (in progress)
ROOT_CAUSE=config/ota-flights.php UTF-8 BOM (EF BB BF) prepended to every Laravel JSON response → JSON.parse fails → SearchModule/cert cannot use search_id
FIX=Strip BOM from config/ota-flights.php; harden initFlightSearch + cert harness to strip \uFEFF
RESULT=pending deploy + Return/Traveler N>=30 recert
