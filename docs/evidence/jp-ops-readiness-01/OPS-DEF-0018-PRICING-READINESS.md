# OPS-DEF-0018 — Pricing + Booking Read-Side Readiness

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `045c3fe642c5061b0cb4fb46ad7948e9613c5cf1`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID:** `QClrSvr5W0V2GqllIeLKE`  
**Branch:** `ops/ops-def-0018-pricing-readiness`

```
SABRE_LIVE_PNR_ALLOWED_NOW=NO
SABRE_PNR_UAT_STATUS=DEFERRED_UNTIL_GO_LIVE_WINDOW
GO_LIVE_GATE_SABRE_PNR_001=PENDING_FUTURE_GO_LIVE_WINDOW
MANUAL_PAYMENT_UAT=PASS
PRICING_READINESS=PASS_FOR_INITIAL_LAUNCH_PROFILE
OPS_DEF_0018_STATUS=PARTIAL_DEFERRED_LIVE_SABRE_PNR_UAT
SABRE_PNR_CREATE_CALLS=0
SUPPLIER_MUTATIONS=0
PAYMENT_MUTATIONS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
```

This loop is **docs/evidence + read-only production inspection only**. No runtime application code changes. No Sabre CreatePNR / cancel / ticket. No payment/wallet/ledger mutation. Booking `JPQAMPAY9B8E85CF` (id=40) reused **read-only**.

---

## Phase 1 — Sabre PNR deferral (governance)

| Field | Value |
|-------|--------|
| TECHNICAL_READINESS | READY |
| LIVE_SUPPLIER_CERTIFICATION | DEFERRED_UNTIL_GO_LIVE_WINDOW |
| OPS_DEF_0018_STATUS | PARTIAL_DEFERRED_LIVE_SABRE_PNR_UAT |
| SABRE_PNR_UAT_READINESS | READY_AWAITING_GO_LIVE_AUTHORIZATION |
| LIVE_PNR_EXECUTION_REQUIRED_BEFORE_GO_LIVE | YES |
| LIVE_PNR_EXECUTION_ALLOWED_NOW | NO |
| LIVE_PNR_DEFERRAL_REASON | Supplier financial/fine/ADM exposure before production launch window |

Do **not** mark OPS-DEF-0018 CLOSED or PASS.

---

## Phase 2 — GO_LIVE_GATE_SABRE_PNR_001

**State:** `PENDING_FUTURE_GO_LIVE_WINDOW`

Required immediately before actual launch (not now):

1. Explicit user authorization  
2. Approved real QA traveler  
3. Safe current direct one-way itinerary  
4. Supplier/fare terms reviewed  
5. No immediate ticketing requirement  
6. One CreatePNR only  
7. No auto-ticket  
8. Controlled unticketed release/cancel if commercially safe  
9. Verify no duplicate PNR  
10. Certify local/supplier state  

---

## Phase 6 — Source trace (canonical)

| Label | Source |
|-------|--------|
| SEARCH_PRICE_SOURCE | Supplier normalized offer → `PricingRuleService` (FX + markup) → offer `total` / `pricing_components.final_total` |
| BOOKING_PRICE_SOURCE | Persisted `booking_fare_breakdowns.total` (authoritative fare total once booking exists) |
| CUSTOMER_PAYABLE_SOURCE | `BookingPayableResolver::customerPayableTotal()` = `payable_after_promo` if set else fare total |
| ADMIN_PRICE_SOURCE | Admin `detail-body.blade.php` → `BookingPayableResolver::customerPayableTotal()` + fare breakdown total for fare lines; payments tab also `BookingPaymentSummaryPresenter` |
| AGENT_PRICE_SOURCE | Blade: `BookingPaymentSummaryPresenter`; Next agent detail: `StandardBookingJsonPresenter` pricing + AbhiPay payable |
| PAYMENT_BALANCE_SOURCE | `BookingPaymentService::recalculateBookingPaymentStatus` → `amount_paid` = sum verified; `balance_due` = max(0, customerPayable − verified); column preferred by resolver when set |

```
CANONICAL_FARE_TOTAL=booking_fare_breakdowns.total
CANONICAL_CUSTOMER_PAYABLE=BookingPayableResolver::customerPayableTotal
CANONICAL_PROMO_ADJUSTED_PAYABLE=payable_after_promo (nullable → fare total)
CANONICAL_OUTSTANDING_BALANCE=BookingPayableResolver::balanceDue / booking.balance_due after recalc
```

