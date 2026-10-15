# AbhiPay Controlled Live Payment UAT Plan (FUTURE ONLY)

**Status:** NOT EXECUTED in OPS-DEF-0017 activation-readiness loop  
**Prerequisite classification required first:** live configured + active + connectivity PASS + merchant callback registered

## Safety gates (hard stop if unmet)

1. `payment_gateways` AbhiPay row exists for launch agency
2. `environment=live`, `is_active=true`, merchant ID + secret configured
3. Callback public URL reachable and matches merchant registration
4. Controlled connectivity test already PASS (`GET /orders/by-rrn/OTA-TEST-CONNECTION`)
5. Explicit human approval immediately before any card charge
6. Dedicated QA booking only; small approved amount
7. Do **not** run `payments:abhipay-test` casually — prefer explicit booking start path with recorded IDs
8. Do **not** invent callback payloads against production

## UAT steps (planned)

1. Create/select dedicated QA booking with payable balance > 0
2. Confirm AbhiPay pay button visible only because gateway available
3. Start one payment attempt; capture:
   - `client_transaction_id`
   - `gateway_order_id`
   - redirect URL host
4. Complete payment with merchant-policy-compliant card/instrument
5. Observe callback receipt (server log/audit) without fabricating POST bodies
6. Confirm server-side verification success
7. Assert certification checkpoints below
8. Stop. Refund/void is separate scope.

## Certification checkpoints

```
CALLBACK_RECEIVED=
TRANSACTION_RESOLVED=
SERVER_VERIFY_SUCCESS=
CLIENT_TX_MATCH=
AMOUNT_MATCH=
CURRENCY_MATCH=
STATUS_PAID=
BOOKING_PAYMENT_CREATED_ONCE=
LEDGER_CREATED_ONCE=
NOTIFICATION_CREATED_ONCE=
BOOKING_PAID_ONCE=
NO_AUTO_TICKET=
```

## Controlled connectivity plan (before UAT)

```
METHOD=GET
PATH=/orders/by-rrn/OTA-TEST-CONNECTION
VIA=PaymentGatewaySettingsService::testConnection() / Admin Test connection button
CREATES_ORDER=NO
CREATES_PAYMENT_TRANSACTION=NO
CREATES_BOOKING_PAYMENT=NO
CHARGES_CARD=NO
EXTERNAL_CONNECTIVITY_EXECUTED=NO  # still true until a future approved loop runs it
```

## Current blocker

Production has **zero** AbhiPay gateway rows. Connectivity and live UAT remain blocked until admin configuration + external merchant setup complete.
