# 02 — Payment separation

AirBlue sandbox supplier booking was certified independently of customer payment.

## State at AirBook

- booking status: `payment_pending`
- payment status: `unpaid`
- amount paid: `0.00`
- AbhiPay: not used
- payment calls: 0

## Invariant

`CUSTOMER_FINANCIAL_FIELDS_UNCHANGED=PASS`

Supplier AirBook/retrieve did not rewrite the customer financial state.

No gateway row, payment callback, paid-state simulation or auto-ticket path was used.
