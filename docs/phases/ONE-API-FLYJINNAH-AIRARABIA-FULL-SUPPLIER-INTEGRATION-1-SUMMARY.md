# ONE-API-FLYJINNAH-AIRARABIA-FULL-SUPPLIER-INTEGRATION-1

## Phase metadata

| Field | Value |
|-------|-------|
| Phase name | ONE-API-FLYJINNAH-AIRARABIA-FULL-SUPPLIER-INTEGRATION-1 |
| Branch | `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1` |
| Objective | Full One API supplier module (REST search + SOAP price/book) for Fly Jinnah / Air Arabia |

## Scope

### Included

- Supplier registry, adapters, workflow context, admin readiness panel
- REST auth/search, SOAP transport, pricing, bundles, ancillaries, booking, retrieve/hold pay
- Checkout hooks and Artisan probes / 24-case matrix (fixture mode)
- Unit/feature tests with fixtures

### Excluded

- Live ISA certification runs
- Production credentials or SOAP URLs

## Investigation / root cause

_Placeholder_

## Files changed

_Placeholder — see git diff_

## Routes

- `booking.one-api.extras`, `booking.one-api.selections`

## Database

None (JWT not stored in DB).

## Tests executed

```bash
php artisan test --filter=OneApi
```

| Result | Count |
|--------|-------|
| Passed | 11 |
| Failed | 0 |

## Assertions / screenshots

_Placeholder_

## Responsive / accessibility

N/A (supplier backend).

## Known limitations

- Cancel/refund not supported (`cancel_unticketed` false).
- SOAP URL must be vendor-supplied per connection.

## Risks

_Placeholder_

## Rollback

Disable `one_api_supplier` platform module; remove connection or set inactive.

## Commit SHA

_Placeholder — not committed per phase instructions_

## Final status

_Placeholder_
