# Fare progress UX

Customer-safe messages (no Sabre/GDS/PCC leakage):

1. Checking the latest fare…
2. Refreshing availability… (after ~8s still waiting)
3. Preparing your trip… (handoff)

FARE_PROGRESS_UX=PASS on live Traveler handoff.
FARE_REVALIDATION_ERROR_CLASSIFICATION=PASS (expired / unavailable / timeout / error states retained).
