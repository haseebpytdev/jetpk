# JP-AIRBLUE-TEST-SEAT-MODIFY-01 — Preflight

## SHA

- ORIGIN_MAIN_SHA / START_MAIN_SHA: `138698b3c58d9fae74150e5dcf59f25a284966c9`
- LOCAL branch: `docs/jp-airblue-test-seat-modify-01` @ same SHA
- WORKTREE: clean of runtime changes (docs-only evidence)

## Upstream merges on main

- PR #164 — QA AirBlue ticketing corridor (canonical in source)
- PR #165 — seat-readiness evidence

## Production smoke

- `/up` = 200
- Runtime deploy not required for this seat mutation (AncillaryService already live)

## Mutation budget

| Operation | Max | Notes |
|-----------|-----|-------|
| Fresh SeatMap | 1 | before Type=5 |
| AirBookModify Type=5 | 1 | absolute |
| Post-modify Read | 1 | authority |
| Pre-mutate Read (RPH re-derive) | 1 | Phase 4 authoritative refs |
| AirDemandTicket / payment / cancel | 0 | forbidden |
