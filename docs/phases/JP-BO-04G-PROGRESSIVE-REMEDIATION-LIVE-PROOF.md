# JP-BO-04G Progressive Remediation — Final Live Commerce Proof

**Status:** ENGINEERING DEPLOYED — LIVE CERTIFICATION PARTIAL / BLOCKED  
**Branch:** `phase/jp-bo-04g-progressive`  
**UTC:** 2026-08-25

---

## Pins

| Pin | Value |
| --- | --- |
| CHECKPOINT_SHA | `44f960aff0ae6a7c4cbf6d93e67784781b46086a` |
| PREVIOUS_LIVE_SHA (pre final commerce) | `b08f4ba088ee1483bf76e6a61277f4946c25c478` |
| FINAL_ENGINEERING_SHA | `e93f90f5b57b44daa3486edf728812e44b60b030` |
| FINAL_DOCS_SHA | _(this commit)_ |
| OLD_PUBLIC_BUILD (pre final) | `VEDrm82AVe7W8h1ND_2OR` |
| INTERMEDIATE_PUBLIC_BUILD | `0Fcej-Ky0t1HIMDGGSyzk` |
| NEW_PUBLIC_BUILD | `F1hRrv3NulCyaAh_s0nmw` |
| BACKUP_ID (pre first final deploy) | `jp-bo-04g-final-commerce-20260825T164743Z` |
| BACKUP_ID (pre brand-hide hotfix) | `jp-bo-04g-final-commerce-20260825T180029Z` |
| ROLLBACK_RUNTIME_SHA | `b08f4ba088ee1483bf76e6a61277f4946c25c478` |
| ROLLBACK_PUBLIC_BUILD | `VEDrm82AVe7W8h1ND_2OR` |

Runtime pin is **FINAL_ENGINEERING_SHA** only. Docs/checkpoint SHAs are not deploy pins.

---

## Deployable manifest (exact)

Base live → target: `b08f4ba0` → `e93f90f5`

| Area | Count |
| --- | --- |
| Laravel | 2 |
| Config | 0 |
| Frontend | 12 |
| Dashboard | 0 |
| Migrations | 0 |
| **EXACT_DEPLOYABLE_FILE_COUNT** | **14** |

Paths:

1. `app/Http/Controllers/Frontend/FlightController.php`
2. `app/Support/FlightSearch/PublicFlightSearchSecurity.php`
3. `frontend/features/flight-details/components/FareFamilyDetails.tsx`
4. `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
5. `frontend/features/flight-details/hooks/use-revalidation.ts`
6. `frontend/features/flight-details/types/index.ts`
7. `frontend/features/flight-results/components/FlightResultCard.tsx`
8. `frontend/features/flight-results/components/FlightResultsPage.tsx`
9. `frontend/features/flight-results/components/OutboundOptionCard.tsx`
10. `frontend/features/flight-results/components/PairReturnCard.tsx`
11. `frontend/features/flight-results/components/ReturnOptionsPage.tsx`
12. `frontend/features/flight-results/components/SupplierSourceBadge.tsx`
13. `frontend/features/flight-results/types/index.ts`
14. `frontend/features/flight-results/utils/checkout-nav.ts`

Local immutable TSV: `tmp/jp-bo-04g-final-commerce/immutable-manifest.tsv`

---

## Owner requirements implemented

### A — Book Now confirms fare

- Book Now opens Flight Details drawer (does not jump to passengers).
- Single and multi branded fare cards render inside Details.
- Explicit Continue required.
- Pair Book Now / Return price select also open Details confirmation.

### B — Hide brand on main result card

- Refundable / Non-refundable preserved.
- Fare-family labels removed from main card summary.
- Inline branded carousel removed from result cards (brands live in Details).

### C — Fresh search on checkout return

- `pageshow` / BFCache / back-from-checkout starts fresh search (criteria preserved, selection keys stripped).
- Change Flight already allocated new `search_id` on live.

### D — Privileged supplier badges

- Server-side `SupplierSourceVisibility` + `PublicFlightSearchSecurity::applySupplierSourceVisibility`.
- Guest/customer payloads omit `supplier_source_label`.
- Agent/admin receive safe display label.
- Chip renders only when API sends label.

---

## Local verification

| Check | Result |
| --- | --- |
| Frontend typecheck | PASS |
| Node unit (checkout-nav + cabin filter) | PASS (5) |
| Laravel `SupplierSourceVisibilityResultsApiTest` | PASS (5 tests / 15 assertions) |
| Playwright commerce + progressive | PASS (9) after rebuild |
| Frontend production build | PASS |
| Protected deploy | PASS (`LIVE_SOURCE_DRIFT=0`) |

---

## Live production evidence (`https://jetpakistan.pk`)

