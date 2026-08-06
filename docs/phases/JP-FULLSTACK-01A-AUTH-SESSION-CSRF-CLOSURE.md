# JP-FULLSTACK-01A — Auth, Session, CSRF and Force-Password Closure

**Phase:** JP-FULLSTACK-01A
**Branch:** `phase/jetpk-fullstack-01-public-customer-agent-checkout-connectivity`
**Audit anchor:** `02405021103f7ebd15f734a913bf5519d9d28a58`
**Parent baseline:** `846add82e0aea36e84e877b067bc2210ef2af467`
**Status:** Implementation complete — **not committed** (stop for review)

## Objective

Close **JP-FS01-GAP-001**: Next-owned `/password/force-change` page wired to authoritative Laravel session/CSRF/password update, preserving Blade fallback and OTP demo.

## Gap closure

| Gap ID | Original status | Final status | Notes |
|--------|-----------------|--------------|-------|
| JP-FS01-GAP-001 | HIGH — no Next force-password page | **CLOSED** | Next route + JSON contract on existing controller |

No other gaps were in 01A scope.

## Implementation summary

### Laravel

- `ForcePasswordChangeController` — additive `?format=json` / `Accept: application/json` on GET and POST; role-aware redirect via `AuthPostLoginRedirectResolver`; Blade HTML preserved
- `EnsurePasswordChanged` — allow `api.public.auth.session` and `api.public.content.csrf` so session bootstrap works while password change is required

### Frontend

- `frontend/app/(auth)/password/force-change/page.tsx` — canonical Next route
- `ForcePasswordChangeForm` — CSRF POST, validation display, logout, no password storage
- `force-password-access.ts` — SSR guard (no redirect loop)
- `force-password-service.ts` — Laravel mutation with optional CSRF retry
- Session fixtures: `customer_force_password`, `agent_force_password`
- `laravelJsonFetch` — passes `retryCsrfOnce` to shared client

### Blade fallback

`resources/views/themes/frontend/jetpakistan/auth/force-password-change.blade.php` — **unchanged**

### OTP demo

`config/ota_otp_demo.php`, `DemoFixedLoginOtpGate.php` — **no diff**

## Tests

| Suite | Command | Result |
|-------|---------|--------|
| Laravel auth + force-password | `php artisan test tests/Feature/Auth/ForcePasswordChangeJsonTest.php tests/Feature/Auth/EnsurePasswordChangedMiddlewareTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/DemoFixedLoginOtpGateTest.php` | 39 passed |
| Frontend typecheck | `npm run typecheck` | exit 0 |
| Frontend lint | `npm run lint` | exit 0 |
| Frontend build | `npm run build` | exit 0 |
| Playwright 01A | `jp-fullstack-01a-force-password.spec.ts` | not run (fixture-based; ready for CI) |

## Remaining limitations

- Post-password redirect follows Laravel `AuthPostLoginRedirectResolver` (e.g. customer → `/customer/bookings`, agent → `/agent`), not necessarily dashboard URLs
- Playwright 01A specs require `OTA_ALLOW_SESSION_FIXTURE=true` at runtime (existing portal guard pattern)

## Rollback

Revert implementation files listed in git diff; restore `EnsurePasswordChanged` and `ForcePasswordChangeController` from audit anchor `0240502`.
