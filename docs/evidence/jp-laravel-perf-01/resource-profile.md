# Resource profile

| Signal | Observation |
|---|---|
| PHP_PROCESS_PRESSURE_CORRELATED | YES for client init wall tails (60s timeout; 6–11s outliers) while server INIT_RESPONSE_MS stays <10 ms |
| OLS_REQUEST_QUEUE_PRESSURE | LIKELY for the same tails (`x-powered-by=CyberPanel-OLS`) |
| CPU_PRESSURE_CORRELATED | NO for application-controlled pre-supplier (P95 59 ms) |
| SWAP_PRESSURE_CORRELATED | NOT measured this phase |
| PUBLIC_BUILD_ID | Unchanged `U9-V-YGZgQ3qKayMCp4BX` |
| PM2 | public + dashboard online; PIDs unchanged |

OLS SHA256 gate: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` PASS.
