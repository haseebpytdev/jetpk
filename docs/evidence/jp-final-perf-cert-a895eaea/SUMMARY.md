# JP Final Perf Cert — a895eaea / tEgZGLmLHLmRO2eqJs__z

**PERFORMANCE_CERTIFICATION=FAIL**

| Field | Value |
|-------|-------|
| RELEASE_SHA | `a895eaea6bdaff310617801ee7e8ebf734258186` |
| PUBLIC_BUILD_ID | `tEgZGLmLHLmRO2eqJs__z` |
| ROLLBACK_SHA | `bd12ac766621912c5bac045cffc6d0bcbefe3394` |
| Disk | 64% (stale Sep5–16 tarballs removed; inventory in 536521f1 preflight) |
| Unbiased | YES |
| Concurrent harness | NO (sequential soft → traveler → return) |
| Commercial mutations | 0 |

## Soft-nav (N=20, 10 routes)

PASS 6/10. FAIL: home_support 3192, support_home 1752, home_login 2273, home_about 1856.  
Privacy/faq/terms/groups/login_register PASS after auth force-dynamic removal + loading boundaries.

SOFT_NAV_GATE=**FAIL**

## Traveler (N=30, alone)

| Metric | Value |
|--------|-------|
| APP_P50 | 1735 |
| APP_P95 | **2334** |
| RAW_P95 | 4546 |
| redundant revalidate | 0 |
| mutations | 0 |

TRAVELER_GATE=**FAIL** (≤2000) — improved vs 4536 on contended 536521f1 run.

## Return + switch (N=30 / 20, alone)

| Metric | Value |
|--------|-------|
| POST_P50 | 687 |
| POST_P95 | **1299** |
| dup / wrong | 0 / 0 |
| pair↔seg P95 | 220 / 166 |
| switch supplier | 0 |

RETURN_PAIR_GATE=**FAIL** (≤1000) — improved vs 2649 contended.

## Historical packs preserved

- `jp-final-perf-cert-675c7e5e/`
- `jp-final-perf-cert-36221ac0/`
- `jp-final-perf-cert-536521f1/` (FAIL baseline)
- `jp-final-perf-cert-ab667f78/`, `jp-final-perf-cert-bd12ac76/` (remediation trail)

## Blockers before retirement/cleanup/tag

1. Soft-nav APP_P95 ≤1500 on **all** routes (support/login/about tails)
2. Traveler APP_P95 ≤2000 (currently 2334)
3. Return POST_SUPPLIER_P95 ≤1000 (currently 1299)
