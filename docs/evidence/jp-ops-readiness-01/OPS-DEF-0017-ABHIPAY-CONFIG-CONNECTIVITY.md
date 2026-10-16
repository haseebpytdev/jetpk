# OPS-DEF-0017 — AbhiPay Config + Connectivity

**Updated UTC:** 2026-10-16T07:10Z  
**Mode:** CONTROLLED-PRODUCTION-CONFIGURATION / SECRET-SAFE / CONNECTIVITY-ONLY  
**Continuation:** Operator reported manual credential entry for agency_id=1

## Exit classification (this continuation)

```
ABHIPAY_ACTIVATION_CLASSIFICATION=BLOCKED_CONFIGURATION
OPS_DEF_0017_STATUS=BLOCKED_CONFIGURATION
ABHIPAY_LIVE_CREDENTIALS_AVAILABLE_TO_OPERATOR=CLAIMED_BY_OPERATOR
PRODUCTION_PERSISTENCE_VERIFIED=NO
CONNECTIVITY_TEST_EXECUTED=NO
ABHIPAY_ROW_CREATED=NO
PAYMENT_GATEWAY_CONFIG_ROWS_CREATED=0
ABHIPAY_ORDERS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
CARD_CHARGES=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
```

## Phase 0 / authority

| Check | Value |
|-------|-------|
| PROTECTED_MAIN_SHA (reported) | `d10f679b0ea6c14faca85c6020e85e14b0865705` |
| PRODUCTION_RUNTIME_SHA | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| FRONTEND_BUILD_ID | `QClrSvr5W0V2GqllIeLKE` |
| DB | `jetpk_prod` |
| APP_ENV | production |
| APP_URL | https://jetpakistan.pk |

## Target agency (unchanged)

```
TARGET_ABHIPAY_AGENCY_ID=1
TARGET_ABHIPAY_AGENCY_NAME=JetPakistan
TARGET_AGENCY_SELECTION_BASIS=ota.default_agency_slug=asif-travels + B2C bookings on agency 1
```

Admin page: `https://jetpakistan.pk/admin/settings/payments?agency_id=1`

## Post-claim production verification (READ-ONLY + artisan)

```
AGENCY_1_ABHIPAY_ROW_COUNT=0
ALL_ABHIPAY_ROWS=0
PLATFORM_ABHIPAY_ROWS=0
PAYMENT_GATEWAYS_TOTAL=0
AUDIT_ABHIPAY_ROWS=0
RECENT_6H_AUDITS=0
```

`php artisan payments:abhipay-status --agency_id=1` (unsaved firstOrNew defaults only):

| Field | Value |
|-------|-------|
| Agency ID | 1 |
| Active | no |
| Environment | test |
| Merchant ID | not set |
| Secret configured | no |
| Base URL | https://api.abhipay.com.pk/api/v3 |
| Checkout ready | no |

```
ABHIPAY_ROW_EXISTS=NO
ABHIPAY_ACTIVE=NO
ABHIPAY_ENVIRONMENT=(no persisted row; artisan default=test)
ABHIPAY_MERCHANT_ID_CONFIGURED=NO
ABHIPAY_SECRET_CONFIGURED=NO
ABHIPAY_CHECKOUT_READY=NO
CALLBACK_URL_MATCH=N/A_NO_STORED_ROW
SUCCESS_URL_MATCH=N/A_NO_STORED_ROW
CANCEL_URL_MATCH=N/A_NO_STORED_ROW
DECLINE_URL_MATCH=N/A_NO_STORED_ROW
CALLBACK_PUBLIC_REACHABILITY=PASS
```

## Connectivity

**Not executed.** Gates require live+active+configured+URL parity. All failed.

```
CONNECTIVITY_TEST_EXECUTED=NO
CONNECTIVITY_HTTP_STATUS=
CONNECTIVITY_RESULT=
CONNECTIVITY_SAFE_MESSAGE=skipped: no payment_gateways abhipay row on jetpk_prod
```

`payments:abhipay-test` was **not** run. `testConnection()` was **not** called.

## Checkout / UI

```
TARGET_AGENCY_CHECKOUT_GATEWAY_RESOLVES=NO
PAY_ONLINE_UI_READY=NO
MERCHANT_DASHBOARD_CALLBACK_REGISTERED=UNVERIFIED_EXTERNAL
```

## Interpretation

Operator reported credentials entered via Admin Payment Settings for agency 1, but **production `jetpk_prod.payment_gateways` still has zero rows** and **zero** `payment_gateway.abhipay.updated` audit events. Therefore credentials are **not** confirmed persisted on the canonical production application database.

Likely causes (investigation, not accusation):

1. Save submitted against a non-production host / preview / cached wrong context
2. Form submit failed before `saveAbhiPay()` (auth/CSRF/validation) without operator noticing
3. Wrong agency context (not `?agency_id=1`) and still no row anywhere
4. UI viewed without successful PATCH to `admin.settings.payments.abhipay.update`

No secrets were printed. No payment mutations occurred.

## Next required action

1. Platform admin open `https://jetpakistan.pk/admin/settings/payments?agency_id=1` on **canonical** production.
2. Confirm flash success after Save (not only field fill).
3. Immediately re-run `php artisan payments:abhipay-status --agency_id=1` on production host and confirm Checkout ready=yes / Active=yes / Environment=live / Secret configured=yes (masked).
4. Resume this loop for **Test connection only** once row is proven in DB.
5. Do not start live payment UAT until connectivity PASS.

## Mutation proof

```
PAYMENT_GATEWAY_CONFIG_ROWS_CREATED=0
PAYMENT_GATEWAY_CONFIG_ROWS_UPDATED=0
PAYMENT_GATEWAY_AUDIT_ROWS_CREATED=0
ABHIPAY_ORDERS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
CARD_CHARGES=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
OPS_DEF_0013_RUNTIME_INVARIANT=PASS
```
