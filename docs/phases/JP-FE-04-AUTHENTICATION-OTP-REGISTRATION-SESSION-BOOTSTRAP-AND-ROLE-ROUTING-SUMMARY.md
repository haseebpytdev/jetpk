# JP-FE-04 — Authentication, OTP, Registration, Session Bootstrap, and Role Routing

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-04-AUTHENTICATION-OTP-REGISTRATION-SESSION-BOOTSTRAP-AND-ROLE-ROUTING |
| Branch | `phase/jetpk-fe-04-auth-session-role-routing` |
| Objective | Operational Next.js authentication flows connected to Laravel session authority with role-based routing |
| Final status | **PASS** (targeted gates) |
| Production touched | **No** |

## Included scope

- Laravel session bootstrap API and JSON extensions for auth flows
- Next.js auth feature module (`frontend/features/auth/`)
- Public routes: `/login`, `/login/otp`, `/register`, `/agent/register`, `/forgot-password`, `/reset-password/[token]`
- Placeholders: `/customer`, `/agent`, `/access-denied`
- Account menu logout + session-aware navigation
- CSRF/same-origin `/laravel/*` integration
- Targeted Laravel + Playwright tests
- Architecture documentation

## Excluded scope

- Full customer/agent operational dashboards
- Admin/staff dashboard duplication in Next.js
- Social OAuth Next.js UI (Blade preserved)
- Supplier/booking/payment logic
- Production deployment

## Investigation findings

- Only `POST /login` previously returned JSON; OTP/register/reset/logout were redirect-only
- JetPakistan (`client_slug=jetpk`) always requires OTP via `ClientLoginOtpGate`
- Demo OTP patch (`OTP_DEMO_*`, `DemoFixedLoginOtpGate`) must remain untouched
- Customer post-login Laravel destination is `/customer/bookings` when route exists
- Agent registration creates pending `AgentApplication`, not a user account

## Root causes addressed

- Next.js shell used fixture session by default (`NEXT_PUBLIC_SESSION_PREVIEW`)
- No session bootstrap contract for SSR/client nav state
- Auth pages and logout were non-functional links/buttons
- OTP and registration flows lacked JSON for SPA handoff

## Laravel contract summary

See `frontend/docs/AUTHENTICATION-ARCHITECTURE.md` for full payloads.

### Session bootstrap

`GET /api/public/auth/session` — authoritative `authenticated`, `user`, `role`, `permissions`, `dashboard_url`, account flags.

### Role routing table

| Account type | Dashboard URL (Laravel) |
|--------------|-------------------------|
| `customer` | `/customer/bookings` (fallback `/customer`) |
| `agent`, `agent_staff` | `/agent` |
| `platform_admin` | `/admin/dashboard` |
| `staff` | `/staff/dashboard` |

### OTP behavior

1. `POST /login` → `requires_otp: true` + redirect `/login/otp`
2. `POST /login/otp` → JSON success with `dashboard_url`
3. `POST /login/otp/resend` → JSON with cooldown
4. Demo OTP unchanged in Laravel only

## Files changed

### Laravel

- `app/Support/Auth/PublicAuthRedirectAllowlist.php` (new)
- `app/Support/Auth/PublicSessionBootstrapService.php` (new)
- `app/Http/Controllers/Api/PublicSessionController.php` (new)
- `app/Http/Controllers/Api/PublicAuthController.php` (new)
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/LoginOtpController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Frontend/AgentRegistrationController.php`
- `routes/web.php`
- `tests/Feature/Auth/PublicSessionBootstrapTest.php` (new)

### Frontend

- `frontend/features/auth/**` (new module)
- `frontend/app/(auth)/**` (new pages)
- `frontend/app/customer/page.tsx`, `frontend/app/agent/page.tsx`, `frontend/app/access-denied/page.tsx`
- `frontend/components/navigation/AccountMenu.tsx`
- `frontend/components/navigation/MobileNavigation.tsx`
- `frontend/services/session.ts`
- `frontend/types/session.ts`
- `frontend/tests/auth.spec.ts`
- `frontend/docs/AUTHENTICATION-ARCHITECTURE.md`

## Routes changed

### Laravel API

- `GET api/public/auth/session`
- `GET api/public/auth/otp-challenge`
- `GET api/public/auth/registration-security-challenge`

### Next.js

- `/login`, `/login/otp`, `/register`, `/agent/register`, `/agent/register/submitted`
- `/forgot-password`, `/reset-password/[token]`
- `/customer`, `/agent`, `/access-denied`

## Database changes

None.

## Tests executed

### Laravel (targeted)

```
php artisan test tests/Feature/Auth/PublicSessionBootstrapTest.php tests/Feature/Auth/LoginAjaxUxTest.php
```

Result: **17 passed**, 58 assertions

### Frontend

```
npm run typecheck  → pass
npm run lint       → pass
npm run build      → pass (19 routes)
npx playwright test tests/auth.spec.ts → 10 passed
```

## Build route summary

Auth routes compiled: `/login`, `/login/otp`, `/register`, `/agent/register`, `/forgot-password`, `/reset-password/[token]`, `/customer`, `/agent`, `/access-denied`

## Responsive verification

Forms use shared JetPakistan tokens, stacked mobile layouts, full-width buttons, OTP centered input — validated at 375px in Playwright.

## Accessibility verification

- One `h1` per auth page via `AuthShell`
- Visible labels, `aria-describedby` on password/OTP errors
- `role="alert"` / `aria-live` on async banners
- Keyboard-operable password visibility toggle
- OTP `inputMode=numeric`, paste-friendly

## Security notes

- No credential storage in `localStorage`/`sessionStorage`
- Generic forgot-password response (no enumeration)
- Dashboard URL allowlist on Laravel + Next.js
- CSRF on all mutating requests
- Demo OTP never exposed in UI
- Blade auth routes unchanged

## Known limitations

- Email verification remains Laravel `/verify-email`
- OAuth not ported to Next.js
- Customer bookings link uses Laravel proxy `/laravel/customer/bookings`
- Playwright auth API tests use mocks when Laravel is not running locally

## Risks

- SSR session bootstrap requires cookie forwarding; misconfigured proxy could show logged-out nav flash
- Customer default redirect may be `/customer/bookings` while Next placeholder is `/customer`

## Rollback instructions

1. Revert merge commit on `main`
2. Or checkout previous `main` SHA
3. No migrations to roll back

## Commit SHAs

| Commit | SHA | Description |
|--------|-----|-------------|
| Feature | `c4d3d79` | feat(frontend): add JP-FE-04 authentication and session integration |
| Merge | `bbfa92d` | merge: complete JP-FE-04 authentication and role routing |

## Next phase

**JP-FE-05-FLIGHT-RESULTS-NEXTJS-PRESENTATION-FILTERS-BRANDED-FARES-AND-LARAVEL-RESULT-CONTRACT**
