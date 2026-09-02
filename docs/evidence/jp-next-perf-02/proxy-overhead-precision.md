# JP-NEXT-PERF-02A — OLS → Next proxy overhead precision

## Method

Same safe route `/groups`, compare:

- **PUBLIC_OLS**: `https://jetpakistan.pk/groups` (OLS-terminated TLS → proxy → Next)
- **NEXT_DIRECT**: `http://127.0.0.1:3000/groups` with `Host: jetpakistan.pk` on the app host

TTFB = curl `time_starttransfer` (seconds → ms).

## Samples (n=5 from 02A probe + reconfirm)

Probe set A (earlier 02A):

| i | direct_s | public_s |
|---|----------|----------|
| 1 | 0.814 | 0.309 |
| 2 | 0.127 | 0.229 |
| 3 | 0.270 | 0.234 |
| 4 | 0.136 | 0.337 |
| 5 | 0.146 | 0.337 |

Reconfirm (later):

| | ttfb_s |
|--|--------|
| public | 0.478 |
| direct | 0.257 |

Combined direct TTFB ms: 814, 127, 270, 136, 146, 257  
Combined public TTFB ms: 309, 229, 234, 337, 337, 478

## Percentiles

```
NEXT_DIRECT_TTFB_P50_MS=257
NEXT_DIRECT_TTFB_P95_MS=814
PUBLIC_OLS_TTFB_P50_MS=337
PUBLIC_OLS_TTFB_P95_MS=478
```

Paired incremental (public − direct) where both measured in same loop:

A: -505, +102, -36, +201, +191  
Reconfirm: +221

Sorted deltas ms: -505, -36, 102, 191, 201, 221

```
OLS_TO_NEXT_INCREMENTAL_OVERHEAD_P50_MS=191
OLS_TO_NEXT_INCREMENTAL_OVERHEAD_P95_MS=221
```

## Notes

- Negative delta on cold-ish direct sample shows cache/warmth asymmetry; public can be faster when edge/OLS path is warm and local Next is cold.
- Values are **not** identical to prior ~300 warm / ~1100 coldish public TTFB marketing numbers; those were broader public document timings, not pure OLS→Next incremental overhead.
- No firewall/public exposure changes.
