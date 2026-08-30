# JP-FINAL-CLOSURE-01 — Deployment report (R6)

## Final activation

| Field | Value |
|---|---|
| AUTHORIZED_ENGINEERING_SHA | `a603211f0b5cebf73c1770532cfed649030b7a1f` |
| PUBLIC_BUILD_ID | `38WrCuLnbbv8LChWWw4_M` |
| DASHBOARD_BUILD | UNCHANGED (public-only) |
| ACTIVATE | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| AUTHORIZED_RUNTIME_PARITY | PASS |
| OLS | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| JETPK_PRODUCTION_LOCK_ACQUIRED | YES (r6d + r6e) |
| PRODUCTION_LOCK_RELEASED | YES (RC=0) |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 |
| ROOT_DISK_USED_PERCENT | 20 |
| ROOT_DISK_FREE_GB | ~77 |

## R6 deployment chain

| Wave | SHA | Build | Backup | Notes |
|---|---|---|---|---|
| R6a | `9d76e579` | `qsBFg1yUprktIXyC-8xno` | prior r2 pack | checkout group + timing headers |
| R6b | `94db66f3` | `zxyv3UEi7QaxS9gaI-NUd` | activate-only | anonymous checkout layout |
| R6c | `691d9c61` | `I2Mw1uJud68Msh_oxawVW` | activate-only | hard-nav Book Now |
| R6d | `6b567d44` | `3mgDtlAXvWjtv8Ez_EyaU` | `jp-final-closure-01-r2-20260830T160807Z` | wall-clock timing |
| R6e | `a603211f` | `38WrCuLnbbv8LChWWw4_M` | fresh under lock | Traveler restore on mount |

## PM2

- Public frontend restarted only on frontend waves
- Dashboard PID unchanged when public-only

## Preview-only fixture note

`JetpkEmailSampleDataProvider` remains **HARNESS_FIXTURE** / preview support — not claimed as authorized runtime authority for exact deploy manifests.
