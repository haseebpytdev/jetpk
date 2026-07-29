# Customer Bookings and Payments Contract

## Bookings list (`GET /customer/bookings?format=json`)

Query: `filter` (`all`, `pending_payment`, `pnr_created`, `needs_action`, `cancelled`), `page`, `per_page`.

Each item includes: `booking_reference`, route, dates, passenger count, totals, separate `booking_status`, `payment_status`, `ticketing_status`, optional `pnr`, `detail_url`, `next_action`.

## Booking detail (`GET /customer/bookings/{booking_reference}?format=json`)

Reuses `StandardBookingJsonPresenter::presentConfirmation` payload with customer-portal action URLs.

Ownership: `BookingPolicy::view` + `customer_id` match.

## Payments (`GET /customer/payments?format=json`)

Merged manual `BookingPayment` proofs and card `PaymentTransaction` rows for owned bookings.

Filter: `all`, `pending`, `paid`, `failed`.

No gateway secrets or raw provider payloads exposed.

## Invoices (`GET /customer/invoices?format=json`)

From `BookingDocument` type `invoice` for owned bookings.

PDF download via existing `/customer/documents/{id}/download`.

## Group bookings

Not included in customer bookings list. Document honestly when only standard bookings are returned.
