# JP-OPS-03 Cancellation & Refund Contract

## Cancellation (CONNECTED)

- **POST** `/customer/bookings/{booking}/cancellations?format=json`
- Body: `cancellation_type` (required), `reason` (optional), `terms_acknowledged` (optional)
- Success **201**: `{ ok, message, cancellation_request: { id, status, status_label, message } }`
- Duplicate open request **409**: `code: cancellation_already_requested`
- No live Sabre execution from customer portal

## Refund (INTENTIONALLY_UNAVAILABLE for customer request)

- No customer refund POST route
- Booking detail exposes read-only `refund` summary when staff records exist
- `capabilities.can_request_refund` is always `false` with `customer_refund_request_unavailable`