---

## Phase 5 — Invariant results

| ID | Invariant | Result | Evidence |
|----|-----------|--------|----------|
| INV_1 | Selected fare → booking fare source | PASS (architecture) | Checkout persists breakdown; `CheckoutFareBreakdownPresenter` prefers DB total when present |
| INV_2 | `customerPayableTotal` = amount customer asked to pay | PASS | Blade summary + `payableAmountForBooking` both use resolver |
| INV_3 | Manual payment balance uses same payable | PASS | `BookingPaymentService` recalc uses `customerPayableTotal` |
| INV_4 | Verified payment reduces balance exactly once | PASS | Booking 40: verified 1.00 → amount_paid 1.00 / balance 0.00 / status paid |
| INV_5 | Promo consistency across surfaces | PASS_WITH_RESIDUAL | Resolver+Blade consistent; Next `pricing.total` is fare (not promo-adjusted) — OPS-DEF-0026 |
| INV_6 | Currency consistent | PASS (sample) | Booking 40 PKR throughout; payments inherit booking currency |
| INV_7 | Taxes/fees/markup/discount compose to total | PASS (sample 40) | base 1 + tax/fee/markup/discount 0 = total 1 |
| INV_8 | No PNR required for local payable | PASS | local_qa_inert paid without PNR |
| INV_9 | balance_due = payable − verified | PASS (40) | 1.00 − 1.00 = 0.00 |
| INV_10 | Fully verified → paid + balance 0 | PASS (40) | payment_status=paid, balance_due=0 |

---

## Phase 7 — Cross-surface read-only sample (QA)

| Field | Value |
|-------|--------|
| BOOKING_REFERENCE_MASKED | JPQAMP…85CF |
| BOOKING_ID | 40 |
| PROVIDER | local_qa_inert |
| CURRENCY | PKR |
| FARE_BREAKDOWN_TOTAL | 1.00 |
| PAYABLE_BEFORE_PROMO | 1.00 |
| PROMO_DISCOUNT | 0.00 |
| PAYABLE_AFTER_PROMO | 1.00 |
| CUSTOMER_DISPLAY_TOTAL | 1.00 (`BookingPaymentSummaryPresenter.customer_payable`) |
| AGENT_DISPLAY_TOTAL | 1.00 (same presenter on agent Blade) |
| ADMIN_DISPLAY_TOTAL | 1.00 (`BookingPayableResolver::customerPayableTotal`) |
| AMOUNT_PAID | 1.00 |
| BALANCE_DUE | 0.00 |
| PAYMENT_STATUS | paid |

Raw: `_raw-ops-def-0018-pricing-booking40.json`

Additional QA fixtures (ids 14–20) include zero-fare / stale `balance_due` rows — hygiene residual OPS-DEF-0027; not used as launch payable proof.

---

## Phase 8 — Manual payment UAT reuse

Prior execution `OPS-DEF-0018-MANUAL-PAYMENT-UAT-EXECUTION.md` unchanged. Read-only re-probe confirms still:

`PAYABLE=1.00` · `VERIFIED=1.00` · `AMOUNT_PAID=1.00` · `BALANCE_DUE=0.00` · `PAYMENT_STATUS=paid`

**Do not mutate again.**

---

## Phase 9 — Promo invariants (code + tests; no production redeem)

| Field | Value |
|-------|--------|
| PROMO_PAYABLE_FORMULA | `final = round(max(0, original − discount), 2)` via `PromoCodeCalculator`; caps at `max_discount`; non-zero path keeps ≥1 unless zero-payable allowed |
| PROMO_CAN_OVERDISCOUNT | NO (default); YES only if `ota.promo.allow_zero_payable` + `internal_testing_only` |
| ZERO_PAYABLE_ALLOWED | Config-gated (`allow_zero_payable`) + internal testing promo only |
| PROMO_REDEMPTION_TRIGGER | On full paid: `BookingPaymentService::recalculateBookingPaymentStatus` → `PromoCodeService::redeemForBooking` |

Regression baseline: `tests/Feature/PromoCodeCheckoutTest.php` (asserts payable_after_promo ↔ resolver).

