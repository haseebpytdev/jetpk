# SUMMARY — AirBlue V3 Final Frontend Mutation UAT

## A) Original blocked run (preserved)

**STATUS: BLOCKED** (do not rewrite as PASS)

| Field | Value |
|---|---|
| Date | 2026-10-28 |
| Production | https://jetpakistan.pk |
| Runtime SHA (deployed) | `93882cbc722901b637e2ebd655c26293ce0e9ff4` |
| UAT seed | `.recovery/pr135-final-uat/_final-search-seed.php` |
| Search id hash | `cd834678ea2a` |

### Outcome

Stopped before AirBook. Live ephemeral TEST seed produced AirBlue offers with structured baggage arrays; `/flights/results/data` returned HTTP 500.

### Blocker

```
BLOCKER_CONFIRMED=
FlightController buildFilterMeta array baggage coercion

DIAG_MSG=Array to string conversion
DIAG_FILE=/home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/FlightController.php:2135

AIRLOWFARESEARCH_CALLS_IN_BLOCKED_RUN=1
MUTATIONS_IN_BLOCKED_RUN=0
```

Closure PR: `#136` — `SUMMARY-AIRBLUE-V3-BAGGAGE-FILTER-BLOCKER-CLOSURE.md`

---

## B) PR136 resume run (2026-10-28)

| Field | Value |
|---|---|
| MERGED_PR | 136 |
| MERGE_SHA / MAIN_SHA | `0950593947ce36caf1dbbf107cb18ccb8ebc36fb` |
| DEPLOY_BACKUP | `/home/pkjetp/backups/airblue-pr136-baggage-fix-pre-20261028T182523Z` |
| DEPLOY_RUNTIME_FILES | 7 |
| SOURCE_DEPLOY_HASH_PARITY | PASS |
| Mutation anchors changed | NO (classifier/booking/ticketing/refund hashes unchanged) |
| AIRBLUE_IN_PUBLIC_ALLOWLIST | NO |
| V3_TEST_PUBLIC_SEARCH_ELIGIBLE | NO |

### Deploy + baggage gate

| Gate | Result |
|---|---|
| PHP_SYNTAX | PASS |
| CONTAINER_RESOLUTION | PASS |
| POSTDEPLOY_HTTP_HEALTH | PASS (`/`, `/login`, `/up`, `/flights/results`, `/agent` → 200) |
| Offline structured-null fixture `results/data` | HTTP 200 |
| STRUCTURED_BAGGAGE_FATAL | NO |
| AIRBLUE_BAGGAGE_BUCKET | `no_baggage_info` |
| INVENTED_AIRBLUE_BAGGAGE | NO |
| Live seeded `results/data` | HTTP 200; EV/EF/EX = 25500/26500/27600; no `supplier_total` |
| Targeted PHPUnit | 136 tests / 746 assertions / 0 fail / 0 error |
| Phase21K (in suite) | green |

Raw logs:

- `_raw-pr136-deploy.txt`
- `_raw-pr136-baggage-results-probe.txt`
- `_raw-pr136-postdeploy-http.txt`
- `_raw-pr136-postdeploy-phpunit.txt`
- `_raw-pr136-final-search-seed.txt`
- `_raw-pr136-live-results-diag.txt`

### Resumed search (TEST v3 only)

| Field | Value |
|---|---|
| NEW_AIRLOWFARESEARCH_PROVIDER_CALLS | 1 |
| UAT_SEARCH_ID_HASH | `c1212b47d3ea` |
| Route / date | ISB→KHI / 2026-12-29 |
| REAL_AIRBLUE_OFFER_COUNT | 6 raw → 2 parents |
| PA201 EV/EF/EX customer | 25500 / 26500 / 27600 |
| PA201 EF supplier/customer | 26000 / 26500 |
| Conn22 | NO |
| V3 id hash | `535fa30d7e25` |

### Browser results UI

| Gate | Result |
|---|---|
| RESULT_CARD | PASS (2 AirBlue cards, no 500) |
| Baggage filter | No baggage info (2) |
| Refund filters | Refundable 0 / Non-refundable 0 |
| FARE_FAMILY_SELECTION | PASS on results drawer (EF Selected @ PKR 26,500) |
| Baggage truth | “Not provided by supplier” |
| PUBLIC_SUPPLIER_COST_EXPOSED on results JSON | NO |

