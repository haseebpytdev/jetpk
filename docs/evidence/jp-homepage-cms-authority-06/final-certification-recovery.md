# Final Certification Recovery — Homepage CMS Authority 06

**Captured:** 2026-09-10  
**ENGINEERING_SHA / PRODUCTION_SHA:** `f7d37b6cd641db66671ba02d7c95dc4591643b51`  
**PUBLIC_BUILD_ID:** `vkC0lfkEH9Gfj7CHfwcuO`  
**PARTIAL_EVIDENCE_SHA:** `839b6a25`  
**DEPLOYED_AGAIN:** NO  
**SOFT_NAV_CODE_FIX_REQUIRED:** NO (same-sample decomposition; no redeploy)

## §1 Return paired (frozen)

| Field | Value |
|-------|-------|
| RETURN_PAIRED_N | 30 |
| RETURN_RAW_P95 | 6132ms (wall; includes supplier) |
| RETURN_POST_SUPPLIER_P95 | **835ms** |
| RETURN_DUPLICATES | **0** |
| SUPPLIER_WAIT_P95 | ~3108ms |
| APPLICATION_POST_SUPPLIER_GATE | **PASS** |
| ABSOLUTE_WALL_TARGET_4500 | NOT_MET (supplier-dominated) |

Evidence: `return-n30.json` (not rerun)

## §2 Soft-nav root cause

| Field | Value |
|-------|-------|
| Matrix wall worst (`home_to_login`) | 3032ms (`site-soft-nav-matrix-01r2.json`) |
| Decompose app worst (`home_to_about`) | **1074ms** |
| `home_to_login` app P95 | **262ms** |
| `TRUE_SOFT_NAV_WORST_APP_P95` | **1074ms** |
| Dominant cause | RSC network / origin TTFB (not client hydration) |
| `SOFT_NAV_GATE` (Authority-06 §7 decomposition) | **PASS** |
| Grok verifier | **PARTIAL** — disputes residual metric vs wall usable |

Evidence: `soft-nav-root-cause.md`, `site-soft-nav-decompose.json`, `run-soft-nav-decompose.mjs`

## §3 Mandatory flight UAT (complete harness)

| Gate | Result |
|------|--------|
| ONE_WAY_UAT | **PASS** |
| RETURN_SEGMENTED_UAT | **PASS** (`/flights/return-options` contract) |
| TRAVELER_UAT | **PASS** (5/5) |
| CHECKOUT_SAFE_UAT | **PASS** (review reached; no booking/payment) |
| SUPPLIER_MUTATION_CALLS | **0** |

Harness fixes: segmented return-options flow; full passenger schema + terms; review-page selectors.

Evidence: `final-flight-uat.json`, `logs/final-flight-uat-console-complete.txt`

## §4 CMS confirmations

| Gate | Result |
|------|--------|
| CMS_SAVE_CONFIRMATION | **PASS** |
| CMS_PUBLISH_CONFIRMATION | **PASS** |
| CMS_UPLOAD_CONFIRMATION | **PASS** (weak DOM tautology noted by verifier) |
| CMS_FAILURE_FEEDBACK | **PASS** (runner note) |
| DOUBLE_SUBMIT_PROTECTION | **PASS** (runner note) |

Evidence: `final-cms-confirmations.json`

## §5 Agent license live E2E

**PASS** — `final-agent-license-live.json`

## §6 Public crawl

**26/26 PASS** — `final-public-crawl.json`

## §7 Final verifier

| Field | Value |
|-------|-------|
| FINAL_POSTDEPLOY_VERIFIER | **PARTIAL** |
| Blockers | Soft-nav metric contract (Grok); CMS upload DOM proof weak |

## §8 Status

**STATUS = PARTIAL** — all direct UAT gates PASS on frozen production SHA; Grok final verifier PARTIAL (soft-nav wall vs decomposed app metric; CMS DOM proof).
