# Deployment

| Field | Value |
|---|---|
| AUTHORIZED_SHA | `0e747db23f9f75839ca73960dcc6fca47dab9ea1` |
| Lock | `/usr/local/sbin/jetpk-production-run` label `jp-flight-shopping-r7d` |
| PUBLIC_BUILD_ID | `nNeL8Y49UVwT1K0Dv5Br5` |
| DASHBOARD_BUILD_ID | `fbzOL_dHxc_Iq0ScPoglD` (unchanged) |
| Laravel | `optimize:clear` |
| Public Next | rebuilt as `pkjetp` |
| PM2 | jetpk-public-frontend restarted; dashboard online |
| OLS / pre-proxy | PASS |
| AUTHORIZED_RUNTIME_PARITY | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 |
| ROOT_DISK | ~21% used / ~77G free |
| SAFE_TO_PUSH | NO |

Rollbacks:

- `jp-flight-shopping-r7d-20260901T075443Z`
- `jp-flight-shopping-r7d-rb2-20260901T075443Z`
