# Same-SHA perf cert — `5f78a5e1` / BUILD `GpIljRfA8zEVcmPGCGRQS`

Production tip after SEO + short-URL foundation deploy. Rollback `b45975e3`.

## Soft-nav

| N | Pass | Worst APP P95 |
|---|---|---|
| 10 | 10/10 | 561ms |
| 20 | 10/10 | **333ms** |

## Traveler

| Metric | Value |
|---|---|
| valid | 30 |
| total_p95 | **1548ms** (≤2000) |
| dup_reval | 0 |
| mutations | 0 |

## Return Pair

| Metric | Value |
|---|---|
| valid | 30 |
| poll_total_p95 | **0.581ms** (≤1000) |
| duplicate_fetch_sum | 0 |
| contention | NO |

## Pair ↔ Segmented

| View | N | poll_total_p95 | dup |
|---|---|---|---|
| Pair (via return) | 30 | 0.581 | 0 |
| Segmented | 20 | **0.773** | 0 |

## Verdict

**SAME_SHA_PERF_CERT=PASS** on `5f78a5e1`.

SEO package did not regress soft-nav or booking-path performance gates.

Next: finish AEO/GEO §49 evidence → search short-ref mint cutover (soft-nav sensitive) → parity / retirement / release-lock.
