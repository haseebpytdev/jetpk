# JP-OPS-05 Booking Operations Contract

## Reads (connected)

- `GET /api/dashboard/bookings`
- `GET /api/dashboard/bookings/{id}`

## Safe operational mutations (JSON on portal routes)

| Action | Endpoint | Notes |
|--------|----------|-------|
| Payment verify/reject | `/admin|staff/bookings/payments/{id}/verify|reject` | Review only |
| Cancellation approve/reject | `/admin|staff/bookings/cancellations/{id}/approve|reject` | Review only; no supplier execution |
| Booking notes/status | Blade fallback retained | Next UI deferred |

## Explicitly deferred (JP-OPS-06 / Blade)

- Supplier booking creation
- Manual PNR fabrication
- Live ticketing (`issue-ticket`)
- Cancellation `process`

## Presenter rules

- No raw supplier payload in dashboard JSON
- Capabilities returned per record where connected
