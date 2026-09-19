# JetPakistan PERF-CORRECTION-01 Recert — SHA `36221ac0` / Build `H9TYWa1Q2VFnwaR4TlPZy`

**PERFORMANCE_CERTIFICATION=FAIL**

Do **not** enter Phase B+ cleanup/retirement.

## Release provenance

| Field | Value |
|---|---|
| FINAL_RELEASE_SHA / RUNTIME / PUBLIC_SRC / AUTH | `36221ac0859578dd82d9b3094433b50875dc5b06` |
| PUBLIC_BUILD_ID | `H9TYWa1Q2VFnwaR4TlPZy` |
| DASHBOARD_BUILD_ID | `dOefZBIOnEl7EbTETViNa` |
| DASHBOARD_BUILD_SOURCE_SHA | `675c7e5e…` (PUBLIC_ONLY rebuild) |

Baseline FAIL pack preserved: `docs/evidence/jp-final-perf-cert-675c7e5e/` (`8c2795ff`, 280 rows).

## Retry disclosure

Soft-nav attempt 1 aborted mid-suite (`net::ERR_CONNECTION_CLOSED`). Disclosed retry in `01-soft-nav/RETRY-DISCLOSURE.txt`. Full N=20 suite completed on retry. No slow-sample deletion.

## Gates

| Gate | Threshold | Measured | Status |
|---|---:|---:|---|
| RETURN POST_SUPPLIER P95 | ≤1000 | **1836** (P50=817, MAX=2069, N=30) | **FAIL** |
| TRAVELER APP P95 | ≤2000 | **2817** (P50≈1804, N=30) | **FAIL** |
| SOFT_NAV worst APP P95 | ≤1500 all routes | **8183** (`home_privacy`) | **FAIL** (7/9 routes) |
| DUPLICATE_SUPPLIER_SEARCHES | 0 | 0 | PASS |
| VIEW_SWITCH_SUPPLIER_CALLS | 0 | 0 | PASS |
| Commercial mutations | 0 | 0 | PASS |

Soft-nav failing routes: home_support 4715, support_home 2228, home_privacy 8183, privacy_terms 1983, home_login 3095, home_about 2601, home_faq 7873. Passing: home_groups 1373, login_register 1358.

PAIR→SEGMENTED P95=1057, SEGMENTED→PAIR P95=487 (informational; supplier calls 0).

## FAILED_GATE causes (for Round 2)

1. **SOFT_NAV:** Server CMS `fetch` lost AbortSignal timeout when `next.revalidate` present → unbounded Laravel wait on soft RSC → P95 regression vs baseline.
2. **TRAVELER:** Still `await primePromise` before hard assign → serializes passengers GET into APP; hard-nav drops in-memory prime → cold GET (`passengersMs` often ~1s).
3. **RETURN:** Post-supplier client paint still above 1000; P95 moved 1509→1836 (keep samples; Round 2 continue first-card critical path).

## Commercial

PAYMENT/PNR/ORDER/TICKET/VOID/REFUND = NO

## Next

PERF Round 2 (traveler soft-nav overlap + CMS timeout-inside-`cache()` + ISR align). Re-cert only after deploy. Cleanup remains **blocked**.
