# JP-PERF-FINAL-02R — Remediation phase status

## Baseline preserved

FAILED_CERT_EVIDENCE_SHA=9b5539db26823ab2a7757369371e1c17ffc3a9be
NEW_ENGINEERING_SHA=563d6d075258a28c5c00e441dc217b35ec574ad9 (fix commit, not deployed)

## Root causes (verified before edits)

ROOT_CAUSE_RETURN=Pair options persisted before `flow=return_pair`; UI `shownCount` stayed 0 → loading shell until poll delivered readable payload; serial poll+init delayed first fetch.

ROOT_CAUSE_TRAVELER=Measurement misclassification only. TRUE_APP_P95=1162 (passes ≤2000). Supplier wait P95=7378 includes mandatory revalidation + passengers hold_validate (not duplicate POST amplification).

ROOT_CAUSE_SOFT_NAV=Login Suspense/useSearchParams stall on register→login; register page mount contention (security question fetch); groups detail is HARD nav (excluded from soft gates).

## Before-fix corrected metrics

TRAVELER_TRUE_APP_P95_BEFORE=1162
TRAVELER_TRUE_SUPPLIER_P95_BEFORE=7378

## Fixes implemented (563d6d07)

- `use-flight-results.ts`: parallel init `loadPage` + poll; `isReturnPair` when view=pair && paired_options>0
- `LoginPageClient.tsx`: no Suspense stall; sync query read; prefetch register
- `CustomerRegistrationForm.tsx`: Link prefetch login; idle-deferred security question
- `register/page.tsx`: prefetch footer login link
- Extended `jp-next-perf-02-check.cjs` (PASS)

## Verifier gates

| Gate | Status |
|---|---|
| RETURN_ROOT_CAUSE_VERIFIER | PARTIAL_CONFIRM |
| TRAVELER_CLASSIFICATION_VERIFIER | CONFIRM (NO_APP_FIX_REQUIRED) |
| PASSENGERS_ROOT_CAUSE_VERIFIER | CONFIRM (supplier-dominated) |
| NAV_CLASSIFICATION_VERIFIER | CONFIRM |
| PREDEPLOY_VERIFIER (initial) | FAIL (100ms poll throttle — reverted) |
| PREDEPLOY_VERIFIER (after throttle fix) | Pending re-run |

## Deploy / recert

**NOT DEPLOYED** — initial PREDEPLOY_VERIFIER=FAIL; throttle fix + regression extension applied; requires:

1. Re-run Grok PREDEPLOY_VERIFIER
2. Pre-deploy audit gate (`ota:route-page-health-audit --all`)
3. Protected frontend deploy once (DEPLOY-HYGIENE-001 ownership gate)
4. Fresh Return N≥30 + soft-nav affected routes (+ Traveler control if desired)
5. Final Grok PERF_FINAL_VERIFIER

## Current status

STATUS=PARTIAL
BLOCKERS=Deploy not executed; post-fix cohorts not run; Return absolute P95 unproven on new build
