# JP Final Perf Cert — 536521f1 / mHk-585jVMAtC6DsijCuS

**PERFORMANCE_CERTIFICATION=FAIL**

| Field | Value |
|-------|-------|
| RELEASE_SHA | `536521f1752b420d4221970c4f8a2677762c753b` |
| PUBLIC_BUILD_ID | `mHk-585jVMAtC6DsijCuS` |
| Captured | 2026-09-19 (see `00-preflight/cert-start-utc.txt`) |
| Unbiased | YES — all soft samples retained; no artificial prefetch wait; harness retries only for missing link/timeout |
| Retries disclosed | home_privacy: 1 hard_fallback (link/timeout path); no slow-sample retries |
| Commercial | PAYMENT/PNR/ORDER/TICKET/VOID/REFUND = 0 |

Historical packs **not** overwritten:
- `docs/evidence/jp-final-perf-cert-675c7e5e/`
- `docs/evidence/jp-final-perf-cert-36221ac0/`

## Soft-nav (N=20 / route, 10 routes)

| Metric | Value |
|--------|-------|
| SOFT_NAV_PASS_COUNT | 5 |
| SOFT_NAV_TOTAL_COUNT | 10 |
| WORST_SOFT_NAV_ROUTE | home_about |
| WORST_SOFT_NAV_P95 | **5244 ms** |
| SOFT_NAV_GATE | **FAIL** (≤1500) |

PASS: privacy_terms 591, login_register 520, home_faq 1331, home_terms 1299, home_groups 1460  
FAIL: home_about 5244, home_support 4250, support_home 3809, home_privacy 3396, home_login 2881  

Pattern: within-route sample series often **fast early / slow late** (SPA/RSC degradation under repeated soft-nav), not classic cold-first only.

## Traveler (N=30)

| Metric | Value |
|--------|-------|
| TRAVELER_N | 30 |
| TRAVELER_OK | 30 |
| TRAVELER_RAW_P50 | 4215 |
| TRAVELER_RAW_P95 | 7929 |
| TRAVELER_APP_P95 | **4536** |
| TRAVELER_SUPPLIER_P95 | 3074 |
| TRAVELER_REDUNDANT_REVALIDATION | 0 |
| SEARCH_ID_PRESERVED | 30/30 |
| TRAVELER_GATE | **FAIL** (≤2000) |

Note: traveler ran **concurrently** with return/switch harness (possible contention). Recert must be sequential.

## Return pair + switch

| Metric | Value |
|--------|-------|
| RETURN_N | 30 |
| RETURN_POST_SUPPLIER_P50 | 861 |
| RETURN_POST_SUPPLIER_P95 | **2649** |
| RETURN_DUPLICATE_SEARCHES | 0 |
| RETURN_WRONG_ITINERARY | 0 |
| PAIR_TO_SEGMENTED_P95 | 270 (N=20) |
| SEGMENTED_TO_PAIR_P95 | 274 (N=20) |
| RETURN_VIEW_SWITCH_SUPPLIER_CALLS | 0 |
| RETURN_PAIR_GATE | **FAIL** (≤1000) |

## Infrastructure observation (not cleaned — gated)

Production disk **95%** full; ` /home/pkjetp/backups` ≈ **39G**. May contribute to cold FS/RSC tails. Cleanup remains blocked until a later PASS candidate + inventory.

## Soft-nav rediag 911124f7

`SOFT_NAV_REDIAG` evidence folder for `jOxkQ0qppKD8m4VXqOg1V` was **missing**; treat as incomplete/superseded. faq/terms priority prefetch on this build shows benefit (both PASS).

## Next

Phase 5 remediation required before any retirement/cleanup/tag. Do not declare closure PASS.
