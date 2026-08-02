# JP-OPS-02 CSRF API Bridge Contract

## Mechanism

- Laravel web session CSRF (no Sanctum)
- `XSRF-TOKEN` cookie (http-readable)
- `GET /api/public/content/csrf-token` → `{ csrf_token }` with `Cache-Control: no-store, private`
- Mutations send `X-XSRF-TOKEN` header via `frontend/lib/api/laravel-action-client.ts`
- CSRF retry eligibility is enforced by `frontend/lib/api/csrf-retry-policy.mjs` (imported by the production client)
- Token is never stored in `localStorage` or `sessionStorage`

## 419 handling

| Status | Code | Message |
|--------|------|---------|
| 419 | `csrf_expired` | Your session expired. Please refresh and try again. |

Default mutation policy: **no automatic replay**.

Optional `retryCsrfOnce: true` on `laravelRequest` refreshes CSRF once and retries via `shouldRetryAfterCsrfExpired` + `pathAllowsCsrfAutoRetry`. Never use for payment or booking mutations.

## Proxy

Next.js rewrites `/laravel/:path*` → configured `LARAVEL_URL`. No user-controlled upstream URLs.

## Credentials

All Laravel API calls use `credentials: "include"`.
