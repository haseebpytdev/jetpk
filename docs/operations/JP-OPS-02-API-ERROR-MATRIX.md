# JP-OPS-02 API Error Matrix

Mapped in `frontend/lib/api/errors.ts`.

| HTTP | Code | Default message |
|------|------|-----------------|
| 400 | `unknown` | Request failed. Please try again. |
| 401 | `unauthorized` | Your session has expired. Please sign in again. |
| 403 | `forbidden` | You do not have permission to perform this action. |
| 404 | `not_found` | The requested resource could not be found. |
| 409 | `conflict` | This action is no longer valid. Please refresh and try again. |
| 419 | `csrf_expired` | Your session expired. Please refresh and try again. |
| 422 | `validation` | Please correct the highlighted fields and try again. |
| 429 | `rate_limit` | Too many attempts. Please wait a moment and try again. |
| 5xx | `server` | Something went wrong on our side. Please try again shortly. |
| Network | `network` | Network error. Check your connection and try again. |
| Abort | `aborted` | Request cancelled. |
| Malformed/empty JSON | `unknown` | Unexpected empty response from server. |
| HTML body | `unknown` | Uses status default message |

## Retry policy

- GET: optional `retryOnNetworkError` (once)
- Mutations: no auto-replay by default
- CSRF: optional `retryCsrfOnce` (once, non-payment/booking only)
