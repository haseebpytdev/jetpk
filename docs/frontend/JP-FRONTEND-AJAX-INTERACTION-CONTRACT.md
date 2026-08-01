# JP-FRONTEND AJAX Interaction Contract

## Authority

Laravel remains authoritative for session, CSRF, auth, booking, payment, RBAC, and ownership.

## Client layer

`frontend/lib/api/laravel-action-client.ts` provides:

- `laravelRequest()` typed boundary
- CSRF via `ensureLaravelCsrfToken()`
- JSON, FormData, timeout, AbortSignal
- Status normalization: 401/403/404/409/422/429/5xx/network/timeout/abort
- `mapFieldErrors()` for 422 responses
- Safe GET retry only (`retryOnNetworkError`)
- No auto-retry for mutations

## Hook

`useAsyncAction` — pending lock, duplicate-submit guard, field error mapping, cancel/reset.

## Rules

- Never show operational success before Laravel confirms
- No optimistic success for login, OTP, booking, payment, wallet, refunds
- No PII in localStorage
- Gateway redirects remain normal navigation
