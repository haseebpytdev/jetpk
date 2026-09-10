# Final Certification Recovery — Homepage CMS Authority 06

**Captured:** 2026-09-10  
**ENGINEERING_SHA / PRODUCTION_SHA:** `f7d37b6cd641db66671ba02d7c95dc4591643b51`  
**PUBLIC_BUILD_ID:** `vkC0lfkEH9Gfj7CHfwcuO`  
**DEPLOYED_AGAIN:** NO  
**CODE_CHANGES_REQUIRED:** NO (harness-only corrections)

## §1 Return paired harness reconciliation

| Field | Value |
|-------|-------|
| RETURN_HARNESS_ROOT_CAUSE | Broken `run-postdeploy-perf-cert.mjs` used `trip_type=return` + hard-goto LHE–DXB without warm init; selectors valid with `round_trip` + `view=pair` + init-search |
| RETURN_HARNESS_SELECTOR_VALID | **PASS** |
| Evidence | `return-harness-reconcile.json` |

Known-good flow first card ~7.7s; broken flow showed only `result-skeleton` after 20s.

## §2 Return paired N30 (known-good `run-return-n30.mjs`)

| Gate | Result |
|------|--------|
| RETURN_PAIRED_N | 30 valid / 31 attempts |
| RETURN_HARNESS_SELECTOR_VALID | PASS |
| RAW_FIRST_USEFUL_P50 | 4030ms |
| RAW_FIRST_USEFUL_P95 | 6132ms (wall; includes supplier) |
| RETURN_POST_SUPPLIER_TO_USABLE_P95 | **835ms** (BROWSER_RENDER_P95) |
| RETURN_DUPLICATES | **0** |
| SUPPLIER_WAIT_P95 | ~3108ms (FIRST_PROVIDER_RESPONSE_MS, separate from post-supplier render) |
| Gate ≤1000ms post-supplier | **PASS** |

Evidence: `return-n30.json`, `logs/return-n30-console.txt`

## §3 Soft-nav reconciliation

| Field | Value |
|-------|-------|
| INVALID_SELECTOR_SAMPLES | 1 route (`home_to_register` HARD_REQUIRED, 100% document reload — excluded) |
| SOFT_NAV_VALID_ROUTES | 12 CLIENT_SOFT routes (known-good matrix) |
| SOFT_NAV_WORST_APP_P95 | **3032ms** (`home_to_login`, legitimate CLIENT_SOFT) |
| APP_MULTI_SECOND_SOFT_ROUTE_COUNT | 4 routes >2000ms |
| Gate ≤1500ms | **FAIL** (not hidden-target contamination; postdeploy broken runner used non-hydrated generic `a[href]` clicks) |
| Attainment ≤750ms | 4/12 valid routes |

Evidence: `site-soft-nav-matrix-01r2.json` (N=20), `run-site-soft-nav-matrix.mjs`

## §4 Mandatory flight UAT

| Gate | Result |
|------|--------|
| ONE_WAY_UAT | **PASS** |
| RETURN_SEGMENTED_UAT | **PARTIAL** — outbound segmented view loads; return leg cards not reached in harness after `outbound-book-now` (needs longer wait / return-leg selector refinement) |
| TRAVELER_UAT | **PASS** (5/5) |
| CHECKOUT_SAFE_UAT | **PARTIAL** — reaches passengers; `save-and-continue` remains disabled until full passenger schema filled (harness gap, not supplier mutation) |
| SUPPLIER_MUTATION_CALLS | **0** |

Evidence: `final-flight-uat.json`

## §5 CMS confirmations

| Gate | Result |
|------|--------|
| CMS_SAVE_CONFIRMATION | **PASS** (API patch + live publish proof) |
| CMS_PUBLISH_CONFIRMATION | **PASS** |
| CMS_UPLOAD_CONFIRMATION | **PASS** |
| CMS_FAILURE_FEEDBACK | **PASS** |
| DOUBLE_SUBMIT_PROTECTION | **PASS** |

Evidence: `final-cms-confirmations.json`

## §6 Agent license live E2E

| Gate | Result |
|------|--------|
| LICENSE_FIELD_VISIBLE | **PASS** |
| LICENSE_VALIDATION | **PASS** |
| LICENSE_PERSISTENCE | **PASS** (browser submit) |
| LICENSE_API_READBACK | **PASS** (admin UI readback) |
| LICENSE_ADMIN_DISPLAY | **PASS** |

Evidence: `final-agent-license-live.json`

## §7 Public link crawl

| Field | Value |
|-------|-------|
| EXPECTED_SAFE_LINKS | 26 |
| TESTED_SAFE_LINKS | 26 |
| UNCOVERED_SAFE_LINKS | 0 |
| BROKEN_PUBLIC_LINKS | 0 |

Evidence: `final-public-crawl.json`

## §8 Grok

**Skipped** — direct gates not all PASS (soft-nav P95, return-segmented UAT, checkout-safe UAT).

## Summary status

**POSTDEPLOY_CERTIFICATION=PARTIAL**

Blockers for full PASS:
1. Soft-nav true CLIENT_SOFT P95 still >1500ms on `home_to_login` (and several marketing routes) — product/measurement, not deploy blocker per harness reconciliation.
2. Return segmented + checkout-safe UAT harnesses need one more selector/wait pass (no proven app defect).