---

## Phase 10 — Rounding

All money paths inspected use `round(..., 2)` / `decimal:2` casts on booking promo/payable fields.  
**Display residual:** Next JSON `formatted_total` / AbhiPay `formatted_amount` use `number_format(..., 0)` (whole PKR display) while authoritative floats remain 2dp — not a computation mismatch for typical PKR integers; note for fractional display.

`ROUNDING_INVARIANT=PASS` for computation; display formatting AMBER residual only.

---

## Phase 11 — Multi-currency

| Field | Value |
|-------|--------|
| CURRENCY_CONVERSION_SUPPORTED | YES at search/pricing (`PricingRuleService` + `LiveFxRateService` → PKR when converted) |
| PAYMENT_CURRENCY_MUST_MATCH_BOOKING | YES in practice — payments / AbhiPay transactions use `booking.currency`; AbhiPay verify rejects remote currency mismatch |
| CROSS_CURRENCY_PAYMENT_BLOCKED | YES (gateway remote ≠ transaction currency) |

No FX transaction executed this loop.

---

## Phase 12 — UI/presenter consistency

| Surface | Presenter |
|---------|-----------|
| CUSTOMER_PRICING_PRESENTER | Blade: `BookingPaymentSummaryPresenter` → resolver; Next: `CustomerPortalBookingDetailPresenter` → `StandardBookingJsonPresenter` |
| AGENT_PRICING_PRESENTER | Blade: same summary presenter; Next: `AgentPortalBookingDetailPresenter` → standard JSON |
| ADMIN_PRICING_PRESENTER | `detail-body.blade.php` + `BookingPaymentSummaryPresenter` + resolver |

```
FORMULA_DIVERGENCE_FOUND=YES
```

**Soft divergence (not launch-blocking for MANUAL):** Next `presentAuthoritativePricing()` sets `pricing.total` from fare breakdown / checkout rows, while amount due / AbhiPay payable uses `BookingPayableResolver::customerPayableTotal` (promo-aware). Blade customer/agent/admin payment cards are aligned on resolver. Tracked as **OPS-DEF-0026**.

---

## Phase 13 — New pricing defects

| ID | Severity | Surface | Mismatch | Launch impact |
|----|----------|---------|----------|---------------|
| OPS-DEF-0026 | P2 | Next `StandardBookingJsonPresenter.pricing.total` | Fare total vs promo-adjusted payable | Soft UX inconsistency when promo applied; payment math still resolver-correct |
| OPS-DEF-0027 | P3 | Legacy QA fixtures | Stored `balance_due` with fare_total=0 | Data hygiene; exclude from KPI; not initial-launch payable path |

No runtime patch in this evidence loop.

---

## Phase 14 — Domain reassessment

```
PRICING_READINESS=PASS_FOR_INITIAL_LAUNCH_PROFILE
SCORECARD_PRICING=AMBER
```

Not GREEN: live supplier price revalidation unexecuted; Next promo display residual; authenticated browser depth partial.

---

## Phase 15 — Browser depth

```
BROWSER_DEPTH_STATUS=BROWSER_DEPTH_PARTIAL
```

Health: `/up` 200, `/login` 200, `/admin` 302. No authenticated QA browser session this loop. Pricing correctness proven via server-side presenters + DB read probe — distinguished from browser UX depth.

---

## Phase 16 — OPS-DEF-0018 reassessment

| Track | Status |
|-------|--------|
| Manual payment UAT | PASS |
| Sabre PNR | DEFERRED_UNTIL_GO_LIVE_WINDOW (`GO_LIVE_GATE_SABRE_PNR_001`) |
| AbhiPay | OUT_OF_INITIAL_LAUNCH_SCOPE (OPS-DEF-0017) |
| Pricing read-side | PASS_FOR_INITIAL_LAUNCH_PROFILE |

```
CURRENT_NON_DEFERRED_P1_BLOCKERS=NONE
```

(Deferred P1s remain: Sabre live PNR go-live gate; AbhiPay deferred. They must not block other readiness domains.)

---

## Safety statement

```
SABRE_PNR_CREATE_CALLS=0
SABRE_CANCEL_CALLS=0
TICKETING_CALLS=0
SUPPLIER_MUTATIONS=0
PAYMENT_MUTATIONS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
```
