# JP-OPS-05 Payment Deposit Review Contract

## Payment review

| Operation | Method | Idempotency |
|-----------|--------|-------------|
| Verify | PATCH portal payment | Duplicate → 409 `already_processed` |
| Reject | PATCH portal payment + reason | Validation 422 |

- No card data or provider raw payload in JSON
- Capabilities on `DashboardPaymentResource`
- Dashboard client: `retryCsrfOnce: false`

## Deposit review (Admin only)

| Operation | Method | Idempotency |
|-----------|--------|-------------|
| Approve | PATCH `admin/agent-deposits/{id}/approve` | Wallet lock + status guard |
| Reject | PATCH + `admin_note` | Required reason |

- Reads: `GET /api/dashboard/deposits`, `GET /api/dashboard/deposits/{id}`
- One wallet credit per approval (verified in tests)
- Proof download remains Blade/authorized stream route
