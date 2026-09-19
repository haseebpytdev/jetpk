# JetPakistan Final Performance Certification — 675c7e5e

**PERFORMANCE_CERTIFICATION=FAIL**  
**Release pin:** `675c7e5efaa2656b2435c186cfe4665e086676bb`  
**PUBLIC_BUILD_ID:** `FfNP1fgjiB4_gK6lNt4FI`  
**DASHBOARD_BUILD_ID:** `dOefZBIOnEl7EbTETViNa`  
**Method:** unbiased — no outlier deletion, no best-of-N, no threshold changes after results.

## Release consistency

PASS — see `00-preflight/release-consistency.json` (local HEAD, `jetpk/main`, `.jetpk-runtime-sha`, build source SHAs, BUILD_IDs).

## Harness notes / retries

- `run-live-perf-cert.mjs` return-pair path produced invalid samples (`pairs=0`, `post=null`) for all observed iterations — classified as **harness/bootstrap failure**, not used in percentiles. Disclosed; not retried after interrupt.
- Soft-nav samples retain harness internal attempt counters where present; no samples discarded for slowness.
- Warm pair↔segmented separate N≥20 pass: **not completed** before FAIL stop (immediate switch N=20 present). Marked PARTIAL for warm-only evidence.

## FAILED GATES

### 1) RETURN_PAIR POST_SUPPLIER

| Field | Value |
|---|---|
| FAILED_GATE | `POST_SUPPLIER_P95` |
| measured P95 | **1509 ms** |
| threshold | ≤ 1000 ms |
| P50 / MAX / N | 766 / 1525 / 30 |
| dominant latency | app render after `/results/data` ready → first `pair-return-card` (browserRender P95 also high ~6s wall path) |
| likely cause | client hydration/list paint / pair card commit path after authoritative pair payload is available |
| recommended fix | profile `use-flight-results` pair paint path; reduce main-thread work between data-ready and first card; avoid blocking layout on full list |

Integrity: `DUPLICATE_SUPPLIER_SEARCHES=0`, `WRONG_ITINERARY=0` (from seeded set). Live invalid samples excluded.

### 2) TRAVELER_APP

| Field | Value |
|---|---|
| FAILED_GATE | `TRAVELER_APP_P95` |
| measured P95 | **2967 ms** |
| threshold | ≤ 2000 ms |
| P50 / MAX / N | 1595 / 3204 / 30 |
| dominant latency | application time after revalidate-offer completes → passengers form usable (`app` metric) |
| likely cause | post-revalidate navigation / passengers route render or sequential client work |
| recommended fix | isolate book-now timing marks; cut post-revalidate client waterfall; ensure passengers shell paints before non-critical fetches |

`SUPPLIER_MUTATION_CALLS=0`, `TRAVELER_REDUNDANT_REVALIDATION=0`, search_id preserved 30/30.

### 3) SOFT_NAV APP_P95

Threshold ≤ 1500 ms per route. Failures (kept in dataset):

| Route | N | P50 | P95 | GATE |
|---|---:|---:|---:|---|
| home_support | 20 | 1322 | 1840 | FAIL |
| home_privacy | 20 | 1596 | 4435 | FAIL |
| home_groups | 20 | 902 | 1854 | FAIL |
| login_register | 20 | 328 | 1763 | FAIL |
| home_about | 20 | 1091 | 1987 | FAIL |
| home_faq | 20 | 1153 | 3813 | FAIL |
| support_home | 20 | 671 | 1219 | PASS |
| privacy_terms | 20 | 561 | 1320 | PASS |
| home_login | 20 | 637 | 1150 | PASS |

Worst: `home_privacy` APP_P95=4435. Dominant: Next soft-nav RSC/client transition to CMS pages. Likely cause: heavy page payloads / sequential data on soft navigation. Recommended: route-level code-split + reduce blocking CMS fetches on soft nav.

Supplemental routes (Homepage→Flights, authenticated customer/agent dashboards): not fully executed before FAIL stop — do not treat as PASS.

## Passed / clean counters (within measured scopes)

| Check | Result |
|---|---|
| PAIR→SEGMENTED P95 (immediate N=20) | 263 ms |
| SEGMENTED→PAIR P95 (immediate N=20) | 186 ms |
| UNNECESSARY_SUPPLIER_CALLS (view switch) | 0 |
| DUPLICATE_SUPPLIER_SEARCHES (seeded return) | 0 |
| SUPPLIER_MUTATION_CALLS | 0 |
| PAYMENT / PNR / ORDER / TICKET / VOID / REFUND | NO |

## Compact certification table

See parent response / `05-network/aggregates.json`. Raw rows: `raw/samples.csv`.

## Stop rule

Cleanup / retirement **blocked** until performance gates pass on a future cert.