### STOP before AirBook

Reached passengers → review with `fare_option_key=grouped-offer-b8dd8e100148`.

Store proof: that key maps to **EF** `price_total=26500` / member customer 26500 / member supplier 26000.

Review page displayed **PKR 25,000** (EV supplier layer) with no EF label / no 26,500 customer total.

```
PRE_AIRBOOK_CHECKPOINT=FAIL
CUSTOMER_TOTAL_STICKINESS=FAIL
SELECTED_FARE_STICKINESS=FAIL (UI review payable)
AIRBOOK_PROVIDER_CALLS=0
AIRBOOK_SUCCESSFUL_SENDS=0
TYPE5_SUCCESSFUL_SENDS=0
AIRDEMANDTICKET_SUCCESSFUL_SENDS=0
CANCEL_PROVIDER_CALLS=0
TYPE1_REFUND_PROVIDER_CALLS=0
```

Mission hard rule: mismatch → **STOP** (no second AirBook attempt).

### Provider accounting (this resume loop)

| Call | Count |
|---|---|
| NEW_AIRLOWFARESEARCH_PROVIDER_CALLS | 1 |
| AIRBOOK | 0 |
| READ | 0 |
| SEATMAP | 0 |
| TYPE5 | 0 |
| AIRDEMANDTICKET | 0 |
| CANCEL | 0 |
| TYPE1_REFUND | 0 |

### Protected fixtures

```
BOOKING_D_CHANGED=NO
BOOKING_E_CHANGED=NO
BOOKING_F_CHANGED=NO
CONN22_CHANGED=NO
PUBLIC_SUPPLIER_ALLOWLIST_CHANGED=NO
```

### Certification

```
PR136_PROD_DEPLOY=PASS
RESULTS_DATA_HTTP_STATUS=200
STRUCTURED_BAGGAGE_FATAL=NO
AIRBLUE_V3_FINAL_FRONTEND_MUTATION_CERTIFIED=NO
FINAL_STATUS=AIRBLUE_V3_FRONTEND_MUTATION_STOPPED_PRE_AIRBOOK_FARE_STICKINESS
NEXT_PATH=FIX_CHECKOUT_REVIEW_CUSTOMER_TOTAL_STICKINESS_FOR_GROUPED_EF_THEN_RESUME_MUTATION_UAT
```


---

## C) Review total stickiness closure (engineering — not deployed)

**Preserves A + B as blocked mutation UAT.** This section only records the checkout persistence fix.

| Field | Value |
|---|---|
| BRANCH | `fix/airblue-grouped-fare-booking-total-stickiness` |
| BASE_MAIN_SHA | `0950593947ce36caf1dbbf107cb18ccb8ebc36fb` |
| Evidence | `SUMMARY-AIRBLUE-V3-REVIEW-TOTAL-STICKINESS-CLOSURE.md` |
| CUSTOMER_TOTAL_PERSISTENCE_BUG_REPRODUCED | YES (B: review PKR 25000 after EF 26500) |
| INITIAL_BOOKING_TOTAL_WRITE_SOURCE_PRE_FIX | `pricing['final_total']` in `attachFareBreakdown` |
| REVALIDATION_TOTAL_WRITE_SOURCE_PRE_FIX | `pricing['final_total']` in `revalidateCheckoutBeforeConfirmation` |
| Offline EF after fix | selected/revalidated/fare_breakdown total **26500**; meta supplier **26000**; AirDemandTicket amount **26000** |
| PROVIDER_CALLS_THIS_LOOP | 0 |
| Merge / deploy | NO |

```
FINAL_STATUS=AIRBLUE_FINAL_UAT_REVIEW_TOTAL_STICKINESS_BLOCKER_CLOSED
NEXT_PATH=FINAL_REVIEW_MERGE_SCOPED_DEPLOY_THEN_RESUME_FINAL_AIRBLUE_MUTATION_UAT
```
