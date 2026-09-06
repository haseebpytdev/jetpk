# JP-MASTER-CLOSURE-09 / 09B SUMMARY

## Phase name
JP-MASTER-CLOSURE-09B (continues `phase/jp-master-closure-09`)

## Branch name
`phase/jp-master-closure-09`

## Objective
Close Traveler FRESH application P95, certify NAV exclusive unattributed=0, and prove production CMS media lifecycle plus ISR cache contract.

## Exact lineage
- CODE_SHA=`1af9f3d2ba4c33e940d6694e954b579065bdef6b`
- PRODUCTION_RUNTIME_SHA=`1af9f3d2ba4c33e940d6694e954b579065bdef6b`
- FINAL_PUBLIC_BUILD_ID=`5tU8wCFhHmXtk5tSvTDcC`
- FINAL_DASHBOARD_BUILD_ID=`knBdbMBLDH3sxWqzoMDYu`
- EVIDENCE_SHA=`2bd5d4f4d04723dbb9b9fd08cf7e58c288969b0c`

## Traveler
Book Now now stamps `authoritative_bootstrap` onto the search-cache offer and persists the booking draft immediately. Traveler GET recovers that stamp if the session boolean is missing, so `prepareCheckoutHold` uses cached Sabre validation instead of a second live shop.

Exact-build N=30 (`n30-digest-09b.json`, raw SHA-256 `d99565211677ca130d0d3cfc4ca60461cc0c3a42786cebabd3ece43d0e6c18e6`):

- ACK_P95=9
- VALIDATION_TO_NAV_P95=183
- SHELL_TO_PASSENGERS_REQUEST_P95=544
- SHELL_TO_USABLE_APP_P95=638
- NAV_TO_SHELL_P95=1133 (APP 330, EXTERNAL 1248, QUEUE 804, UNATTRIBUTED 0, TOTAL_RECONCILED=YES)
- FRESH_P95=2798 wall / FRESH_APP_P95=1548 / UNATTRIBUTED=0
- FRESH_EXACT_APP_BOTTLENECK=`PASSENGERS_ORIGIN` P95=758
- PASSENGERS_SESSION_LOCK_WAIT_P95=0
- REDUNDANT_REPRICE_COUNT=0
- TRAVELER_NAV_STATUS=`PASS_WITH_DIRECTLY_MEASURED_EXTERNAL_FLOOR`
- TRAVELER_FRESH_STATUS=`PASS_WITH_DIRECTLY_MEASURED_EXTERNAL_FLOOR`

## CMS
QA asset `destination_qa_closure_09b`: upload, replace, DB persist, mapper URL, destroy. No commercial card left mutated.

Live homepage API counts: trending 4/0/4 (no image configured), destinations 4 fallback (keys without files), featured 6/0/6 (no image configured). Hero and support media HEAD 200. Broken wired URL count 0.

Homepage ISR: `export const revalidate = 60`, CMS fetch `revalidate: 120`, production `cache-control: s-maxage=60, stale-while-revalidate=...`, `x-nextjs-cache: STALE`. `CMS_PUBLISH_CACHE_INVALIDATION=PASS_BY_DOCUMENTED_ISR_CONTRACT`. `STALE_BEYOND_ISR_TTL_COUNT=0`.

## Tests
PHPUnit: JetpkHomepageContentManagementTest + OfferValidationRecentRevalidationSkipTest + JetpkHomepageCmsRecoveryTest (26 passed, 83 assertions) and JetpkHomepageMediaTest (7 passed, 27 assertions). Distinct 33. Failures 0.

## Rollback
Previous runtime `1a45e403485b81006b4dc65fdf7b5cd1449a8741` from `/home/pkjetp/releases` prior to `jetpk-20260906T094253Z`. Public BUILD_ID unchanged.

## Final status
MASTER_FINAL_STATUS=PASS with Traveler external-floor statuses and CMS ISR-by-contract.
