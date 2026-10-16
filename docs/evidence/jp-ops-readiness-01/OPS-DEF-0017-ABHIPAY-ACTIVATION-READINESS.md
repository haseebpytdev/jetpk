# OPS-DEF-0017 — AbhiPay Activation Readiness (READ-ONLY)

**Audit UTC:** 2026-10-15T20:45Z  
**Mode:** PRODUCTION-READ-ONLY / NO-CHARGE / NO-LIVE-PAYMENT-MUTATION  
**Defect:** OPS-DEF-0017 (P1)

## Exit classification

```
ABHIPAY_ACTIVATION_CLASSIFICATION=BLOCKED_NO_GATEWAY_ROW
OPS_DEF_0017_STATUS=BLOCKED_CONFIGURATION
OPS_DEF_0018_PAYMENT_BLOCKER=YES
EXTERNAL_CONNECTIVITY_EXECUTED=NO
LIVE_PAYMENT_EXECUTED=NO
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
PAYMENT_MUTATIONS=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
```

## Phase 0 — Production health

| Check | Value |
|-------|-------|
| PROTECTED_MAIN_SHA | `a9676dc26489d99322e61e29ddf4b5e41e3a38e4` (matches `jetpk/main`) |
| PRODUCTION_RUNTIME_SHA | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| FRONTEND_BUILD_ID | `QClrSvr5W0V2GqllIeLKE` (matches prompt CURRENT_BUILD_ID) |
| DASHBOARD_BUILD_ID | `7-iZf4RbaJmGpuDjO1tsh` (distinct from frontend) |
| HTTP /up / /login /admin /agent | 200 / 200 / 200 / 302 / 200 |
| FAILED_JOBS | 1 (id=148 QA SMTP bounce) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER_RUNNING | NO |
| DISK_USED_PERCENT | 48 |

## Phase 1 — Gateway row inventory

```
ABHIPAY_GATEWAY_ROWS=0
PLATFORM_ABHIPAY_ROW_EXISTS=NO
AGENCY_SCOPED_ABHIPAY_ROWS=0
ACTIVE_LIVE_ROWS=0
ACTIVE_TEST_ROWS=0
TOTAL_PAYMENT_GATEWAYS=0
```

**Important:** `php artisan payments:abhipay-status` prints an unsaved `firstOrNew` default (platform / inactive / test / credentials not set / checkout ready no). That is **not** a persisted DB row.

## Phase 2 — Artisan status (safe)

| Field | Value |
|-------|-------|
| ACTIVE | no |
| ENVIRONMENT | test |
| MERCHANT_ID_MASKED | not set |
| SECRET_CONFIGURED | no |
| BASE_URL | https://api.abhipay.com.pk/api/v3 |
| CHECKOUT_READY | no |

Agency-scoped status not separately required: zero AbhiPay rows for all agencies.

## Phase 3 — Effective checkout resolution

`PaymentTransactionService::activeAbhiPayGateway()` requires `is_active=true` + configured credentials. Precedence: agency-specific active row, else platform (`agency_id` null).

| Agency ID | RESOLVED_GATEWAY_ID | CHECKOUT_AVAILABLE |
|-----------|---------------------|--------------------|
| 1–5 | NONE | NO |
| platform null | NONE | NO |

## Phase 4 — Live vs test

```
CLASSIFICATION=NO_GATEWAY_ROW
```

No live or test configured gateway exists. OPS-DEF-0017 cannot close.

## Phases 5–9 — Callback / URLs / base

| Check | Result |
|-------|--------|
| CALLBACK_ROUTE_PRESENT | YES (`ANY payments/abhipay/callback`) |
| SUCCESS/CANCEL/DECLINE routes | YES |
| CALLBACK_PUBLIC_URL | https://jetpakistan.pk/payments/abhipay/callback |
| CALLBACK_PUBLIC_REACHABILITY | PASS (GET empty → 302 Location `/payment/decline`; no auth wall; LiteSpeed; throttle headers present; not CF challenge) |
| SUCCESS/CANCEL/DECLINE GET | 200 |
| CALLBACK_URL_MATCH | N/A_NO_STORED_ROW (route URL matches expected host/path) |
| SUCCESS/CANCEL/DECLINE_URL_MATCH | N/A_NO_STORED_ROW (route URLs match expected) |
| ABHIPAY_BASE_URL_VALID | YES (default host `api.abhipay.com.pk`, HTTPS) |

Empty GET on callback is non-mutating relative to payment data (`payment_transactions=0`); controller cannot resolve a transaction and redirects to decline.

## Phase 6 — Callback security model (source)

| Control | Evidence |
|---------|----------|
| CALLBACK_CSRF_MODEL | CSRF exempt path `payments/abhipay/callback` in `bootstrap/app.php` |
| CALLBACK_THROTTLE | `throttle:abhipay-payment-callback` = 60/min/IP (`AppServiceProvider`) |
| SERVER_SIDE_VERIFY_REQUIRED | YES — `processCallback` always calls `verifyPayment` |
| CLIENT_TX_VERIFY | YES — mismatch → VerificationFailed |
| AMOUNT_VERIFY | YES |
| CURRENCY_VERIFY | YES |
| PAID_DOWNGRADE_BLOCKED | YES — `isPaid()` short-circuit + `lockForUpdate` |

Callback body is never treated as proof of payment.

## Phase 10 — External connectivity

```
SAFE_CONNECTIVITY_CHECK_AVAILABLE=NO
EXTERNAL_CONNECTIVITY_EXECUTED=NO
```

