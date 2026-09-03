# JP-DEEP-CLOSURE-01 — Return path attribution (pre-change, from REG-05)

## Freeze

```
BRANCH=phase/jp-flight-perf-01
START_LOCAL_HEAD=abbbf69983bc78d278e477402fd73acc501d7293
ACTUAL_REMOTE_HEAD=1f12edef052da278f02b7ffeaf4e7a881c663ef9
AHEAD_BY=115
BEHIND_BY=0
TRACKED_MODIFIED_COUNT=0
INDEX_LOCK_PRESENT=NO
PUSH_PERFORMED=NO
```

## Live Return samples (REG-05 n=6 with provider rows; rest criteria-cache)

| Metric | P50 | P95 |
|---|---|---|
| Sabre network_duration_ms | 2633 | 3286 |
| FIRST_PROVIDER_RESPONSE_MS | ~2709 | 3349 |
| postNet → FIRST_VALID_PAIR_MS | **1597** | **2466** |
| FIRST_VALID_PAIR_PERSISTED_MS | ~4092 | 5659 |
| PAIR_PERSIST_TO_POLL_READABLE_MS | ~593 | 741 |
| TOTAL_PRE_SUPPLIER_MS (true live) | ~45 | ~65 |
| RETURN_CLICK_TO_FIRST_USEFUL (live) | 8910 | 9905 |

## Attribution (live Sabre-only public fan-out)

- **Eligible providers on measured live runs:** Sabre only (Duffel/IATI/PIA not in those samples).
- **Cross-provider parallelization:** technically safe for distinct adapters, but **does not move first-useful** when only Sabre is eligible.
- **Misread REG-05 `LARAVEL_PRE_FIRST_SUPPLIER_P95=3746`:** that P95 was dominated by criteria-cache paths where `TOTAL_PRE_SUPPLIER_MS` fell through to end-of-search (pair ready). True live pre-supplier ≈ **44–65 ms**.
- **Primary JetPakistan-controlled wait:** pricing **entire** Sabre batch (43–81 offers) before first `onProgress` → **~1.5–2.5s** after supplier HTTP/normalize returns.
- **Supplier floor (network):** Sabre shop ≈ **2.1–3.3s** P50/P95 in live samples.
- **DB query counters (716–1214)** include work after network start; metric label `PRE_SUPPLIER_DB_*` is misleading (listener spans whole worker).

## Parallelization verdict

```
RETURN_PARALLEL_DISPATCH_SAFE=YES (bounded, distinct providers)
BOUNDED_PARALLELISM_IMPLEMENTED=NO
REASON=Only Sabre eligible on live path; wait-all would not help progressive first-card; early intra-batch publish addresses the proven JetPakistan gap.
PROVIDER_DISPATCH_MODE_BEFORE=SEQUENTIAL
PROVIDER_DISPATCH_MODE_AFTER=SEQUENTIAL + EARLY_PARTIAL_WITHIN_BATCH
```

## Fix implemented (runtime)

1. Early `onProgress` after first priced offer inside `FlightSearchService::collectOffersFromConnections`.
2. Fare revalidation: `findOfferInPayload` + patch returns offer/payload (avoid duplicate store reads).
3. Perf mark `T_FIRST_EARLY_PARTIAL_PUBLISH` / `FIRST_EARLY_PARTIAL_PUBLISH_MS`.
