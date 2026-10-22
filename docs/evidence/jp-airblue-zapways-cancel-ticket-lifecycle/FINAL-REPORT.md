# JP-AIRBLUE-ZAPWAYS-CANCEL-TICKET-LIFECYCLE — Final Report

**Date (UTC):** 2026-10-22  
**MAIN_SHA:** `5808326bd4ca3faa4de7b9ad902afbae37a8b129`  
**PR #119 head (merged):** `1df3661274c0fe6967122a5514fa49b21bab0949`  
**Conn 22:** Zapways TEST `otatest4.zapways.com` v2.0 / `zapways_ota`  
**Production root:** `/home/pkjetp/jetpk_app`

## Phase 1 — PR119 deploy

```
PR119_DEPLOYED=YES
BACKUP=/home/pkjetp/backups/airblue-pr119-pre-20261022T180632Z
PARSER_PARITY=YES
BUILDER_PARITY=YES
PROD_PARSER_SHA256=dbfd3acf8571d002d2413216948c1786b46d0263155e292df0c8c3f5719c803c
PROD_BUILDER_SHA256=12fefec13f3d6ba4458c1fc8123d532a116e47dc6d66e22b5beab91b03cdfd98
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
HTTP_HEALTH=PASS
AIRBLUE_TESTS=PASS (local AirBlueOtaXml filter: 21 tests / 95 assertions)
OPTIMIZE_CLEAR=SKIPPED
```

Note: pre-deploy Parser already matched main; Builder was updated from experimental doc-contract hash to PR119 tip.

## Phase 2 — Connection isolation

```
SNAPSHOT_COUNT=6
CONN22_ISOLATION=PASS
CONN22_TICKETING_ENABLED=false
NON_CONN22_CONNECTION_ROWS_CHANGED=0
```

## Phase 3–5 — Booking A cancel lifecycle

PNR hash `f08f2faf6b61` (raw PNR omitted).

```
BOOKING_A_FOUND=YES
BOOKING_A_MATCH_COUNT=1
BOOKING_A_PRE_CANCEL_RETRIEVE=PASS
PRE_CANCEL_PNR_MATCH=YES
PRE_CANCEL_TICKETED=NO
BOOKING_A_CANCEL_CALLS=1
BOOKING_A_CANCEL_HTTP=200
BOOKING_A_CANCEL_RESULT=PASS
BOOKING_A_POST_CANCEL_RETRIEVE=PASS
BOOKING_A_PROVIDER_CANCEL_CONFIRMED=YES
BOOKING_A_LOCAL_CANCEL_CONFIRMED=YES
DUPLICATE_CANCEL_GUARD=PASS
SECOND_CANCEL_PROVIDER_CALLS=0
```

## Phase 6–10 — Booking B through payment-context audit

```
BOOKING_B_SEARCH=PASS
BOOKING_B_OFFER_COUNT=6
SEARCH_RBD=U
FARE_BASIS_CODE=EFTAOWU
FARE_BASIS_SOURCE=fare_info_attr
BASE=18030
TAX=7970
SUPPLIER_TOTAL=26000
MARKUP=500
CUSTOMER_PAYABLE=26500
BOOKING_B_PRICE_RECONCILES=YES
BOOKING_B_CHECKOUT=PASS
BOOKING_B_MANUAL_PAYMENT=PASS
BOOKING_B_PAYMENT_VERIFICATION=PASS
BOOKING_B_AIRBOOK_CALLS=1
BOOKING_B_AIRBOOK=PASS
AIRBOOK_B_HTTP=200
BOOKING_B_PNR_PRESENT=YES
BOOKING_B_INSTANCE_PRESENT=YES
PNR_B_HASH=33989e6f45a8
BOOKING_B_RETRIEVE=PASS
BOOKING_B_UNTICKETED=YES
SEAT_SELECTION_REQUIRED=NO
SEAT_MAP_CALLS=0
```

### Payment context audit (STOP gate)

```
AIRBLUE_CONTEXT_SUPPLIER_PAYABLE_PRESENT=NO
EXPECTED_SUPPLIER_PAYABLE=26000
CONTEXT_SUPPLIER_PAYABLE=ABSENT
SUPPLIER_PAYABLE_MATCH=NO
TICKETING_CALLS=0
TICKETING_SUCCESS=NOT_RUN
PHASE=STOP_PAYMENT_CONTEXT_MISSING_RUNTIME_PR_REQUIRED
```

Booking B metadata was **not** manually patched. Application defect confirmed: successful AirBook does not yet persist `supplier_payable_amount` into `airblue_context`.

## Runtime fix (unmerged)

Branch: `fix/airblue-ticketing-payment-context`

- Persist `supplier_payable_amount`, `payment_currency`, `payment_type` from offer `fare_breakdown.supplier_total` during AirBook success in `AirBlueBookingService`.
- Never use `BookingPayableResolver` / customer payable for Zapways PaymentInfo.
- Focused tests prove supplier 26000 vs customer 26500 → AirDemandTicket Amount=26000.00.

```
VOID_OPERATION_SUPPORTED=NO
PROVIDER_POST_TICKET_REVERSAL=AirBookModify
FULL_REFUND_MODIFICATION_TYPE=1
PARTIAL_REFUND_MODIFICATION_TYPE=2
REFUND_RUNTIME_PR=NOT_CREATED (ticketing not reached)
```

## Round-trip status

```
AIRBLUE_ONEWAY_AIRBOOK=CERTIFIED
AIRBLUE_ROUNDTRIP_BUILDER_OFFLINE=PASS (PR119 offline hardening on main)
AIRBLUE_ROUNDTRIP_SUPPLIER_CERTIFICATION=NOT_RUN
```

## Isolation end-state

```
NON_CONN22_CONNECTION_ROWS_CHANGED=0
TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS=0
SABRE_CONNECTION_CHANGED=NO
PIA_NDC_CONNECTION_CHANGED=NO
IATI_CONNECTION_CHANGED=NO
DUFFEL_CONNECTION_CHANGED=NO
ONEAPI_CONNECTION_CHANGED=NO
TICKETING_FEATURE_RESTORED=N/A (never enabled this loop)
```

## Next (after merge/deploy of payment-context PR)

1. Fresh AirBook (or controlled supplier_total=26000-only backfill — never 26500) so context fields present.
2. Temporary `ticketing_enabled=true` on conn 22 only → exactly one AirDemandTicket → retrieve → restore flag.
3. Offline `fix/airblue-zapways-v2-ticket-refund` for AirBookModify ModificationType=1 (no live refund until reviewed).

## Rollback (PR119 files)

Restore from:

`/home/pkjetp/backups/airblue-pr119-pre-20261022T180632Z/app/Services/Suppliers/AirBlue/`
