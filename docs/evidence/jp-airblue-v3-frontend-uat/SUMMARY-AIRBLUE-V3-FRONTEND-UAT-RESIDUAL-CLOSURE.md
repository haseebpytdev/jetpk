# SUMMARY — AirBlue v3 Frontend UAT Residual Closure

**Date (UTC):** 2026-10-28  
**Branch:** `fix/airblue-v3-frontend-uat-residual-closure`  
**Base main:** `1450828c03e6179d2264877c4f25980cc4e46102` (PR #134)  
**Mission:** Recover post-PR134 production UAT hotfixes into git + close fare stickiness / total_formatted / PHPUnit residuals. **No deploy. No merge. No provider calls. No AirBook.**

## Absolute safety

| Gate | Result |
| --- | --- |
| AIRLOWFARESEARCH_PROVIDER_CALLS | 0 |
| AIRBOOK_PROVIDER_CALLS | 0 |
| READ / SeatMap / Type5 / AirDemandTicket / Type1 / Cancel | 0 |
| TOTAL_PROVIDER_CALLS | 0 |
| BOOKING_F_CHANGED | NO |
| CONN22_CHANGED | NO |
| PUBLIC_SUPPLIER_ALLOWLIST_CHANGED | NO |
| DEPLOYED | NO |
| MERGED | NO |

Prior certification left intact:

- `AIRBLUE_BACKEND_TEST_CERTIFICATION=PASS`
- `AIRBLUE_FRONTEND_SEARCH_PRESENTATION_CERTIFIED=YES`
- `AIRBLUE_BRANDED_FARES_CERTIFIED=YES`
- `AIRBLUE_FRONTEND_CHECKOUT_PREBOOK_CERTIFIED=YES`

## Phase A — baseline

| Field | Value |
| --- | --- |
| origin/main | `1450828c03e6179d2264877c4f25980cc4e46102` |
| MAIN_PROTECTED | YES |
| Branch | `fix/airblue-v3-frontend-uat-residual-closure` (from main, not PR #134 head) |

## Phase B/C — production hotfix recovery

`PRODUCTION_SOURCE_DRIFT_AFTER_UAT_HOTFIX=YES` (main lacked post-#134 UAT hotfixes).

Recovered from local working tree / Phase-2 evidence (matched production):

| FILE | PURPOSE | UAT_DEFECT_FIXED |
| --- | --- | --- |
| `FlightOfferDisplayPresenter.php` | `selection_key_authoritative` on grouped FareType options; AirBlue skip Sabre `departure_fare_key` in `applySelectedFareFamilyOptionToOffer`; price/family projection | Book Now dropped `fare_option_key`; AirBlue apply failed; EF not projected |
| `FlightController.php` | Consolidate before `FlightSearchResultStore::store()` | Checkout `findOffer` lost `fare_family_options` / `members_by_id` |

| Hash | Value |
| --- | --- |
| PROD_CURRENT presenter (Phase-2) | `e0152c4cbb1877a1b6c7d48f7aec69a990bb83fb8d73f0c7d2d8d65832872511` |
| PROD_CURRENT FlightController | `3c4cd8b5029265f2a85c939ba6a90c24e846bc3ed61776cd31bc5329560347ae` |
| BRANCH FlightController | `3c4cd8b5029265f2a85c939ba6a90c24e846bc3ed61776cd31bc5329560347ae` (exact parity) |
| BRANCH presenter (hotfix + residual fixes) | `35de23a09210c5b767e1acd433b6c5f90ae54169e67939db8b371ac5cc8864f6` |

`SOURCE_REPRESENTS_CURRENT_AIRBLUE_UAT_RUNTIME=YES` (hotfix behavior preserved; branch ahead with residual fixes).  
`RECOVERED_HOTFIX_PARITY=PASS` (behavioral; FlightController byte-identical).

### PROD_HOTFIX_DRIFT_FILES

- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Http/Controllers/Frontend/FlightController.php`

## Phase D/E — selected fare stickiness

Offline PA201 fixture: EV=25000, EF=26000, EX=27100, RBD=U, cabin=economy.

Pre-fix residual: parent `fare_family=EV` while selected option key/name = EF → `STICKINESS_BUG_REPRODUCED=YES`.

Fix path:

1. Recovered AirBlue `applySelectedFareFamilyOptionToOffer` (no Sabre departure key hard-fail).
2. `BookingController` passengers GET applies selection onto `$offer` after draft validation.
3. `StandardBookingJsonPresenter::selectedFareFamilyLabel()` prefers draft intent name over parent default.

Require (fixture test `test_airblue_selected_ef_overrides_parent_ev_on_offer_and_checkout_total`):

| Field | Value |
| --- | --- |
| SELECTED_FARE_OPTION_KEY | EF option_key |
| SELECTED_FARE_FAMILY | EF |
| DETAILS_SELECTED_FARE | EF (`offer.fare_family` after apply) |
| PASSENGER_SELECTED_FARE | EF (`itinerary.fare_family`) |
| SELECTED_FARE_STICKINESS | PASS |

## Phase F — customer total stickiness

`CheckoutFareBreakdownPresenter::result()` now emits `total_formatted` (`PKR 26,000` for EF 26000).

| Field | Value |
| --- | --- |
| SELECTED_EF_CUSTOMER_TOTAL | 26000 |
| SELECTED_EF_TOTAL_FORMATTED | `PKR 26,000` |
| TOTAL_FORMATTED_NULL | NO |
| CUSTOMER_TOTAL_STICKINESS | PASS |

## Phase G — FAILURE #1 (4 fares vs 3)

| Field | Value |
| --- | --- |
| FAILURE_1_TEST | `FlightOfferDisplayPresenterTest::test_branded_fares_display_options_when_display_gate_enabled` |
| FAILURE_1_FILE | `tests/Unit/FlightOfferDisplayPresenterTest.php` |
| FAILURE_1_ROOT_CAUSE | Card path listed all branded options; 4-input fixture expected card strip max 3 |
| OPTION_1..4 (fixture) | Value, Flexi, Premium, Business (4th = real 4th brand, not AirBlue) |
| AirBlue PA201 | EV, EF, EX only — no synthetic fourth (`test_airblue_pa201_…`) |
| BRANDED_FARES_DISPLAY_OPTIONS_COUNT | 3 |
| FARE_OPTION_NAMES (AirBlue) | EV,EF,EX |

Fix: restore card `array_slice(..., 0, 3)` + `branded_fares_more_count`.

## Phase H — FAILURE #2 (pricing_information_index)

| Field | Value |
| --- | --- |
| FAILURE_2_TEST | `FlightOfferDisplayPresenterTest::test_sanitize_selected_fare_family_intent_strips_supplier_refs` |
| FAILURE_2_ROOT_CAUSE | `sanitizeSelectedFareFamilyIntent` echoed `pricing_information_index` into customer intent |
| PUBLIC_PRICING_INFORMATION_INDEX_PRESENT | NO |
| SERVER_SIDE_PRICING_INFORMATION_INDEX | PRESERVED_IF_REQUIRED (raw/option rows / Sabre routing only) |

## Phase I — airlines SQLite harness

| Field | Value |
| --- | --- |
| Symptom | `SQLSTATE[HY000] no such table: airlines` on sqlite `:memory:` via `AirlineCanonicalResolver` / display name path |
| AIRLINES_TABLE_ERRORS_CLASSIFIED | TEST_HARNESS |
| Fix | `RefreshDatabase` on `FlightOfferDisplayPresenterTest` + `AirBlueRefundabilityTruthTest` |
| AIRLINES_TABLE_TEST_ERRORS | 0 |

## Phase J/K/L — regressions

| Gate | Result |
| --- | --- |
| AIRBLUE_UNKNOWN_REFUNDABILITY | PASS |
| AIRBLUE_BAGGAGE_TRUTH | PASS |
| AIRBLUE_POLICY_TRUTH | PASS |
| PUBLIC_FLIGHT_SECURITY | PASS |
| SERVER_SIDE_ROUTING_IDENTITY | PASS |

## Phase M/N — tests (post revenue-integrity)

Targeted (includes AirBlueBookingPaymentContextTest):

`_raw-residual-closure-targeted-phpunit.txt` → **107 passed / 568 assertions / fail=0 / errors=0**

Broad historical filter + revenue/storage tests:

`_raw-residual-closure-broad-phpunit.txt` → **54 passed / 317 assertions / fail=0 / errors=0**

| Metric | Value |
| --- | --- |
| TARGETED_TESTS_PASSED | 107 |
| TARGETED_TESTS_FAILED | 0 |
| TARGETED_TESTS_ERRORS | 0 |
| BROAD_TEST_PASSED | 54 |
| BROAD_TEST_FAILURES | 0 |
| BROAD_TEST_ERRORS | 0 |
| BROAD_TEST_SKIPPED | 0 |
| BROAD_TEST_ASSERTIONS | 317 |

## Phase O — frontend source contract

No TS/TSX edits in this loop. Stickiness + total contract proven offline via PHP presenters/controllers used by Next passengers/details JSON.  
`SELECTED_FARE_STICKINESS=PASS`  
`CUSTOMER_TOTAL_STICKINESS=PASS`

## Phase P — quality

| Gate | Result |
| --- | --- |
| PHP_SYNTAX | PASS (all touched PHP) |
| FRONTEND_TYPECHECK | N/A (no TS/TSX changes) |

## Phase Q — drift accounting

| Field | Value |
| --- | --- |
| RECOVERED_HOTFIX_PARITY | PASS |
| ADDITIONAL_FIXES_PENDING_DEPLOY | card slice max-3; strip public `pricing_information_index`; `total_formatted` on checkout breakdown; passengers apply-selected-fare; `selectedFareFamilyLabel`; RefreshDatabase harness; stickiness regression tests; **customer-payable preserve on grouped apply**; **AirBlue-only pre-store consolidation** |

## Post-review — revenue integrity + storage scope (PR #135)

| Gate | Result |
| --- | --- |
| POST_REVIEW_REVENUE_INTEGRITY_BLOCKER_FOUND | YES |
| POST_REVIEW_REVENUE_INTEGRITY_BLOCKER_CLOSED | YES |
| CUSTOMER_PAYABLE_COLLAPSE_REPRODUCED | YES (at `46b907a1`: apply set `final_customer_price`/`displayed_price` = supplier total) |
| SUPPLIER_CUSTOMER_PRICE_SEPARATION | PASS |
| AIRBLUE_GROUPED_SELECTION_MARKUP_PRESERVED | PASS |
| CHECKOUT_CUSTOMER_PAYABLE | PASS (`PKR 26,500`) |
| TICKETING_SUPPLIER_PAYABLE | PASS (`Amount="26000.00"`; customer 26500 never ticketed) |
| PRESTORE_CONSOLIDATION_SCOPE | AIRBLUE_ONLY (`ItineraryFareConsolidator::consolidateAirblueForStorage`) |
| NON_AIRBLUE_OFFER_IDS_UNCHANGED | PASS |
| NON_AIRBLUE_STORED_STRUCTURE_UNCHANGED | PASS |
| SELECTED_FARE_STICKINESS | PASS (EF) |

Permanent gate test: `test_airblue_grouped_selected_fare_preserves_customer_markup_separate_from_supplier_payable`.

## Stop

Do **not** merge. Do **not** deploy. Do **not** start final AirBook UAT.  
Next: `PR135_FINAL_REVIEW_BEFORE_MERGE`

## FINAL_STATUS

`AIRBLUE_V3_FRONTEND_UAT_REVENUE_INTEGRITY_CLOSED`
