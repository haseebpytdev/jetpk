# One API Phase 3 — Route and security audit

| Method | URI | Controller | Middleware | AuthZ | Type | CSRF | Sensitivity |
|--------|-----|------------|------------|-------|------|------|-------------|
| GET | `/booking/one-api/catalog` | `OneApiCheckoutController@catalog` | `web`, `platform.module:customer_checkout` | Public checkout module gate; no booking ID | Read | N/A (GET) | Catalog selection IDs only |
| POST | `/booking/one-api/final-price` | `OneApiCheckoutController@saveSelections` | `web`, `customer_checkout`, `throttle:public-booking-submit` | Same | Mutation | Yes | Settlement totals; no TID/RPH/XML |
| POST | `/booking/one-api/selections` | alias final-price | same | same | Mutation | Yes | Legacy alias |
| GET | `/booking/one-api/extras` | deprecated catalog | same | same | Read | N/A | Same as catalog |

## Admin / CLI (not web customer)

- `ota:one-api-test-matrix` — fixture by default; live requires confirm flags; `--connection` required for execution.
- Probe/mutation commands gated by `OneApiMutationCommandGate` and explicit `--confirm-*` flags.

## Proofs

- No GET routes perform supplier booking or final price.
- No public route returns raw SOAP/XML, cookies, or JSESSIONID.
- Checkout JSON rejects `client_total` / `posted_supplier_amount`.
- `OneApiValidationException` renders JSON 422 via `bootstrap/app.php` handler (mixed hunk — stage with `git add -p`).

## Phase 5 update (transport contracts)

- `OneApiSoapTransportContract` bound in `OneApiServiceProvider`.
- **Production / default:** `LiveOneApiSoapTransport` (no fixture reads).
- **Explicit fixture only:** `FixtureOneApiSoapTransport` when `OneApiFixtureTransportScope::isExplicitlyEnabled()` (`matrix_command`, `phpunit` trait, probe commands).
- `OneApiFixtureCaseCatalog` — allowlisted fixture keys only (no arbitrary paths across service boundary in production).
- `isExplicitlyEnabled()` separated from `isEnabled()` (file-read gate for PHPUnit still uses broader `isEnabled()` for `resolveReadableFixturePath`).
