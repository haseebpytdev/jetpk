# JP-AIRBLUE-TEST-TICKETING-ENABLEMENT-01 — Preflight

## SHA

- START_MAIN_SHA / ORIGIN_MAIN_SHA: `bc2eee99d712799ccdc6349de662a8d61ae2129d`
- Contains PR #164 (QA corridor), #165–#166 (seat readiness/attach), #167 (side-effect suppression)
- LOCAL branch: `ops/jp-airblue-test-ticketing-enablement-01` @ same SHA
- WORKTREE: docs evidence only after phase

## Production smoke

- `/up` = 200
- Normal public AirBlue exposure = 0 (post-phase)

## Booking 57 DB-only (no provider)

- payment_pending / unpaid / 0.00
- seat 27A Confirmed
- traveler/flight hashes match readiness
- tickets=0 attempts=0
- ticketing_enabled was NO before enablement
