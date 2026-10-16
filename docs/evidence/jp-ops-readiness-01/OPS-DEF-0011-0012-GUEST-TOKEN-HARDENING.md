# OPS-DEF-0011 / OPS-DEF-0012 — Guest Access Token Hardening

```
PROTECTED_MAIN_SHA=0d2bfd9daf3106d08efa1098201ffc08b7531f0e
BRANCH=security/ops-def-0011-0012-guest-token-hardening
NO_PRODUCTION_DEPLOY=YES
NO_PRODUCTION_MUTATION=YES
SABRE_LIVE_PNR_ALLOWED_NOW=NO
MIGRATION_STRATEGY=TWO_PHASE
```

## Token flow map (Phase 1)

| Key | Finding |
|-----|---------|
| TOKEN_CREATION_PATHS | Lookup POST; guest booking submit; confirmation AbhiPay resolve; booking-received email side-effect (hash only / not emailed) |
| TOKEN_URL_ROUTES | Legacy `/guest/bookings/{id}/access/{token}` retained as **exchange only**; mutations have legacy `*.legacy` aliases |
| TOKEN_QUERY_ROUTES | Document download `?token=` still accepted as compat via `authorizeGuestAccess`; primary path is session |
| TOKEN_FRONTEND_STATE | Clean `/guest/bookings/{id}` Next page; legacy `[token]` page redirects into Laravel exchange |
| TOKEN_LOCAL_STORAGE_USAGE | None |
| TOKEN_SESSION_STORAGE_USAGE | None (browser); Laravel encrypted session holds raw token |
| TOKEN_COOKIE_USAGE | Laravel session cookie only (HttpOnly); no dedicated guest-token cookie |
| TOKEN_SERVER_LOG_RISK | POSSIBLE for legacy path (access logs) until Phase-2 removal; application logs do not store raw token |
| TOKEN_REFERRER_RISK | MITIGATED on guest surfaces via `Referrer-Policy: no-referrer` |
| TOKEN_ANALYTICS_RISK | POSSIBLE if future analytics records pathname on legacy hop; clean URL has no token |
| TOKEN_DOCUMENT_DOWNLOAD_PATH | `/guest/documents/{id}/download` session-auth (query token compat) |
| TOKEN_PAYMENT_PROOF_PATH | `/guest/bookings/{id}/payment-proof` |
| TOKEN_PROMO_PATH | `/guest/bookings/{id}/promo/{apply,remove}` |
| TOKEN_CANCELLATION_PATH | `/guest/bookings/{id}/cancellations` |
| TOKEN_ABHIPAY_PATH | `/guest/bookings/{id}/abhipay/start` |

## OPS-DEF-0012

```
OPS_DEF_0012_CONFIRMED=YES
FIX=createTokenForBooking DB::transaction + lockForUpdate expire prior active rows (expires_at=now) then insert new hash
TOKEN_ROTATION_ATOMIC=YES
REQUIRES_DATABASE_MIGRATION=NO
OLD_TOKEN_VALID_AFTER_ROTATION=NO
NEW_TOKEN_VALID=YES
RAW_TOKEN_NOT_STORED=YES
```

## OPS-DEF-0011

```
OPS_DEF_0011_CONFIRMED=YES
TOKEN_FREE_NEXT_FEASIBLE=YES
DECISION=TWO_PHASE_MIGRATION
PHASE1=session bind + clean URLs + legacy exchange 302 + security headers + token-free mutation/doc URLs
PHASE2_LATER=remove legacy path routes + Next [token] page + access-log redaction ops
```

Threat model (path bearer):

| Surface | Classification |
|---------|----------------|
| Laravel/app logs | MITIGATED (no raw token logged) |
| LiteSpeed access logs | POSSIBLE on legacy hop — ops follow-up / OPS-RISK-0017 |
| Next proxy logs | POSSIBLE on legacy hop |
| Browser history | MITIGATED after exchange (clean URL); POSSIBLE on first legacy hop |
| Referer | MITIGATED (`no-referrer` on guest responses) |
| Analytics | POSSIBLE if enabled later |
| Screenshots/copy | POSSIBLE on legacy hop only |
| Document/mutation URLs in JSON | MITIGATED (token-free) |

```
OPS_DEF_0011_FIX_IMPLEMENTED=YES (phase-1)
OPS_DEF_0011_STATUS=FIX_READY_FOR_DEPLOYMENT
OPS_DEF_0012_STATUS=FIX_READY_FOR_DEPLOYMENT
```

Phase-2 residual: remove legacy `/access/{token}` entirely; server access-log redaction.

## Security headers (guest surfaces)

```
Referrer-Policy=no-referrer
Cache-Control=private, no-store, no-cache, must-revalidate
Pragma=no-cache
X-Robots-Tag=noindex, nofollow
```

## Tests

```
TARGETED_GUEST_SUITE=23 passed / 129 assertions (tests/Feature/Guest/)
TARGETED_FAIL=0
BROADER_UI_PREEXISTING_FAIL=2 (customer dashboard KPI/filter testids — unrelated branding UI; not caused by this change)
```

## Production mutation counters

```
PRODUCTION_GUEST_TOKENS_MUTATED=0
PRODUCTION_BOOKINGS_MUTATED=0
PAYMENT_MUTATIONS=0
SUPPLIER_MUTATIONS=0
PNR_CALLS=0
```
