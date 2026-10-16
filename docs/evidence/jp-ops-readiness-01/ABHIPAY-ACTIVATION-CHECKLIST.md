# AbhiPay Activation Checklist (OPS-DEF-0017)

**Status (2026-10-16):** `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH`  
**Initial launch:** AbhiPay is **OUT OF SCOPE**. Do not treat unchecked items as initial go-live blockers.

Use this checklist only when JetPakistan decides to activate online card payment later.

## Target agency (selected 2026-10-16)

```
TARGET_ABHIPAY_AGENCY_ID=1
TARGET_ABHIPAY_AGENCY_NAME=JetPakistan
TARGET_AGENCY_SLUG=asif-travels
ADMIN_PAGE=https://jetpakistan.pk/admin/settings/payments?agency_id=1
```

Selection basis: `config('ota.default_agency_slug')=asif-travels` + all B2C (`agent_id` NULL) bookings on agency 1. QA agencies 3–5 and Easy Ticket (2) remain out of scope for this row.

## Launch contract note

| Field | Value |
|-------|--------|
| INITIAL_PAYMENT_MODE | MANUAL |
| ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH | NO |
| ABHIPAY_PROD_CONFIGURED | NO |
| ABHIPAY_PROD_CERTIFIED | NO |
| FUTURE_ABHIPAY_VERIFICATION_REQUIRED | YES |

Controlled resave / connectivity / live UAT wakes: **stopped**. Do not re-arm until reopen.

## A. Configuration (OTA admin) — FUTURE ONLY

- [ ] Agency AbhiPay row exists in `payment_gateways` (`code=abhipay`, `agency_id=1`)
- [ ] `environment=live`
- [ ] `is_active=true`
- [ ] Merchant ID configured (encrypted at rest)
- [ ] Merchant secret configured (encrypted at rest)
- [ ] Base URL HTTPS `https://api.abhipay.com.pk/api/v3` (or approved AbhiPay host)
- [ ] `payments:abhipay-status --agency_id=1` shows Checkout ready=yes
- [ ] No secrets printed in status/logs/UI serialization

## B. Callback local consistency

- [x] Route `payments.abhipay.callback` present → `/payments/abhipay/callback`
- [ ] Stored/admin-presented callback URL = `https://jetpakistan.pk/payments/abhipay/callback` (pending row create)
- [ ] Success/cancel/decline URLs match `/payment/{success|cancel|decline}` (pending row create)
- [x] CSRF exempt for callback path
- [x] Callback throttle active (`abhipay-payment-callback`)
- [x] Public GET/HEAD reachability PASS (GET empty → 302 decline)

## C. Merchant dashboard (external) — FUTURE ONLY

- [ ] Merchant account live-enabled at AbhiPay
- [ ] Callback URL registered in AbhiPay dashboard (cannot infer from OTA alone)
- [ ] Success/cancel/decline URLs registered if required by AbhiPay

## D. Controlled connectivity (no charge) — FUTURE ONLY

- [ ] Owner approval recorded
- [ ] Admin **Test connection** OR equivalent `testConnection()` only
- [ ] Expected request: `GET /orders/by-rrn/OTA-TEST-CONNECTION`
- [ ] Record HTTP status only; no PaymentTransaction / BookingPayment created
- [ ] Unauthorized (401/403) treated as credential failure — do not proceed to UAT

## E. Controlled live payment UAT (separate approval) — FUTURE ONLY

- [ ] Dedicated QA booking + small approved amount
- [ ] Human approval immediately before charge
- [ ] Capture client_transaction_id, gateway_order_id, redirect, callback, server verify
- [ ] BookingPayment created once; booking amount_paid / payment_status correct
- [ ] Ledger/notification once; no duplicate settlement; no auto-ticket
- [ ] Refund/void remains separate scope

## Current production snapshot (2026-10-16 deferral)

| Gate | Status |
|------|--------|
| Target agency | SELECTED — agency_id=1 JetPakistan |
| A Configuration | UNFINISHED_DEFERRED — `payment_gateways` **0** AbhiPay rows; intentional MANUAL launch |
| B Callback local | PASS (routes + public reachability) |
| C Merchant dashboard | N/A_UNTIL_REOPEN |
| D Connectivity | NOT_RUN (deferred) |
| E Live UAT | DEFERRED |

**Classification:** `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH`  
Prior forensic class `UNKNOWN_REQUIRES_CONTROLLED_RESAVE_TRACE` preserved historically; verification intentionally not completed.

**Do not use** `/admin/dashboard/settings/*` (Next) — no AbhiPay form there.

**Reopen trigger:** When online card payment is approved for production, start at Section A (agency_id=1 configuration) → D → E. Never paste secrets into chat/logs/git.
