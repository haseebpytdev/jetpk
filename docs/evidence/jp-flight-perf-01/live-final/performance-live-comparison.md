# JP-FLIGHT-PERF-01-R2 performance live comparison

Methodology differs slightly (before: Playwright TTFB; after: Invoke-WebRequest TTFB proxy). Same host (`https://jetpakistan.pk`), cold-ish samples. Not browser LCP.

| Route | Before p50 ms | After p50 ms | Delta |
|-------|---------------|--------------|-------|
| / | 656 | 1132 | 476 |
| /groups | 303 | 495 | 192 |
| /groups/search | 311 | 762 | 451 |
| /login | 295 | 666 | 371 |
| /groups/package/ALH-3348 | 386 | 1996 | 1610 |

## Capture context

| Field | Value |
|-------|-------|
| Before runtime SHA | `460cdae0441d0e07c563e636280c0e552481ac92` |
| After metrics runtime SHA | `bc83e503eb3b0fc1c7d0c4a5cfa4fac10c1fb975` |
| After metrics public build | `5GNhekE7oXu9XlFB88Mmh` |
| Current live engineering authority | `9979330c35141bc85cd5db7941f4a9c274e89a52` |
| Current public build | `e4VIZqNpoHKKio-vCpzSp` |

Note: after TTFB samples were taken on `bc83e503` before the `9979330c` SMTP/timing rebuild. Shell TTFB order-of-magnitude is unchanged across that rebuild; functional timing proof for suppliers is in `supplier-search-timing-live.json` (captured on `9979330c`).
