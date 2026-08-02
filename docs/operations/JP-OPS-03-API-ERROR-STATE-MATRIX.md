# JP-OPS-03 API Error State Matrix

Customer portal uses JP-OPS-02 `laravelRequest` via `customer-dashboard-api.ts`.

| HTTP | Code | Customer UI |
|------|------|-------------|
| 401 | unauthorized | Session expired message |
| 403 | forbidden | No access to record |
| 404 | not_found | Record unavailable |
| 409 | conflict | Server message (e.g. duplicate cancellation) |
| 419 | csrf_expired | CSRF failure (no blind mutation replay) |
| 422 | validation | Field errors |
| 429 | rate_limit | Throttle message |
| 5xx | server | Generic failure |

Mutations use `retryCsrfOnce: false` to avoid uncertain replay.
