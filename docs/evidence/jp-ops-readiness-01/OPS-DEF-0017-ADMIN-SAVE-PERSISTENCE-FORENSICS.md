# OPS-DEF-0017 — Admin Save Persistence Forensics

**Mode:** PRODUCTION-READ-ONLY / HTTP-PERSISTENCE-FORENSICS  
**UTC:** 2026-10-16T07:25Z approx  
**Mutations:** none  
**Secrets:** none printed / none requested

## Exit classification

```
ROOT_CAUSE_CLASSIFICATION=UNKNOWN_REQUIRES_CONTROLLED_RESAVE_TRACE
OPS_DEF_0017_CONFIG_PERSISTENCE_FIX_REQUIRED=NO
CODE_FIX_REQUIRED=NO
OPS_DEF_0017_STATUS=BLOCKED_CONFIGURATION
CONNECTIVITY_TEST_EXECUTED=NO
PAYMENT_GATEWAY_MUTATIONS=0
```

### Contributing observability finding (not AbhiPay code defect)

```
JETPAKISTAN_VHOST_ACCESS_LOG_WRITING=NO
DOMAIN_ACCESS_LOG_PATH=/home/pkjetp/logs/access.log
DOMAIN_ACCESS_LOG_LAST_ENTRY=2026-07-29 (153 bytes)
```

Successful JetPakistan app traffic (e.g. live `GET /up` → 200) does **not** appear in the vhost access log. Therefore access-log absence of `PATCH /admin/settings/payments/abhipay` is **not** proof the request never happened; it is also not proof it did.

## Phase 0 — Health

| Check | Value |
|-------|-------|
| PROTECTED_MAIN_SHA | `78ddf2764eacefdc5e3f4d55ac1b4478a83041a3` |
| PRODUCTION_RUNTIME_SHA | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| BUILD_ID (frontend) | `QClrSvr5W0V2GqllIeLKE` |
| HTTP /up / /login /admin /agent | 200 / 200 / 200 / 302 / 200 |
| FAILED_JOBS | 2 (148 + new 149; both `notifications-transactional`; not retried) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER_RUNNING | NO |

`EXPECTED_SUPERSEDED_WAKE_EXIT=YES` for prior 15m loop wake (intentionally stopped).

## Phase 1 — Canonical app root

```
CANONICAL_WEB_APP_ROOT=/home/pkjetp/jetpk_app
CLI_APP_ROOT=/home/pkjetp/jetpk_app
WEB_AND_CLI_APP_ROOT_MATCH=YES
```

Proof: `/home/pkjetp/public_html/index.php` requires `/home/pkjetp/jetpk_app/bootstrap/app.php`.  
Vhost `jetpakistan.pk` `vhRoot=/home/pkjetp`, `docRoot=$VH_ROOT/public_html/`.  
`www.jetpakistan.pk` aliases same vhost.

## Phase 2 — Routes

```
PAYMENT_SETTINGS_GET_ROUTE_PRESENT=YES
ABHIPAY_UPDATE_ROUTE_PRESENT=YES
ABHIPAY_TEST_ROUTE_PRESENT=YES
UPDATE_HTTP_METHOD=PATCH
UPDATE_URI=admin/settings/payments/abhipay
UPDATE_MIDDLEWARE=web,auth,agency.context,account.type:platform_admin
```

Only **platform_admin** account type may hit these routes.

## Phase 3 — Form action

Deployed Blade has `@csrf`, `@method('PATCH')`, route `admin.settings.payments.abhipay.update`, retains `agency_id`.

```
EXPECTED_FORM_ACTION=https://jetpakistan.pk/admin/settings/payments/abhipay?agency_id=1
```

## Phase 4–5 — Controller / validation

```
CONTROLLER_SAVE_CALL_PRESENT=YES
CONTROLLER_RUNTIME_PARITY=YES (files present under jetpk_app)
VALIDATION_CAN_SAVE_ROW_WITH_EMPTY_CREDENTIALS=YES
```

Merchant fields nullable → missing credentials alone cannot explain row=0 + audit=0.

## Phase 6–7 — Agency / auth

```
AGENCY_1_EXISTS=YES
AGENCY_1_NAME=JetPakistan
AGENCY_1_SLUG=asif-travels
ADMIN_QUERY_PARAM_RESOLUTION=platform_admin + ?agency_id=1 → Agency::findOrFail(1)
AGENCY_1_UPDATE_AUTHORIZATION=YES (Gate for platform admin id=1)
PLATFORM_ADMIN_AUTHENTICATED=N/A_OPERATOR_SESSION_NOT_CAPTURED
```

## Phase 8–9 — DB identity / config cache

```
CLI_DB_DATABASE=jetpk_prod
WEB_DB_DATABASE=jetpk_prod (same Laravel root / .env / no config cache)
WEB_CLI_DB_MATCH=YES
CONFIG_CACHED=NO
CACHED_DB_DATABASE=N/A
ENV_DB_DATABASE=jetpk_prod
ACTIVE_RUNTIME_DB_DATABASE=jetpk_prod
DB_HOST_CLASSIFICATION=localhost
```

No web/CLI database mismatch found.

## Phase 10 — Access log forensics

