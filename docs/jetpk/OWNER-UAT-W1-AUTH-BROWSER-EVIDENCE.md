# OWNER-UAT-W1 — Auth Browser Evidence

Status after reopen gate: `OWNER_W1_AUTH_REGRESSION` retested with **real Chromium/Chrome** against production.

## Result

`OWNER_W1_AUTH_REGRESSION=PASS` (browser automation)  
`OWNER_UAT_WAVE_1=PASS_READY_FOR_OWNER_RETEST`

API/curl-only proof was treated as insufficient for closure.

## Root cause (sanitized)

Real Chromium sessions show intermittent `net::ERR_ABORTED` on:

`GET /laravel/api/public/content/csrf-token`

When the aborted fetch was the only CSRF attempt, the login UI surfaced:

`Network error. Check your connection and try again.`

Contributing race after a successful JSON login: soft App Router navigation (`router.push`) to Agent/Customer portals could race session `Set-Cookie` established via the `/laravel` → Next rewrite → private Laravel bridge.

## Repair (minimal)

1. Retry CSRF bootstrap on empty/failed prep (`useAuthSubmissionReady`).
2. Force CSRF refresh immediately before credential POST (`LoginForm`).
3. One network retry for auth POSTs (`/login`, `/login/otp`, resend, logout).
4. Hard navigation (`window.location.assign`) after successful login/OTP.

F005 `/laravel/:path*` → `/index.php/:path*` rewrite retained.

## Path comparison

| Path | CSRF | Login POST | Notes |
|---|---|---|---|
| A. Browser `https://jetpakistan.pk/laravel/*` | 200 JSON | 200 JSON | OLS proxies `/laravel` → Public Next rewrite → `127.0.0.1:8088` |
| B. Public curl same-origin | 200 + Set-Cookie | 200 | Confirms bridge preserves method/body/headers |
| C. Private `http://127.0.0.1:8088/index.php/*` | 200 | n/a in browser | Direct Laravel; cookies lack `Secure` (HTTP) |

## Cookie semantics (no values)

| Cookie | Domain | Path | SameSite | Secure | HttpOnly |
|---|---|---|---|---|---|
| XSRF-TOKEN | jetpakistan.pk | / | Lax | yes (public HTTPS) | no |
| jetpakistan-session | jetpakistan.pk | / | Lax | yes (public HTTPS) | yes |

`APP_URL=https://jetpakistan.pk`  
`SESSION_DOMAIN=jetpakistan.pk`

## Production build

- Public Next PM2: `jetpk-public-frontend` restarted after rebuild
- `PUBLIC_BUILD_ID=zw_QvaAlxdGFups6haHYa`
- Login bundles include auth gate strings on new chunk hashes
- Service worker URLs `/sw.js` and `/service-worker.js` → 302 not-found (no active SW install path)

## Browser acceptance matrix

| ROLE | CSRF_STATUS | LOGIN_STATUS | SESSION_ESTABLISHED | REDIRECT | PORTAL_LOAD | RESULT |
|---|---|---|---|---|---|---|
| QA Staff | 200 (with possible aborted duplicates) | POST `/laravel/login` 200 | yes | `/staff/dashboard` | yes | PASS |
| QA Agent | 200 (with possible aborted duplicates) | POST `/laravel/login` 200 | yes | `/agent` → `/agent/dashboard` | yes | PASS |
| QA Customer | 200 (with possible aborted duplicates) | POST `/laravel/login` 200 | yes | `/customer/bookings` | yes | PASS |
| QA Agent (reuse context) | — | — | yes | Agent portal | yes | PASS |

Gates for each:

- NETWORK_ERROR=0 (UI)
- SILENT_REFRESH=0
- CSRF_FAILURE=0
- UNEXPECTED_419=0
- HTML_API_RESPONSE=0
- ROLE_REDIRECT=PASS
- SESSION_COOKIE=PASS
- PORTAL_LOAD=PASS

## OLS

Read-only:

`sha256sum /usr/local/lsws/conf/httpd_config.conf`  
`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

`OWNER_UAT_AUTH_OLS_INTEGRITY=PASS`

## Not done

- No Wave 2
- No portal redesign in this reopen
- No OTP policy change (`OTA_CLIENT_REQUIRE_LOGIN_OTP=false` temporary Owner UAT)
- `OTP_DEMO_*` preserved
- No commercial mutations
