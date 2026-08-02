# JP-OPS-02 Session Expiry Recovery Matrix

| Event | API code | Frontend behavior |
|-------|----------|-------------------|
| Session expired | 401 `unauthorized` | `recoverFromUnauthorized()` → `/login?reason=session-expired` (once) |
| CSRF expired | 419 `csrf_expired` | Show refresh message; no blind mutation replay |
| Account disabled | `session_usable: false` | `/access-denied?reason=account-disabled` |
| Wrong role | 403 / guard redirect | `dashboard_url` or `/access-denied` |
| Role/permission change | Next session fetch | Laravel wins; no client authority cache |

## Storage audit

- No auth tokens in `localStorage`/`sessionStorage` (theme preference only)
- `NEXT_PUBLIC_SESSION_PREVIEW` disabled as authority in production builds
- `OTA_ALLOW_SESSION_FIXTURE` required for test fixtures

## Loop prevention

`sessionRecoveryInFlight` guard prevents concurrent 401 redirect storms.
