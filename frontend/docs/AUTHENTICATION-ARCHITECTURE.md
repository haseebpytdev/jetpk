# JetPakistan Next.js Authentication Architecture (JP-FE-04)

## Authority model

- **Laravel** owns credentials, OTP, sessions, CSRF, password reset, account status, RBAC, rate limits, and post-auth destinations.
- **Next.js** owns authentication presentation and submits to same-origin `/laravel/*` endpoints.
- No separate Node auth system, no JWT, no `localStorage` credentials.

## Session bootstrap

`GET /laravel/api/public/auth/session`

```json
{
  "authenticated": true,
  "user": {
    "id": "1",
    "name": "Ayesha Khan",
    "email": "ayesha@example.com",
    "account_type": "customer"
  },
  "role": "customer",
  "permissions": [],
  "dashboard_url": "/customer/bookings",
  "requires_otp": false,
  "requires_password_change": false,
  "requires_email_verification": false,
  "account_status": "active"
}
```

Guest:

```json
{ "authenticated": false }
```

Pending OTP (guest):

```json
{
  "authenticated": false,
  "requires_otp": true,
  "otp_challenge": {
    "masked_email": "a***@example.com",
    "resend_available_in": 42
  }
}
```

## Laravel endpoints used by Next.js

| Flow | Method | Path | JSON |
|------|--------|------|------|
| Login | POST | `/login` | `{ ok, redirect, requires_otp? }` |
| OTP verify | POST | `/login/otp` | `{ ok, redirect, dashboard_url, user? }` |
| OTP resend | POST | `/login/otp/resend` | `{ ok, resend_available_in, message }` |
| Logout | POST | `/logout` | `{ ok, redirect }` |
| Customer register | POST | `/register` | `{ ok, redirect, requires_email_verification?, message? }` |
| Agent apply | POST | `/agent/register` | `{ ok, redirect, pending?, message? }` |
| Forgot password | POST | `/forgot-password` | `{ ok, message }` (always generic) |
| Reset password | POST | `/reset-password` | `{ ok, redirect, message? }` or `422` |
| OTP challenge | GET | `/api/public/auth/otp-challenge` | `{ has_challenge, masked_email?, resend_available_in? }` |
| Registration CAPTCHA | GET | `/api/public/auth/registration-security-challenge` | `{ security_question }` |
| CSRF | GET | `/api/public/content/csrf-token` | `{ csrf_token }` |

## Field contracts

### Login (`login`, `password`, `remember`, `client_slug`)

### OTP (`otp` — 6 digits, `client_slug`)

### Customer registration

`first_name`, `last_name`, `email`, `mobile_country_code`, `mobile`, `password`, `password_confirmation`, `security_answer`, `terms`

### Agent application

`company_name`, `city`, `business_type`, `first_name`, `last_name`, `email`, `mobile_country_code`, `mobile`, `country`, `office_address`, `notes`, `terms`

### Password reset request (`email`)

### Password reset (`token`, `email`, `password`, `password_confirmation`)

## Role routing (Laravel authoritative)

| Account type | Dashboard destination |
|--------------|----------------------|
| `customer` | `/customer/bookings` (Laravel `customer.bookings.index`; fallback `/customer`) |
| `agent`, `agent_staff` | `/agent` |
| `platform_admin` | `/admin/dashboard` |
| `staff` | `/staff/dashboard` |

Next.js validates returned paths against `PublicAuthRedirectAllowlist` / `dashboard-allowlist.ts`. Admin/Staff dashboards remain the existing Laravel dashboard apps.

## CSRF and cookies

1. Browser requests use `credentials: "include"` via `/laravel/*` rewrite.
2. POST requests send `X-XSRF-TOKEN` from `XSRF-TOKEN` cookie (or CSRF bootstrap endpoint).
3. Server Components forward request cookies when calling session bootstrap.

## OTP and demo patch

JetPakistan (`client_slug=jetpk`) always requires login OTP. The existing `OTP_DEMO_*` / `DemoFixedLoginOtpGate` patch is unchanged — demo codes are never shown in the Next.js UI.

## Frontend module layout

```
frontend/features/auth/
├── components/   AuthShell, LoginForm, OtpForm, registration/password forms
├── services/     auth-service, session-service, registration-service, password-reset-service
├── types/
├── utils/        laravel-auth-api, dashboard-allowlist
└── index.ts
```

## Next.js routes

- `/login`, `/login/otp`, `/register`, `/agent/register`, `/agent/register/submitted`
- `/forgot-password`, `/reset-password/[token]`
- Customer placeholders: `/customer` → `/customer/bookings`, `/customer/bookings`
- Agent placeholder: `/agent`
- `/access-denied`

### Customer portal route closure (JP-FE-04A)

Laravel session bootstrap returns `dashboard_url: "/customer/bookings"` for customers. Next.js owns:

- `/customer` — server redirect to `/customer/bookings` (no loop)
- `/customer/bookings` — guarded placeholder via `requireCustomerPortalAccess()` (Laravel `account_type` must be `customer`)

Unauthenticated users redirect to `/login`. Non-customers redirect to Laravel-provided `dashboard_url` (not client-inferred).

## Preview mode

`NEXT_PUBLIC_SESSION_PREVIEW=logged-in` enables isolated fixture session for shell QA only (default: `logged-out`).

### SSR smoke fixture cookie (non-production only)

Playwright smoke runs set `OTA_ALLOW_SESSION_FIXTURE=true` and cookie `ota_session_fixture` to `customer`, `agent`, or `anonymous` so SSR portal guards can be exercised without a live Laravel process. This is disabled unless the env flag is set.

## Local development

- Next.js: `http://localhost:3000`
- Laravel: `http://127.0.0.1:8000` (proxied at `/laravel/*`)
- Cookies must be same-site compatible; production uses Nginx same-origin proxy.

## Known limitations

- Customer/agent operational dashboards are placeholders in Next.js; Laravel portals remain authoritative.
- Email verification UI remains on Laravel `/verify-email` for now.
- Social OAuth buttons not yet ported to Next.js (Blade flow preserved).

## Next phase

JP-FE-05 — flight results Next.js presentation, filters, branded fares, and Laravel result contract.
