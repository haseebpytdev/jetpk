# Dashboard Read-Only Session Contract — JETPK-DASH-11

## Endpoint

`GET /api/dashboard/session`

## Authentication

- Laravel `web` session cookie (`laravel_session`, httpOnly)
- Same-origin when dashboard is mounted under Laravel `public/testdash/`
- Unauthenticated → `401` with sanitized error envelope
- Non-dashboard account types → `403`

## Response `data` fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | string | Safe user ID |
| `displayName` | string | User name |
| `email` | string \| null | Masked (`ab***@domain`) |
| `roles` | string[] | Human-readable role labels |
| `permissions` | string[] | Dashboard permission keys |
| `accountType` | string | `AccountType` enum value |
| `accountStatus` | string | User status enum value |
| `staffType` | string \| null | `admin`, `staff`, `agent`, or null |
| `schemaVersion` | string | `dash-read-only-v1` |
| `generatedAt` | string | ISO-8601 |

## Excluded fields

Never returned: `password`, `remember_token`, `mfa_secret`, `recovery_codes`, `session_id`, raw cookies, API tokens, supplier credentials.

## Frontend consumption

- Server: `getDashboardSession()` in `dashboard/services/session-service.ts`
- Passed to `DashboardShell` → `DashboardHeader` / `DashboardSidebar`
- Fixture mode uses `mockUser` from `overview-fixtures.ts`

## Cache policy

`Cache-Control: private, no-store`. Session summary is always fetched fresh; no cross-user caching.

## Error states

| HTTP | UI state |
|------|----------|
| 401 | `UnauthorizedState` |
| 403 | `ForbiddenState` |
| 503 | `ServiceUnavailableState` |

Frontend never stores session tokens in `localStorage` or `sessionStorage`.
