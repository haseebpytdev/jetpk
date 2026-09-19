# Soft-nav — 536521f1 / mHk-585jVMAtC6DsijCuS

Captured: see `00-preflight/cert-start-utc.txt`
Harness: `01-soft-nav/run-soft-nav.mjs` (JP_SOFT_N=20, +home_terms)
Rules: no artificial prefetch wait; all soft samples retained; harness retries only for missing link/timeout

## Aggregates

| Route | n_soft | hard | APP_P50 | APP_P95 | Gate ≤1500 |
|-------|--------|------|---------|---------|------------|
| home_support | 20 | 0 | 979 | **4250** | FAIL |
| support_home | 20 | 0 | 856 | **3809** | FAIL |
| home_privacy | 19 | 1 | 953 | **3396** | FAIL |
| privacy_terms | 20 | 0 | 226 | 591 | PASS |
| home_groups | 20 | 0 | 776 | 1460 | PASS |
| home_login | 20 | 0 | 464 | **2881** | FAIL |
| login_register | 20 | 0 | 265 | 520 | PASS |
| home_about | 20 | 0 | 630 | **5244** | FAIL |
| home_faq | 20 | 0 | 717 | 1331 | PASS |
| home_terms | 20 | 0 | 571 | 1299 | PASS |

SOFT_NAV_WORST_ROUTE=home_about
SOFT_NAV_WORST_APP_P95=5244
SOFT_NAV_PASS_COUNT=5
SOFT_NAV_TOTAL_COUNT=10
SOFT_NAV_GATE=FAIL

## Notes

- Priority prefetch on faq/terms appears effective (both ≤1500).
- privacy/login still show multi-second P95 despite priority list — early-click / cold RSC tail retained.
- support/about only deferred-idle prefetched → expected cold-tail exposure.
- Disk on production host was **95%** during cert (possible FS cold-latency contributor; cleanup still gated).

## Soft-nav rediag 911124f7

SOFT_NAV_REDIAG_BUILD=jOxkQ0qppKD8m4VXqOg1V (historical; superseded)
Evidence folder `jp-perf-correction-01-profile-911124f7` was **not** present — treat rediag as incomplete/superseded.

Raw: `raw/soft-nav.json`, `raw/samples-soft-nav.csv`
