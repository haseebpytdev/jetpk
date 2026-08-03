# JP-OPS-05 API Error State Matrix

Dashboard `laravel-action-client` aligns with JP-OPS-02:

| HTTP | Code | Mutation replay |
|------|------|-----------------|
| 401 | unauthorized | No |
| 403 | forbidden | No |
| 404 | not_found | No |
| 409 | conflict | No |
| 419 | csrf_expired | **No auto replay** (default `retryCsrfOnce: false`) |
| 422 | validation | No |
| 429 | rate_limit | No |
| 5xx | server | No |
| HTML body | unknown | Sanitized message |
| Abort/timeout | aborted/network | No |

Financial, deposit, cancellation, refund, and staff mutations are on the CSRF no-auto-retry prefix list.
