# JP-OPS-04 Agency Booking Contract

## List (`AgentPortalBookingsPresenter`)

`booking_reference`, route, dates, totals, `booking_status`, `payment_status`, `ticketing_status`, `pnr`, commission snippet when present, `detail_url`, pagination, active filter.

Scope: `agent_id = auth agent.id`.

## Detail (`AgentPortalBookingDetailPresenter` + standard booking JSON)

Confirmation payload plus server-driven `actions[]`:

```json
{
  "actions": [
    {
      "code": "request_cancellation",
      "label": "Request cancellation",
      "available": true,
      "url": "/laravel/agent/bookings/{ref}/cancellations",
      "reason_unavailable": null
    }
  ]
}
```

`AgentBookingCancellationPanel` reads `actions[].available` — frontend does not infer from booking status alone.

## Cancellation request

| Field | Value |
|-------|-------|
| Endpoint | `POST /agent/bookings/{booking_reference}/cancellations?format=json` |
| Auth | `BookingCancellationPolicy::request` + `agent_id` match |
| Body | `cancellation_type` (required enum), `reason` (optional) |
| Success | 201, `cancellation_request.status = requested` |
| Duplicate open request | 409, `code: cancellation_already_requested` |
| Already cancelled | 422, `code: booking_not_cancellable` |

**Request ≠ cancelled.** Booking `status` remains unchanged until staff/supplier processing.

## Payment proof

| Field | Value |
|-------|-------|
| Endpoint | `POST /agent/bookings/{ref}/payment-proof?format=json` |
| Auth | `BookingPolicy::submitPaymentProof` + `agent_id` match |
| Body | `method`, `amount`, `payment_reference`, `notes` |
| Success | 201, proof queued for review |
| Throttle | `throttle:payment-proof-submit` |

Live payment capture and wallet debit remain separate operational flows (not JP-OPS-04 scope).

## Booking create (GAP-012)

| Step | Endpoint | Response |
|------|----------|----------|
| Enter mode | `GET /agent/bookings/create?format=json` | `booking_mode_active: true`, `search_url: /flights/search` |
| Exit mode | `GET /agent/bookings/exit-mode?format=json` | `redirect_url: /agent/dashboard` |
| Session | `AgentBookingContext::activate/clear` | Links subsequent bookings to agency |

`POST /agent/bookings` is deprecated — redirects to create with notice to use flight search.

## Filters (index)

`all`, `pending_payment`, `pnr_created`, `needs_action`, `cancelled` — server-side only; invalid tab ignored.
