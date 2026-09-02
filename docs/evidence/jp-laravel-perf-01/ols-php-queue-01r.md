# JP-LARAVEL-PERF-01R — OLS / PHP queue & swap verification

Measured 2026-09-02 under `/usr/local/sbin/jetpk-production-run` (read-only; no OLS config change).

## Host snapshot

| Signal | Value |
|---|---|
| Load average | 0.03 / 0.07 / 0.08 |
| Mem available | ~10585 MB / 11960 MB |
| Swap used | 90 MB / 2047 MB |
| SWAP_PRESSURE_CORRELATED | **NO** |
| OLS REQ_PROCESSING | 0 (idle and after samples) |
| OLS AVAILCONN | 10000 |
| LSPHP after local N20 | 8 workers (spawned on demand) |

## Latency samples (N=20)

### Public HTTPS (external client → edge)

| Metric | P50 | P95 |
|---|---|---|
| CLIENT_WALL_MS | 175 | 308 |
| INIT_RESPONSE_MS (server) | 5.2 | 21.9 |
| QUEUE_PROXY = wall − INIT_RESPONSE | 168 | 286 |

### Localhost HTTPS Host:jetpakistan.pk (removes Internet RTT)

| Metric | P50 | P95 |
|---|---|---|
| CLIENT_WALL_MS | 119 | 139 |
| INIT_RESPONSE_MS | 4.8 | 8.1 |
| LOCAL_QUEUE_PROXY (OLS+bootstrap before controller T0) | 113 | 132 |

## Decision fields

```
OLS_QUEUE_PRESSURE_CURRENT=NO
PHP_PROCESS_PRESSURE_CURRENT=NO
OLS_QUEUE_WAIT_P50_MS=113.5
OLS_QUEUE_WAIT_P95_MS=132.2
PHP_QUEUE_WAIT_P50_MS=113.5
PHP_QUEUE_WAIT_P95_MS=132.2
SWAP_PRESSURE_CORRELATED=NO
USER_VISIBLE_QUEUE_LATENCY_BLOCKER=NO
```

Notes:

- OLS vs PHP pre-controller wait cannot be split further without OLS internal span APIs; localhost proxy is the combined upper bound.
- Values ≪ 1000 ms; not a user-visible multi-second blocker.
- Earlier YES correlation during JP-LARAVEL-PERF-01 coincided with concurrent external Playwright bursts + cold TLS outliers (60s timeout / 6–11s walls) while server `INIT_RESPONSE_MS` stayed single-digit–tens of ms — i.e. edge/client contention, not sustained OLS saturation.

Raw: `ops-01r.json`, `ops-01r-local.json`.
