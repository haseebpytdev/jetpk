# AbhiPay Activation Checklist (OPS-DEF-0017)

Use this before any controlled live charge. Check items only with evidence.

## A. Configuration (OTA admin)

- [ ] Agency AbhiPay row exists in `payment_gateways` (`code=abhipay`)
- [ ] `environment=live`
- [ ] `is_active=true`
- [ ] Merchant ID configured (encrypted at rest)
- [ ] Merchant secret configured (encrypted at rest)
- [ ] Base URL HTTPS `https://api.abhipay.com.pk/api/v3` (or approved AbhiPay host)
- [ ] `payments:abhipay-status --agency_id={id}` shows Checkout ready=yes
- [ ] No secrets printed in status/logs/UI serialization

## B. Callback local consistency

- [ ] Route `payments.abhipay.callback` present → `/payments/abhipay/callback`
- [ ] Stored/admin-presented callback URL = `https://jetpakistan.pk/payments/abhipay/callback`
- [ ] Success/cancel/decline URLs match `/payment/{success|cancel|decline}`
- [ ] CSRF exempt for callback path
- [ ] Callback throttle active (`abhipay-payment-callback`)
- [ ] Public GET/HEAD reachability PASS (no auth wall / no CF challenge blocking S2S)

## C. Merchant dashboard (external)

- [ ] Merchant account live-enabled at AbhiPay
- [ ] Callback URL registered in AbhiPay dashboard (cannot infer from OTA alone)
- [ ] Success/cancel/decline URLs registered if required by AbhiPay

## D. Controlled connectivity (no charge)

- [ ] Owner approval recorded
- [ ] Admin **Test connection** OR equivalent `testConnection()` only
- [ ] Expected request: `GET /orders/by-rrn/OTA-TEST-CONNECTION`
- [ ] Record HTTP status only; no PaymentTransaction / BookingPayment created
- [ ] Unauthorized (401/403) treated as credential failure — do not proceed to UAT

## E. Controlled live payment UAT (separate approval)

- [ ] Dedicated QA booking + small approved amount
- [ ] Human approval immediately before charge
- [ ] Capture client_transaction_id, gateway_order_id, redirect, callback, server verify
- [ ] BookingPayment created once; booking amount_paid / payment_status correct
- [ ] Ledger/notification once; no duplicate settlement; no auto-ticket
- [ ] Refund/void remains separate scope

## Current production snapshot (2026-10-15)

| Gate | Status |
|------|--------|
| A Configuration | FAIL — `payment_gateways` AbhiPay rows = 0 |
| B Callback local | PASS (routes + public reachability) |
| C Merchant dashboard | UNVERIFIED_EXTERNAL |
| D Connectivity | BLOCKED (no credentials) |
| E Live UAT | BLOCKED |

**Classification:** `BLOCKED_NO_GATEWAY_ROW`
