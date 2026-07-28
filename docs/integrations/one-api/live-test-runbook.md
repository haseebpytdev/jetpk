# One API live test runbook

## Blockers

1. **SOAP URL** — not present in vendor document pack; set `soap_url` on the connection before any live price/book/read/modify probe.
2. **Live flags** — `live_search_enabled`, `live_booking_enabled`, `live_payment_modification_enabled` default off on the connection.
3. **On-hold** — `on_hold_enabled` default off until agency confirms carrier permission.

## Required confirmations

| Operation | CLI flags | Connection flag |
|-----------|-----------|-----------------|
| Live search | `--live` + `--confirm-live-search` | `live_search_enabled` |
| Live book | `--live` + `--confirm-live-booking` | `live_booking_enabled` |
| Hold payment | `--live` + `--confirm-live-payment` | `live_payment_modification_enabled` |

Matrix runner: `php artisan ota:one-api-test-matrix --mode=fixture` (default). Live mode refuses without explicit confirms.

Never commit vendor credentials or tokens. Never auto-retry ambiguous book/payment SOAP calls.
