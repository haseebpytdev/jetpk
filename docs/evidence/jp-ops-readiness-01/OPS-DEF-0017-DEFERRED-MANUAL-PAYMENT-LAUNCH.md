# OPS-DEF-0017 — Deferred for Initial Manual-Payment Launch

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `d85edb1e24724eef11b9c6bc31e929155f0cdea9`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID:** `QClrSvr5W0V2GqllIeLKE`  
**Target agency:** `agency_id=1` JetPakistan (`asif-travels`)

---

## Business decision

| Field | Value |
|-------|--------|
| BUSINESS_DECISION_DATE | 2026-10-16 |
| INITIAL_PAYMENT_MODE | MANUAL |
| ONLINE_ABHIPAY | DISABLED (out of initial launch scope) |
| ABHIPAY_STATUS | UNFINISHED_DEFERRED |
| ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH | NO |
| FUTURE_ABHIPAY_VERIFICATION_REQUIRED | YES |

AbhiPay production activation is **not** required for the initial JetPakistan launch. The authoritative initial payment contract is **manual payment methods** already supported by the application.

---

## OPS-DEF-0017 status (must remain OPEN)

```
OPS_DEF_0017_STATUS=OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH
ABHIPAY_CERTIFICATION=UNFINISHED_DEFERRED
ABHIPAY_PROD_CONFIGURED=NO
ABHIPAY_PROD_CERTIFIED=NO
```

**Do NOT claim:** `ABHIPAY_READY`, `ABHIPAY_CERTIFIED`, or `OPS_DEF_0017_CLOSED`.

---

## Production facts (unchanged / unfinished)

| Fact | Value |
|------|--------|
| ABHIPAY_GATEWAY_ROW_COUNT | 0 |
| AGENCY_1_ABHIPAY_ROWS | 0 |
| ABHIPAY_AUDIT_EVENTS | 0 |
| ABHIPAY_ACTIVE | NO |
| ABHIPAY_CHECKOUT_READY | NO |
| PAYMENT_TRANSACTIONS (AbhiPay) | 0 |
| ABHIPAY_CONNECTIVITY_TEST_EXECUTED | NO |
| ABHIPAY_LIVE_UAT_EXECUTED | NO |
| CARD_CHARGES | 0 |

Masked artisan (`payments:abhipay-status --agency_id=1`) on runtime `922f1577`: Active=no; Merchant ID=not set; Secret configured=no; Checkout ready=no (unsaved `firstOrNew` defaults — no persisted row).

---

## Prior forensics (preserved; not re-run)

Historical evidence remains valid:

- `OPS-DEF-0017-ABHIPAY-ACTIVATION-READINESS.md`
- `OPS-DEF-0017-ABHIPAY-CONFIG-CONNECTIVITY.md`
- `OPS-DEF-0017-ADMIN-SAVE-PERSISTENCE-FORENSICS.md`

Proven: web/CLI app root match `/home/pkjetp/jetpk_app`; DB `jetpk_prod` match; form/controller/authorization/encryption/schema healthy; **no AbhiPay save-path code defect proven**. Prior classification `UNKNOWN_REQUIRES_CONTROLLED_RESAVE_TRACE` is **intentionally not completed** due to this business decision.

Controlled resave wake: **stopped** (`ABHIPAY_WAKE_ACTIVE=NO`, `EXPECTED_SUPERSEDED_WAKE_EXIT`).

---

## Initial payment launch contract

| Capability | Launch posture |
|------------|----------------|
| ONLINE_ABHIPAY | DISABLED |
| MANUAL_BANK_TRANSFER | ALLOWED |
| MANUAL_PAYMENT_PROOF | ALLOWED |
| STAFF_PAYMENT_VERIFICATION | ALLOWED (existing admin/staff verify/reject routes) |
| CASH_MANUAL | ALLOWED where `BookingPaymentMethod::Cash` policy applies |
| EXTERNAL_MANUAL_CARD | ALLOWED where `BookingPaymentMethod::CardManual` policy applies |
| Easypaisa / JazzCash (manual proof) | ALLOWED where configured in app enums/workflows |
| AUTO_PAYMENT_GATEWAY_SETTLEMENT | NOT_IN_LAUNCH_SCOPE |
| AUTO_TICKETING_AFTER_PAYMENT | NO |

Enum methods already in code: `bank_transfer`, `cash`, `card_manual`, `easypaisa`, `jazzcash`, `abhipay`, `other`. Launch uses non-AbhiPay methods only.

Proof submit routes (present): `customer.bookings.payment-proof`, `agent.bookings.payment-proof`, `guest.bookings.payment-proof`.  
Staff/admin review: `admin.bookings.payments.verify` / `reject`, `staff.bookings.payments.verify` / `reject`.

---

## UI consistency (read-only)

| Check | Result |
|-------|--------|
| ABHIPAY_UI_AVAILABLE | NO — no active gateway row; `PublicAbhiPayCheckoutPresenter` gates `show_pay_button` / `gateway_available` on `isAbhiPayAvailableForBooking` |
| MANUAL_PAYMENT_UI_AVAILABLE | YES — payment-proof submit + admin/staff verify/reject paths exist |
| New AbhiPay-as-available UI defect | NONE opened from this deferral |

OPS-DEF-0025 (ticketing_note wording) remains OPEN P3 and is unrelated to launch payment mode.

---

## OPS-DEF-0018 / scorecard impact

| Field | Value |
|-------|--------|
| OPS_DEF_0018_ABHIPAY_BLOCKER | NO (for INITIAL MANUAL-PAYMENT launch profile) |
| OPS_DEF_0018_MANUAL_PAYMENT_CERT_REQUIRED | YES |
| OPS_DEF_0018_ONLINE_PAYMENT_CERT_DEFERRED | YES |

Payments domain scorecard: **AMBER** (manual launch contract; AbhiPay deferred — not GREEN until manual E2E certified).

---

## Separate risk (unchanged)

**OPS-RISK-0017** — Production vhost access logging not recording application traffic. Observability only; do not repair in this deferral. Does not block manual-payment launch contract documentation.

**OPS-RISK-0016** — Reclassified as deferred residual (online AbhiPay unavailable by design for initial launch).

---

## Future reopen trigger

When JetPakistan decides to activate online card payment, reopen OPS-DEF-0017 from:

1. Configure agency_id=1 AbhiPay gateway (Live/Active + credentials)
2. Merchant dashboard callback registration
3. Safe credential connectivity test (no charge)
4. Controlled live-payment UAT
5. Callback + server verification certification
6. One-time settlement proof
7. Confirm no auto-ticket

---

## Wake / mutation safety this loop

| Action | Status |
|--------|--------|
| AbhiPay wake | STOPPED |
| Gateway row created | NO |
| Credentials requested/entered by Cursor | NO |
| Test connection | NO |
| Live charge / order | NO |
| `.env` / DB config changes | NO |
