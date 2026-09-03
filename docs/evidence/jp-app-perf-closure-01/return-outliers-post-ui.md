# JP-APP-PERF-CLOSURE-01 — Return outlier pass (post Codex UI deploy)

Runtime: `278f515b` / build `TYhGG2FlsC2x-VSH6QfBN`
Harness: shared warm browser context, N=30 valid (31 attempts, 1 card timeout)

## Headline

| Metric | Value |
| --- | --- |
| RETURN_FIRST_USEFUL_P50_MS | 4366 |
| RETURN_FIRST_USEFUL_P95_MS | 7136 |
| RETURN_FIRST_VALID_PAIR_P95_MS | 3320 |
| SHELL_P95_MS | 4069 |
| INIT_P95_MS | 2749 |
| PAIR_CREATE_TO_PERSIST_P95_MS | 22 |
| PAIR_PERSIST_TO_POLL_READABLE_P95_MS | 1237 |
| BROWSER_RENDER_P95_MS | 596 |
| DUPLICATE_SEARCH | 0 |

Gate `<=4500` **miss** — dominated by **COLD_POST_DEPLOY** Next shell/init (this run started immediately after public rebuild), not by pair creation floor.

## Outliers >4500 (12)

Classification (multi-label; not mutually exclusive):

| Sample | FU | Pair | Shell | Init | Persist→Readable | Dominant |
| --- | --- | --- | --- | --- | --- | --- |
| 15 | 7580 | 2670 | 1778 | 846 | 1237 | CLIENT_PROCESSING + POLLING (poll gap P95 2720) |
| 18 | 7136 | 2656 | 1626 | 765 | 233 | CLIENT_PROCESSING (poll gap P95 3527) + RENDER |
| 17 | 5643 | 2891 | 4213 | 2749 | 3 | COLD shell/init (shell 4213) |
| 19 | 5583 | 4498 | 1609 | 695 | 97 | SUPPLIER_NETWORK (pair itself 4498) |
| 04 | 5572 | 3313 | 2609 | 1566 | 496 | COLD shell + POLLING |
| 07 | 5479 | 1226 | 3017 | 2144 | 502 | COLD shell/init |
| 09 | 5029 | 1071 | 4069 | 3172 | 1821 | COLD shell + PUBLICATION/POLL lag |
| 11 | 4966 | 2470 | 2390 | 1303 | 562 | COLD shell + POLLING |
| 00/01/12/22 | 45–49xx | ~3k | 1.4–2.2k | — | — | mixed cold + post-pair browser |

## Early partial

Pair create→persist ≈1–22ms when pair exists. `FIRST_VALID_PAIR_P95≈3320` under absolute FU target; supplier floor alone does **not** explain P95=7136 on this contaminated run.

## Next

1. Re-run Return N≥30 on warmed public process (no fresh PM2).
2. If warm FU P95 still >4500 with JP post-supplier-ready >1000 → fix poll/client path.
3. Traveler warm/cold separate certs.
