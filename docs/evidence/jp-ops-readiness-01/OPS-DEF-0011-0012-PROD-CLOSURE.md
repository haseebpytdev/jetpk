# OPS-DEF-0011 / OPS-DEF-0012 — Production deploy + verification closure

**Date (UTC):** 2026-10-16  
**Task:** `JP-OPS-READINESS-01-GUEST-TOKEN-PROD-DEPLOY`  
**PR:** #98 (squash-merged to protected main)  
**Reviewed head:** `ac21e6c314978ddf32d919544fdcef0fe4d4285c`  
**Protected main (post-merge):** `9a3f2ba22ecf8466aa4cdf056739a6c3a4ff693e`  
**Release guards:** PASS (`38970137624`)

## Merge proof

| Check | Result |
|-------|--------|
| `git merge-base --is-ancestor ac21e6c… jetpk/main` | FAIL (expected for squash merge) |
| Runtime blob parity vs reviewed head (key guest files) | MATCH |
| GitHub PR #98 merged into protected main tip | YES (`9a3f2ba2`) |
| `PR_98_MERGED` | YES |

## Production base (unchanged full-tree SHA)

| Field | Value |
|-------|-------|
| `PRODUCTION_RUNTIME_BASE_SHA` | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| `PREVIOUS_PATCH_MARKER` | `ops-def-0005-0008@79da5477` |
| `NEW_PRODUCTION_PATCH_MARKER` | `922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2` |
| `CANONICAL_APP_ROOT` | `/home/pkjetp/jetpk_app` |
| `BACKUP_PATH` | `/home/pkjetp/backups/ops-def-0011-0012-pre-deploy-20261016T220356Z` |
| `PREVIOUS_FRONTEND_BUILD_ID` | `QClrSvr5W0V2GqllIeLKE` |
| `BUILD_ID_PRE` (dashboard, user reference) | `7-iZf4RbaJmGpuDjO1tsh` |
| `BUILD_ID_POST` (public frontend) | `HNDspC9CoB4Bat_x0DwZ6` |
| Dashboard BUILD_ID (unchanged) | `7-iZf4RbaJmGpuDjO1tsh` |

Scoped file/build deploy only — production `.jetpk-runtime-sha` remains `922f1577…` (not rewritten to main).

## Pre-deploy health

| Probe | Result |
|-------|--------|
| HTTP `/up` | 200 |
| HTTP `/` | 200 |
| HTTP `/login` | 200 |
| HTTP `/lookup-booking` | 200 |
| HTTP `/agent` | 200 |
| HTTP `/admin` | 302 |
| `PENDING_DEFAULT_PRE` | 0 |
| Failed jobs | not drained / not touched |

## Runtime files deployed (from `9a3f2ba2`)

**LARAVEL_RUNTIME / BLADE_RUNTIME / ROUTES (16):**

- `app/Http/Controllers/Frontend/BookingCheckoutPromoController.php`
- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Http/Controllers/Frontend/GuestBookingCancellationController.php`
- `app/Http/Controllers/Frontend/GuestBookingLookupController.php`
- `app/Http/Controllers/Payments/AbhiPayPaymentController.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Services/Customer/GuestBookingAccessService.php`
- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/GuestBooking/GuestBookingDetailPresenter.php`
- `routes/web.php`
- `resources/views/components/bookings/detail-documents-card.blade.php`
- `resources/views/components/bookings/detail-payment-card.blade.php`
- `resources/views/components/bookings/promo-code-card.blade.php`
- `resources/views/frontend/booking/guest-show.blade.php`
- `resources/views/frontend/booking/partials/confirmation-body.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/booking/partials/confirmation-body.blade.php`

**NEXT_RUNTIME sources (5) + PUBLIC_ONLY rebuild:**

- `frontend/app/(public)/guest/bookings/[booking]/page.tsx`
- `frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx`
- `frontend/features/guest-booking/components/GuestBookingDetailPage.tsx`
- `frontend/features/guest-booking/services/guest-booking-api.ts`
- `frontend/features/standard-booking/lookup/guest-redirect.ts`

Not deployed: `tests/`, `docs/evidence/`, `summary.md`, `vendor/`, `node_modules/`, `.env`, `storage/`.

| Gate | Result |
|------|--------|
| `PHP_RUNTIME_PARITY` | PASS (16/16 SHA256) |
| `UNEXPECTED_RUNTIME_FILES_DEPLOYED` | 0 |
| `PHP_SYNTAX_FAIL` | 0 |
| `LARAVEL_BOOTSTRAP` | PASS |
| `FRONTEND_SOURCE_PARITY` | PASS (5/5) |
| `FRONTEND_BUILD` | PASS (`PUBLIC_ONLY=1` `/tmp/jetpk-next-build.sh`) |
| Dashboard PID unchanged | YES |

## Route verification

| Check | Result |
|-------|--------|
| `CLEAN_GUEST_ROUTES_PRESENT` | YES (show, documents download, payment-proof, promo apply/remove, abhipay start, cancellations) |
| `LEGACY_EXCHANGE_ROUTE_PRESENT` | YES (`GET …/access/{token}` → `guest.bookings.exchange`) |
| `LEGACY_COMPAT_MUTATION_ROUTES_PRESENT` | YES |

## Token rotation / lookup / exchange (source+runtime, zero live rotation)

