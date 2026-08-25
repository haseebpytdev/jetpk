# JP-BO-04G Progressive Remediation — Final Live Commerce Proof

**Status:** COMMERCE PRODUCTION CERTIFICATION — PASS (Tier-3 not executed)  
**Branch:** `phase/jp-bo-04g-progressive`  
**UTC:** 2026-08-25

---

## Pins

| Pin | Value |
| --- | --- |
| PREVIOUS_LIVE_SHA | `e93f90f5b57b44daa3486edf728812e44b60b030` |
| PREVIOUS_BRANCH_HEAD | `86263e71a55cbe3f0ace51adfa34056ee2dc4f00` |
| PRICE_AUTHORITY_ENGINEERING_SHA (Laravel) | `82b2b8e78992b417325074105d8ec3b92057ba84` |
| PRICE_AUTHORITY_FE_SHA | `4ff3af2721b179e5cf5e0a55fde11aa65b451bc9` |
| FINAL_DOCS_SHA | _(this commit)_ |
| OLD_PUBLIC_BUILD | `F1hRrv3NulCyaAh_s0nmw` |
| NEW_PUBLIC_BUILD | `5jcScCO5Ujc-40-4nw1kr` |
| BACKUP_ID | `jp-bo-04g-price-authority-20260825T194529Z` |
| ROLLBACK_ENGINEERING_SHA | `e93f90f5b57b44daa3486edf728812e44b60b030` |
| ROLLBACK_PUBLIC_BUILD | `F1hRrv3NulCyaAh_s0nmw` |

Runtime pins: Laravel authority = `82b2b8e7`; FE BFCache criteria = `4ff3af27`. Docs/test tip is not a deploy pin.

---

## Root cause

`PRICE_REFRESH_ROOT_CAUSE=E+G (+C secondary)`

1. **E — AUTHORITATIVE_INTENT_WRITTEN_BUT_OVERWRITTEN_LATER**  
   Successful revalidation could mark selected fare authoritative in the booking draft, but passengers GET `applyValidatedFareOptionSelection` / `reaffirmSelectedFareFamilyIntent` re-sanitized from the offer and overwrote draft intent without preserving `authoritative_after_revalidation`.

2. **G — PASSENGER_PRESENTER_PREFERS_STALE_DRAFT**  
   Presenter prefers `draft.selected_fare_family_option`, so approximate flags after overwrite produced `price_needs_refresh=true` / “Price needs to be refreshed”.

3. **C — SELECTED_FARE_LOOKUP_FAILS_AFTER_REFRESH** (secondary)  
   `persistSelectedFareIntoBookingDraft` early-returned without clearing/updating intent when refreshed offer lost brand cards, leaving stale approximate intent.

4. **Fare-change contract**  
   Successful Sabre/IATI refresh marked authoritative even when `requires_fare_change_acceptance=true`.

5. **BFCache criteria bug (FE)**  
   `buildFreshResultsSearchParams` preserved `origin`/`destination`/`departure_date` but public URLs use `from`/`to`/`depart`, so browser Back stripped route criteria.

---

## Exact fix

- Persist authoritative selected fare after successful unchanged revalidation; clear or block on unlinked resolution failure; preserve linked brand intent when refreshed offer loses brand arrays.
- Do not mark unaccepted fare changes authoritative.
- Preserve authoritative flag across passengers reaffirm / sticky merge.
- Preserve `from`/`to`/`depart` (and aliases) on checkout Back/BFCache fresh search.

---

## Manifests

### Laravel price-authority (`e93` → `82b2b8e7`)

| Area | Count |
| --- | --- |
| Laravel | 3 |
| Frontend | 0 |
| Config | 0 |
| Dashboard | 0 |
| Migrations | 0 |
| **EXACT_DEPLOYABLE_FILE_COUNT** | **3** |

1. `app/Http/Controllers/Frontend/FlightController.php`  
2. `app/Http/Controllers/Frontend/BookingController.php`  
3. `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`

### FE BFCache criteria (`82b2b8e7` → `4ff3af27`)

| Area | Count |
| --- | --- |
| Frontend | 1 |
| **EXACT_DEPLOYABLE_FILE_COUNT** | **1** |

1. `frontend/features/flight-results/utils/checkout-nav.ts`

---

## Live production evidence

| Field | Status |
| --- | --- |
| ORIGINAL_PRICE_REFRESH_BLOCKER | **FIXED_ON_LIVE_PRODUCTION** |
| LIVE_ONE_WAY_PRICE_AUTHORITY | PASS (`price_needs_refresh=false`, banner absent) |
| LIVE_ONE_WAY_REVIEW_REACHED | PASS |
| LIVE_PAIRED_PRICE_AUTHORITY | PASS |
| LIVE_PAIRED_REVIEW_REACHED | PASS |
| LIVE_SPLIT_PRICE_AUTHORITY | PASS |
| LIVE_SPLIT_REVIEW_REACHED | PASS |
| LIVE_BROWSER_BACK_BFCACHE_REFRESH | PASS (new `search_id`, criteria ISB/DXB/depart preserved) |
| DUPLICATE_BACK_SEARCHES | 0 intentional full searches (progressive poll requests observed during single refresh) |
| LIVE_AGENT_SUPPLIER_BADGE | PASS |
| LIVE_ADMIN_SUPPLIER_BADGE | PASS (STAFF QA identity on public results) |
| LIVE_GUEST_SUPPLIER_BADGE | NO |
| LIVE_CUSTOMER_SUPPLIER_BADGE | NO |
| UNAUTHORIZED_SUPPLIER_LABEL_API_LEAK | 0 |
| LIVE_SOURCE_DRIFT | 0 |
| BACK_OFFICE_REGRESSION | PASS (read-only smoke) |

Evidence dirs: `tmp/jp-bo-04g-price-authority/live-proof/`

---

## Performance (sample)

| Metric | Sample |
| --- | --- |
| SEARCH_TO_SHELL_MS | 13428 (supplier-dominated; app shell path) |
| APPLICATION_SUB_1_SECOND | PASS for JetPakistan-controlled gates where sampled; end-to-end blocked by supplier |
| END_TO_END_SUB_1_SECOND | BLOCKED_BY_SUPPLIER |
| Fanout | SEQUENTIAL |

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
COMMERCE_PRODUCTION_CERTIFICATION=PASS
TIER3_READY=YES
OWNER_RETEST_V3_STATE=BLOCKED_PENDING_FINAL_SABRE_LIFECYCLE_PROOF
NEXT=Owner-authorized FINAL SABRE LIFECYCLE PREFLIGHT only — do not cancel/create PNR/ticket/payment in this phase
```

Do **not** execute Sabre cancellation / PNR / ticket / payment without a new owner authorization.
