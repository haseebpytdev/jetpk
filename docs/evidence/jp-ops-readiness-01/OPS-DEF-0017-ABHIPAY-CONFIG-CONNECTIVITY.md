# OPS-DEF-0017 — AbhiPay Config + Connectivity (STOPPED at credentials gate)

**Audit UTC:** 2026-10-16T07:00Z (approx)  
**Mode:** CONTROLLED-PRODUCTION-CONFIGURATION / SECRET-SAFE / CONNECTIVITY-ONLY  
**Result:** STOPPED before configuration — credentials not available to operator

## Exit classification

```
ABHIPAY_ACTIVATION_CLASSIFICATION=BLOCKED_WAITING_FOR_ABHIPAY_CREDENTIALS
OPS_DEF_0017_STATUS=BLOCKED_WAITING_FOR_ABHIPAY_CREDENTIALS
TARGET_AGENCY_AMBIGUOUS=NO
ABHIPAY_LIVE_CREDENTIALS_AVAILABLE_TO_OPERATOR=NO
CONNECTIVITY_TEST_EXECUTED=NO
ABHIPAY_ROW_CREATED=NO
PAYMENT_GATEWAY_CONFIG_ROWS_CREATED=0
PAYMENT_GATEWAY_CONFIG_ROWS_UPDATED=0
ABHIPAY_ORDERS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
CARD_CHARGES=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
```

## Phase 0 — Health

| Check | Value |
|-------|-------|
| PROTECTED_MAIN_SHA | `a4c36fdd36ec3dfc8fadd05b8726127bab84ba9a` (`jetpk/main`, PR #86 merged) |
| PRODUCTION_RUNTIME_SHA | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| BUILD_ID_PRE (frontend) | `QClrSvr5W0V2GqllIeLKE` |
| DASHBOARD_BUILD_ID | `7-iZf4RbaJmGpuDjO1tsh` |
| HTTP /up / /login /admin /agent | 200 / 200 / 200 / 302 / 200 |
| FAILED_JOBS_PRE | 1 (id=148 QA SMTP; not payment-related; not retried) |
| PENDING_DEFAULT_PRE | 0 |
| DEFAULT_WORKER_RUNNING_PRE | NO |
| CALLBACK_GET | 302 (reachability PASS) |

Evidence-only main SHA was **not** deployed (runtime correctly remains `922f1577`).

## Phase 1 — Target agency selection

| Agency ID | Display name | Slug | Users | Bookings | Recent 30d | Agents | B2C null-agent bookings |
|-----------|--------------|------|------:|---------:|-----------:|-------:|------------------------:|
| **1** | **JetPakistan** | **asif-travels** | 8 | **38** | 4 | 1 | **38** |
| 2 | Easy Ticket | easy-ticket | 1 | 0 | 0 | 1 | 0 |
| 3 | JP-DASH-03 QA Agency | jp-dash-03-qa-agency | 1 | 0 | 0 | 1 | 0 |
| 4 | JetPakistan Production QA | jetpk-production-qa | 12 | 1 | 1 | 1 | 0 |
| 5 | JetPakistan Production QA B | jetpk-production-qa-b | 1 | 0 | 0 | 1 | 0 |

```
TARGET_ABHIPAY_AGENCY_ID=1
TARGET_ABHIPAY_AGENCY_NAME=JetPakistan
TARGET_AGENCY_SELECTION_BASIS=config ota.default_agency_slug=asif-travels (agency 1) + all public/B2C bookings (agent_id NULL) owned by agency_id=1 (38/38) + primary production booking volume; agencies 3–5 are QA fixtures; agency 2 has zero bookings
TARGET_AGENCY_AMBIGUOUS=NO
```

Do **not** create a platform/global (`agency_id=null`) AbhiPay row. Admin save path is agency-scoped via `AgencyPaymentSettingsController` → `PaymentGatewaySettingsService::saveAbhiPay($agency, ...)`.

## Phase 2 — Pre-configuration baseline

```
EXISTING_ABHIPAY_ROW=NO
PAYMENT_GATEWAY_ROWS_PRE=0
ABHIPAY_ROWS_PRE=0
CHECKOUT_AVAILABLE_PRE=NO
ABHIPAY_TRANSACTIONS_PRE=0
PAYMENT_TRANSACTIONS_PRE=0
BOOKING_PAYMENTS_PRE=3
```

## Phase 3 — Admin route / authorization

| Item | Value |
|------|-------|
| Index | `GET /admin/settings/payments` → `admin.settings.payments.index` |
| Update | `PATCH /admin/settings/payments/abhipay` → `admin.settings.payments.abhipay.update` |
| Test connection | `POST /admin/settings/payments/abhipay/test` → `admin.settings.payments.abhipay.test` |
| Auth | `Gate::authorize('update', $agency)`; platform admin may pass `?agency_id=` |
| Preferred target URL | `https://jetpakistan.pk/admin/settings/payments?agency_id=1` |

Fields to enter (human only; never commit/log):

- Active = on
- Environment = **Live**
- Merchant ID
- Merchant secret key
- Base URL = `https://api.abhipay.com.pk/api/v3` (default)
- Callback/success/cancel/decline shown read-only from named routes

## Phase 5 — Human secret entry gate (STOP)

```
ABHIPAY_LIVE_CREDENTIALS_AVAILABLE_TO_OPERATOR=NO
```

No production Merchant ID / Merchant Secret were provided in this loop prompt, agent stores, or secure form session. Credentials were **not invented**. Configuration and connectivity were **not executed**.

## Phases 6–15 — Not executed

| Gate | Status |
|------|--------|
| Save via `saveAbhiPay()` | NOT_RUN |
| Connectivity `GET /orders/by-rrn/OTA-TEST-CONNECTION` | NOT_RUN |
| URL parity after save | N/A |
| Merchant dashboard registration | UNVERIFIED_EXTERNAL |
| Checkout resolution post-config | N/A |
| Live UAT | FORBIDDEN this loop |

## Next required action

1. Operator obtains production AbhiPay **Merchant ID** + **Merchant Secret**.
2. Sign in as platform admin → open `https://jetpakistan.pk/admin/settings/payments?agency_id=1`.
3. Enter credentials; set Environment=Live; Active=on; Save.
4. Copy callback `https://jetpakistan.pk/payments/abhipay/callback` into AbhiPay merchant dashboard.
5. Re-run this loop (or approved continuation) to execute **Test connection** only, then stop before live UAT.

## Mutation proof (this loop)

```
PAYMENT_GATEWAY_CONFIG_ROWS_CREATED=0
PAYMENT_GATEWAY_CONFIG_ROWS_UPDATED=0
PAYMENT_GATEWAY_AUDIT_ROWS_CREATED=0
ABHIPAY_ORDERS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
CARD_CHARGES=0
BOOKING_PAYMENT_STATUS_MUTATIONS=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
```
