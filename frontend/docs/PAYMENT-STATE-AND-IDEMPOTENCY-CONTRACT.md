# Payment state and idempotency contract

## Payment status codes (frontend presentation)

Mapped from Laravel `PublicAbhiPayCheckoutPresenter`:

| Laravel label | Frontend code | Terminal |
|---------------|---------------|----------|
| Paid | `succeeded` | yes |
| Payment pending | `pending` | no |
| Payment failed | `failed` | yes |
| Unpaid | `not_started` | no |

## Card initiation

1. Next.js POSTs to Laravel `start_endpoint` with `?format=json`
2. Laravel creates idempotent `PaymentTransaction` via `PaymentTransactionService`
3. Response includes `redirect_url` (hosted checkout only)
4. Next.js validates URL host against AbhiPay allowlist before `window.location.assign`

Browser return (`/payment/success`) is **not** proof of payment. `/booking/payment/status` polls Laravel.

## Duplicate protection

- Review submit uses existing Laravel duplicate-submit locks and draft/submitted guards
- Card start uses transaction service idempotency (existing)
- Frontend `submitLock` / `startLock` refs prevent double-click

## Polling

`presentPaymentStatus` returns `poll.should_poll`, `interval_ms` (3000), `max_attempts` (40). Stops on terminal payment status.
