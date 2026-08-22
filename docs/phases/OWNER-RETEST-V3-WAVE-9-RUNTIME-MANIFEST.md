# OWNER-RETEST-V3 WAVE-9 — EXACT RUNTIME MANIFEST

**Status:** engineering closed for payment-safety; **DO NOT DEPLOY** from this document alone.

## SHA pins (stable terminology)

| Pin | Value | Meaning |
|---|---|---|
| Production base SHA | `8cf657d7d35cc97848318f56184825ac49af6225` | Current production runtime |
| `FINAL_WAVE9_ENGINEERING_SHA` | `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` | Last Wave-9 **runtime/product** engineering commit (AbhiPay v3 amount contract) |
| Prior engineering (pre-payment-fix) | `1c846fa8438483aea213400bbba26760aecfee1a` | Last Review/payment-UI engineering before this payment-safety fix |
| Docs-only tips (not deployable) | `dfc70e74…`, `10392382…`, `8db2fe84…` | Docs and/or visual Playwright only — **never** label as ENGINEERING_SHA |

`PREDEPLOY_DOCS_HEAD` / `CURRENT_BRANCH_TIP` are set by the subsequent docs commit and must not be confused with `FINAL_WAVE9_ENGINEERING_SHA`.

## Exact runtime delta

```text
8cf657d7d35cc97848318f56184825ac49af6225
→ 67417d225fcd70e8e8cb1a1b535ec0ed8eee0877
```

Generated from Git (excludes `docs/**`, `tests/**`, `frontend/tests/**`, `tmp/**`, screenshots, `.next`, private tooling):

| Status | Path |
|---|---|
| modified | `app/Http/Controllers/Frontend/BookingController.php` |
| modified | `app/Http/Requests/Frontend/StoreBookingPassengersRequest.php` |
| modified | `app/Services/Payments/Gateways/AbhiPayGateway.php` |
| modified | `app/Support/Booking/StandardBookingJsonPresenter.php` |
| modified | `frontend/features/booking-layout/components/OrderSummary.tsx` |
| modified | `frontend/features/standard-booking/components/BookingReviewPage.tsx` |
| modified | `frontend/features/standard-booking/components/CardPaymentPage.tsx` |
| modified | `frontend/features/standard-booking/components/PaymentMethodSelector.tsx` |
| modified | `frontend/features/standard-booking/components/ReviewPassengerList.tsx` |
| modified | `frontend/features/standard-booking/itinerary/ItineraryTimeline.tsx` |
| modified | `frontend/features/standard-booking/types/review-payment.ts` |

```text
EXACT_RUNTIME_FILE_COUNT=11
MIGRATIONS=NONE
DATABASE_CHANGES=NONE
UNRELATED_RUNTIME_SUBSYSTEMS=NONE
```

## Payment contract change

AbhiPay **v3** create-order `amount` is major currency units (e.g. PKR `79089.00` → request `79089`), not paisa/`*100`.

Verification compares provider major-unit `amount` to `PaymentTransaction.amount` with PKR decimal tolerance (`< 0.01`). A remote value of `7908900` against local `79089.00` **fails** (no silent `/100` heuristic).

Provider `currencyType` is authoritative when present; mismatch vs transaction currency fails verification.

Create/verify endpoints remain:

- Base: `https://api.abhipay.com.pk/api/v3`
- Create: `POST /orders`
- Verify: `GET /orders/by-rrn/{clientTransactionId}` (optional `GET /orders/{orderId}` when order id known)
- Authorization secret: server-side header only

## Rollback base

Redeploy / restore runtime from production base:

`8cf657d7d35cc97848318f56184825ac49af6225`

## Excluded from runtime deploy

- `docs/**`
- `tests/**` / `frontend/tests/**`
- `tmp/**`, screenshots, `.next/**`, private tooling

## Commercial safety

No live AbhiPay order, PNR, ticket, refund, or payment mutation was performed during this closure. Payment tests use `Http::fake` / mocks only.

## Production AbhiPay config (read-only audit)

Performed against live JetPakistan app without printing secrets:

```text
ABHIPAY_RECORD_PRESENT=NO
ABHIPAY_ACTIVE=NO
ABHIPAY_CONFIGURED=NO
ABHIPAY_CHECKOUT_AVAILABLE=NO
ABHIPAY_ENVIRONMENT=none
ABHIPAY_BASE_URL_IS_V3=NO
ABHIPAY_CALLBACK_CONFIGURED=NO
```

Reason (non-secret): no `payment_gateways` row for AbhiPay. Do **not** fabricate a Pay by Card option until checkout is available.