### Observed PASS

| Field | Evidence |
| --- | --- |
| LIVE_ONE_WAY_RESULTS_COUNT | 12 (ISB→DXB 2026-09-29) |
| BOOK_NOW_OPENS_DETAILS_DRAWER | PASS — drawer + fare cards |
| BOOK_NOW_DIRECT_CHECKOUT | NO |
| RESULT_CARD_BRAND_LABEL_HIDDEN | PASS — `selected-fare-brand=0`, carousel=0 |
| REFUNDABILITY_TAG_PRESERVED | PASS — Non-refundable on cards |
| DETAILS / BRANDED_FARE_CARD_BRAND_VISIBLE | PASS — ECONOMY VALUE etc. in drawer |
| MULTI_BRAND_SELECTION_REQUIRED | PASS — 4 fare cards; Continue after select |
| GUEST_SUPPLIER_BADGE_VISIBLE | NO |
| CHANGE_FLIGHT_TRIGGERS_FRESH_SEARCH | PASS — `448167a0…` → `cb585c1c…` |
| SEARCH_CRITERIA_PRESERVED | PASS — ISB/DXB/date/cabin retained |
| OLD_SEARCH_ID_REUSED | NO |
| PROGRESSIVE_SEARCH_EFFECTIVE | TRUE |
| PUBLIC_PM2 / DASHBOARD_PM2 | online |
| OLS_HASH | PASS |
| LIVE_SOURCE_DRIFT | 0 |
| Selected fare on passenger summary | ECONOMY VALUE (`fare_option_key=yvalue-pi1`) |

### Still BLOCKED / incomplete

| Field | Status |
| --- | --- |
| ORIGINAL_PRICE_REFRESH_BLOCKER | **BLOCKED** — passenger Order Summary still shows “Price needs to be refreshed” after Continue from Details |
| LIVE_ONE_WAY_REVIEW_REACHED | BLOCKED (price banner + passport form completion not closed in this pass) |
| LIVE_PAIRED_REVIEW_REACHED | NOT COMPLETED this pass |
| LIVE_SPLIT_REVIEW_REACHED | NOT COMPLETED this pass |
| LIVE_BROWSER_BACK_BFCACHE_REFRESH | Implemented; not fully timed on live this pass |
| AGENT/ADMIN supplier badge live UI | API/auth tests PASS; live authenticated badge UI not screenshot-closed this pass |
| Formal P95 tables across OW/Pair/Split | Not finalized |

Supplier latency remains high; fanout remains **SEQUENTIAL**. Do not claim first-responder across multi-provider.

---

## Commercial safety

```text
NO_REAL_PNR_CREATED_BY_QA=YES
NO_REAL_PNR_CANCELLED=YES
NO_REAL_TICKET_ISSUED=YES
NO_REAL_TICKET_VOIDED=YES
NO_ABHIPAY_PAYMENT=YES
COMMERCIAL_EXTERNAL_SIDE_EFFECTS=0
```

---

## Verdict

```text
COMMERCE_ENGINEERING=PASS
PROTECTED_DEPLOY=PASS
COMMERCE_PRODUCTION_CERTIFICATION=BLOCKED
TIER3_READY=NO
OWNER_RETEST_V3_STATE=BLOCKED_PENDING_FINAL_SABRE_LIFECYCLE_PROOF
NEXT=Close live price_needs_refresh after successful revalidation; finish OW/Pair/Split Review + role badge screenshots; then Tier-3 preflight only after owner authorization
```

Do **not** execute Sabre cancellation / PNR / ticket / payment in this phase.