| Source | Result |
|--------|--------|
| `/home/pkjetp/logs/access.log` (vhost configured) | Stale since **2026-07-29**; not writing |
| `/usr/local/lsws/logs/access.log` | Probe/noise 404s; **0** hits ever for `settings/payments` / `payments/abhipay`; does not record successful `/up` 200 either |

```
SAVE_REQUEST_SEEN_IN_ACCESS_LOG=UNKNOWN_ACCESS_LOGGING_BROKEN
SAVE_REQUEST_HTTP_STATUS=
SAVE_REQUEST_TIMESTAMP=
```

## Phase 11 — Laravel log

Tail/search of recent `laravel.log` for abhipay/payment_gateway/CSRF/validation: **no matching errors** in sampled window.

```
LARAVEL_ERROR_FOUND=NO
ERROR_CLASS=
SAFE_ERROR_SUMMARY=no AbhiPay/CSRF/validation exceptions found in recent log sample
```

## Phase 12–13 — Audit / gateway rows

```
ABHIPAY_AUDIT_COUNT=0
ABHIPAY_GATEWAY_ROW_COUNT=0
PAYMENT_GATEWAYS_TOTAL=0
ROW_FOUND_IN_WRONG_DB=NO (only jetpk_prod inspected as configured app DB)
RECENT_2D_PAYMENT_SETTINGS_AUDITS=0
```

## Phase 14–15 — Host / proxy

```
CANONICAL_ADMIN_HOST=https://jetpakistan.pk
POSSIBLE_LEGACY_ADMIN_HOSTS=www.jetpakistan.pk (alias same app); iframe-pilot.jetpakistan.pk (different docRoot); ota.iati.pk (forbidden unrelated)
CLAIMED_SAVE_HOST_KNOWN=NO (operator claim only)
GET_AND_PATCH_SAME_UPSTREAM=YES_FOR_LARAVEL_ADMIN
```

Rewrite note: `/admin/dashboard/*` → Next dashboard; **non-dashboard `/admin/*` stays Laravel**. AbhiPay form is Laravel Blade at `/admin/settings/payments` (not under `/admin/dashboard`).

Next dashboard settings modules (**general / security / notifications / integrations**) have **no AbhiPay form**. If the operator used Next dashboard “Settings” only, no `saveAbhiPay()` would run.

## Phase 16–18 — CSRF / save order

Middleware includes `web` (CSRF) + auth + platform_admin.  
Service: `gateway->save()` then `AuditLog::create` — **no** DB transaction wrapper.

```
SAVE_ORDER=gateway_save_then_audit
GATEWAY_SAVE_BEFORE_AUDIT=YES
```

Therefore row=0 + audit=0 ⇒ request never completed `PaymentGateway::save()` successfully on `jetpk_prod` (or never reached controller), not “audit failed after save”.

## Phase 19–20 — Encryption / schema / grants

```
APP_KEY_PRESENT=YES
ENCRYPTION_ROUNDTRIP=PASS
PAYMENT_GATEWAYS_TABLE_PRESENT=YES
SCHEMA_MATCH=YES
DB_USER_INSERT_PRIVILEGE_METADATA=YES_METADATA
```

## Phase 21 — UI claim

```
SAVE_SUCCESS_UI_NOT_CONFIRMED=YES
```

Operator reported save; no durable production row/audit/access proof.

## Root cause decision

**Primary:** `UNKNOWN_REQUIRES_CONTROLLED_RESAVE_TRACE`

Not enough durable evidence to pick CSRF vs never-submitted vs wrong UI (Next dashboard) vs silent client abort. Code/runtime DB/encryption/schema look healthy. Domain access logging is broken, which blocks HTTP-status forensics.

**Code fix for AbhiPay save path:** **NO** (no defect proven in controller/service/form/validation).

**Ops follow-up (separate):** restore writing of `/home/pkjetp/logs/access.log` (permissions/ownership/LiteSpeed logger) so admin mutations are auditable.

## Controlled resave plan (DO NOT EXECUTE in this loop)

1. Use **only** canonical URL: `https://jetpakistan.pk/admin/settings/payments?agency_id=1` (Blade), **not** `/admin/dashboard/settings/*`.
2. Sign in as **platform_admin**.
3. Optionally fix/enable domain access logging first (ops).
4. Before Save: confirm page shows AbhiPay form + read-only callback URL.
5. Save once; require visible flash: “AbhiPay settings saved.”
6. Immediately on server:  
   `php artisan payments:abhipay-status --agency_id=1` → expect Active=yes, Environment=live, Secret configured=yes, Checkout ready=yes (masked).  
   Confirm `payment_gateways` count≥1 and audit `payment_gateway.abhipay.updated`.
7. Only then run **Test connection** (`GET /orders/by-rrn/OTA-TEST-CONNECTION`) in a separate approved loop.
8. If Save fails: capture HTTP status from browser network panel + Laravel log before re-entering secrets.

## Mutation proof

```
CONNECTIVITY_TEST_EXECUTED=NO
ABHIPAY_ORDERS_CREATED=0
PAYMENT_TRANSACTIONS_CREATED=0
BOOKING_PAYMENTS_CREATED=0
CARD_CHARGES=0
PAYMENT_GATEWAY_MUTATIONS=0
LEDGER_MUTATIONS=0
WALLET_MUTATIONS=0
SUPPLIER_MUTATIONS=0
```
