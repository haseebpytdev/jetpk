# AbhiPay Activation Checklist (OPS-DEF-0017)

Use this before any controlled live charge. Check items only with evidence.

## Target agency (selected 2026-10-16)

```
TARGET_ABHIPAY_AGENCY_ID=1
TARGET_ABHIPAY_AGENCY_NAME=JetPakistan
TARGET_AGENCY_SLUG=asif-travels
ADMIN_PAGE=https://jetpakistan.pk/admin/settings/payments?agency_id=1
```

Selection basis: `config('ota.default_agency_slug')=asif-travels` + all B2C (`agent_id` NULL) bookings on agency 1 (38/38). QA agencies 3–5 and Easy Ticket (2) are out of launch scope for this row.

## A. Configuration (OTA admin)

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

## Current production snapshot (2026-10-16 config-connectivity loop)

| Gate | Status |
|------|--------|
| Target agency | SELECTED — agency_id=1 JetPakistan |
| A Configuration | FAIL — AbhiPay rows still 0; **waiting for live credentials** |
| B Callback local | PASS (routes + public reachability) |
| C Merchant dashboard | UNVERIFIED_EXTERNAL |
| D Connectivity | BLOCKED (credentials unavailable to operator) |
| E Live UAT | BLOCKED |

**Classification:** `BLOCKED_WAITING_FOR_ABHIPAY_CREDENTIALS`

**Admin fields requiring human entry:** Active, Environment=Live, Merchant ID, Merchant secret key (never paste into chat/logs/git).