No credentials → do not call `testConnection()` / `GET /orders/by-rrn/OTA-TEST-CONNECTION`.

## Phase 11 — Payment data

| Metric | Count |
|--------|------:|
| PAYMENT_TRANSACTIONS_TOTAL | 0 |
| ABHIPAY_TRANSACTIONS_TOTAL | 0 |
| ABHIPAY_LIVE / TEST | 0 / 0 |
| BOOKING_PAYMENTS_TOTAL | 3 (all `bank_transfer`) |
| VERIFIED_ABHIPAY_BOOKING_PAYMENTS | 0 |
| FAILED / PENDING AbhiPay tx | 0 / 0 |

## Phase 12 — Checkout UI readiness

`PublicAbhiPayCheckoutPresenter`:

- `show_pay_button` / `show_review_option` require `gateway_available`
- With no active configured gateway → online option hidden
- `ticketing_note` = "Ticketing will happen after payment verification."

**UI claim note:** With OPS-DEF-0016 launch scope (ticketing external/manual/disabled in product), that note can over-imply automatic issuance. Tracked as **OPS-DEF-0025** (P3). No UI redesign in this audit.

## Phase 13 — Payment → booking flow

Verified AbhiPay Paid → `recordVerifiedGatewayPayment` (explicitly **without auto-ticketing**) → recalculate booking payment status → audit/note. No supplier ticketing call in settlement path.

```
PAYMENT_VERIFICATION_AUTO_TICKETING=NO
PAYMENT_VERIFICATION_SUPPLIER_CALL=NO
```

## Phase 14 — Observability

| Area | Status |
|------|--------|
| CALLBACK_LOGGING | YES (warnings + audit action `booking.payment_abhipay_verified`) |
| PAYLOAD_REDACTION | YES (`PaymentGatewayPayloadRedactor`) |
| PAYMENT_AUDIT | YES (`AuditLog`) |
| ERROR_VISIBILITY | YES (verification failure statuses + logs) |

## Phase 15 — OPS-DEF-0013 runtime invariant

```
HAS_BOOKING_PAYMENTS_PTX_COL=YES
BOOKING_PAYMENTS_PTX_UNIQUE=YES
CLIENT_TX_UNIQUE=YES
GATEWAY_ORDER_COMPOSITE_UNIQUE=YES
OPS_DEF_0013_RUNTIME_INVARIANT=PASS
```

## Phase 16–17 — Admin + credentials

| Check | Result |
|-------|--------|
| ADMIN_GATEWAY_SETTINGS_AVAILABLE | YES (`admin/settings/payments`) |
| ADMIN_CAN_CONFIGURE_LIVE | YES (environment select test/live + active + merchant + secret + base URL) |
| ADMIN_TEST_CONNECTION_ACTION_PRESENT | YES (POST test; **not executed**) |
| Callback/success/cancel/decline | Presented read-only from named routes |
| CREDENTIALS_ENCRYPTED_AT_REST | YES (model casts) |
| SECRET_HIDDEN | YES (`#[Hidden]`) |
| STATUS_COMMAND_SECRET_SAFE | YES (masked / never prints secret) |

Admin save path creates **agency-scoped** rows (`agency_id` required). Platform null row is resolution fallback only.

## Phase 18 — Blockers

### Primary

| Field | Value |
|-------|-------|
| DEPENDENCY | Persist AbhiPay `payment_gateways` row for launch agency |
| OWNER | Platform admin / agency admin |
| ACTION | Admin → Payment methods → enter live merchant ID + secret, set environment=live, activate |
| MUTATION_REQUIRED | YES (admin settings save — out of scope for this audit) |
| EXTERNAL_VENDOR_REQUIRED | YES (AbhiPay merchant account + dashboard callback registration) |

### Secondary (after row exists)

1. Controlled connectivity test via existing Test connection (`GET /orders/by-rrn/OTA-TEST-CONNECTION`)
2. Confirm merchant-dashboard callback URL registration (`UNVERIFIED_EXTERNAL` today)
3. One controlled live payment UAT (separate approval)

## Phase 22 — Merchant dashboard

```
MERCHANT_DASHBOARD_CALLBACK_REGISTERED=UNVERIFIED_EXTERNAL
```

Cannot be proven from OTA DB/routes alone.

## Phase 23–24 — Status vs OPS-DEF-0018

- Not READY_FOR_CONTROLLED_CONNECTIVITY_TEST (credentials missing / no row)
- Not READY_FOR_CONTROLLED_LIVE_UAT
- Online AbhiPay remains a blocker for OJ-B2C-001 / OJ-B2B-OWN-001 full payment certification
- Manual payment methods remain available in admin copy (bank transfer / cash / etc.); this audit does **not** treat manual-only as an approved substitute for AbhiPay go-live closure unless separate business evidence says so

## Phase 25 — Failed job

```
FAILED_JOB_148_EXISTS=YES
FAILED_JOB_148_DISPLAY=App\Jobs\Notifications\DeliverQueuedOperationalMail
FAILED_JOB_148_SMTPISH=YES
FAILED_JOB_PAYMENT_RELATED=NO
```

Do not retry/delete.

## Next required action

1. Admin configures agency AbhiPay **live** credentials + `is_active=true` (creates first gateway row)
2. Register callback `https://jetpakistan.pk/payments/abhipay/callback` in AbhiPay merchant dashboard
3. Run controlled connectivity test only (no order / no charge)
4. Then one approved controlled live-payment UAT before closing OPS-DEF-0017