| Check | Result |
|-------|--------|
| `PROD_ROTATION_CODE_PARITY` | PASS |
| `TOKEN_ROTATION_TRANSACTION_PRESENT` | YES |
| `PRIOR_TOKEN_EXPIRY_PRESENT` | YES |
| `TOKEN_HASH_ONLY_STORAGE_PRESENT` | YES (`hash('sha256', $raw)`) |
| `LOOKUP_REDIRECT_TOKEN_FREE` | YES |
| `SESSION_BIND_IMPLEMENTED` | YES |
| `RAW_TOKEN_IN_LOOKUP_REDIRECT` | NO |
| `LEGACY_EXCHANGE_FINAL_LOCATION_TOKEN_FREE` | YES |

## No token leak (primary surfaces)

| Check | Result |
|-------|--------|
| `RAW_TOKEN_IN_GUEST_JSON_URLS` | NO |
| `RAW_TOKEN_IN_DOCUMENT_URLS` | NO |
| `RAW_TOKEN_IN_MUTATION_URLS` | NO |
| `RAW_TOKEN_IN_BLADE_PRIMARY_URLS` | NO |
| `RAW_TOKEN_IN_NEXT_PRIMARY_URLS` | NO |

## Session / mutation authorization (safe automated; no business mutations)

| Check | Result |
|-------|--------|
| `GUEST_SESSION_OWN_BOOKING` | PASS (session key scoped to booking id) |
| `GUEST_SESSION_CROSS_BOOKING` | DENY |
| `GUEST_MISSING_SESSION` | DENY |
| `GUEST_INVALID_TOKEN` | DENY |
| `PAYMENT_PROOF_SESSION_AUTH` | PASS (`authorizeGuestAccess` before logic) |
| `PROMO_SESSION_AUTH` | PASS |
| `CANCELLATION_SESSION_AUTH` | PASS |
| `ABHIPAY_SESSION_AUTH` | PASS |
| Actual payment/promo/cancel/AbhiPay mutations | 0 |

## AbhiPay

| Check | Result |
|-------|--------|
| `OPS_DEF_0017` | `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH` |
| `ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH` | NO |
| `ABHIPAY_CONFIG_ACTIVATED` | NO |
| Connectivity / orders / card charges | 0 |
| `ABHIPAY_STATE_UNCHANGED` | YES |

## Security headers (accurate by surface)

Helper in `GuestBookingLookupController::withGuestAccessHeaders` sets `Referrer-Policy=no-referrer`, `Cache-Control=private, no-store…`, `Pragma=no-cache`, `X-Robots-Tag=noindex, nofollow`.

| Surface | Observed at public edge (unauth / Next shell) |
|---------|-----------------------------------------------|
| LOOKUP (`/lookup-booking`) | `Cache-Control: private, no-cache, no-store…` (Next); helper N/A on page shell |
| EXCHANGE (Next path) | `Cache-Control: private, no-cache, no-store…` |
| DETAIL (Next unauth) | `Cache-Control: no-cache, private`; `Referrer-Policy: strict-origin-when-cross-origin` (SecurityHeaders default; helper not executed on Next 404 shell) |
| DOCUMENT (unauth) | same as DETAIL-class deny |
| PAYMENT_PROOF (unauth POST) | deny path; do not overstate helper coverage |

## Post-deploy health

| Probe | Result |
|-------|--------|
| `/up` `/` `/login` `/lookup-booking` `/agent` | 200 |
| `/admin` | 302 |
| `/guest/bookings/<nonexistent>` | 404 (handled) |
| `NEXT_GUEST_ROUTE_HANDLED` | YES |

## Zero-mutation assertions

All required counters = 0 (no intentional guest token create/rotate; no booking/payment/promo/cancel/AbhiPay/wallet/ledger/supplier/PNR/ticket mutations).

## Defect status

| Defect | Status |
|--------|--------|
| `OPS_DEF_0012_STATUS` | **CLOSED** |
| `OPS_DEF_0011_STATUS` | **MITIGATED_PHASE1_DEPLOYED_PHASE2_PENDING** |
| `OPS_DEF_0011_PHASE2_REQUIRED` | YES |

### Residual (0011 Phase-2)

- `LEGACY_PATH_TOKEN_EXPOSURE=POSSIBLE`
- `LEGACY_QUERY_TOKEN_COMPAT=PRESENT`
- `LEGACY_MUTATION_TOKEN_ROUTES=PRESENT`
- `ACCESS_LOG_REDACTION_PENDING=YES`

## Rollback

| Field | Value |
|-------|-------|
| `ROLLBACK_READY` | YES |
| `ROLLBACK_PATH` | `/home/pkjetp/backups/ops-def-0011-0012-pre-deploy-20261016T220356Z` |
| Previous public frontend BUILD_ID | `QClrSvr5W0V2GqllIeLKE` |
| Dashboard BUILD_ID (unchanged) | `7-iZf4RbaJmGpuDjO1tsh` |

Restore backed-up Laravel/Blade/routes files; restore frontend sources from backup where present; rebuild/cutover public frontend to prior BUILD_ID via certified process if needed. Do not claim full-tree git SHA equals main.

## Scripts used

- `_ops-def-0011-0012-prehealth.sh`
- `_ops-def-0011-0012-deploy-root.sh`
- `_ops-def-0011-0012-verify.sh` (partial)
- `_ops-def-0011-0012-verify-rest.php`
- `_ops-def-0011-0012-verify-headers.sh` / `_ops-def-0011-0012-headers2.sh`
- Certified `/tmp/jetpk-next-build.sh` with `PUBLIC_ONLY=1`
