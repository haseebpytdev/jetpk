# JP-OPS-03 Customer Booking Contract

## List item (`CustomerPortalBookingsPresenter`)

`booking_reference`, `route`, `departure_date`, `passenger_count`, `total`, `currency`, `booking_status`, `payment_status`, `ticketing_status`, `pnr`, `detail_url`, `pagination`.

## Detail (`CustomerPortalBookingDetailPresenter` + `StandardBookingJsonPresenter`)

Confirmation payload plus:

```json
{
  "booking": { "id": 1, "booking_reference": "BKG-1234" },
  "capabilities": {
    "can_request_cancellation": true,
    "can_request_refund": false,
    "reason_codes": { "can_request_refund": "customer_refund_request_unavailable" },
    "mutation_urls": { "request_cancellation": "/laravel/customer/bookings/1/cancellations" },
    "download_urls": { "invoice": "...", "ticket": "..." }
  },
  "cancellation": { "state": "available|request_submitted|cancelled|...", "message": "..." },
  "refund": { "state": "not_eligible|under_review|paid|...", "can_request": false }
}
```

States are server-authoritative; request submission does not imply cancelled/refunded.
