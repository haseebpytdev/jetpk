# Agent Bookings Contract

## Routes

| Method | Laravel path | Next.js path |
|--------|--------------|--------------|
| GET | `/agent/bookings?format=json` | `/agent/bookings` |
| GET | `/agent/bookings/{booking_reference}?format=json` | `/agent/bookings/[reference]` |

Route binding uses `{booking:booking_reference}` — public booking reference, not internal ID.

## Authorization

- Requires `agent.bookings.view` (`AgentPermission::BookingsView`)
- Ownership: booking `agent_id` must match authenticated user's agency agent
- Cross-agency detail returns 403

## Bookings list

Query parameters:

- `filter` — `all`, `pending_payment`, `pnr_created`, `needs_action`, `cancelled`
- `page`, `per_page` — pagination

Each item includes:

- `booking_reference`, `booking_date`, `trip_type`, `route`, `departure_date`, `airline`
- `passenger_count`, `total`, `currency`
- `booking_status`, `payment_status`, `ticketing_status` — `{ code, label }` objects
- `pnr` when present
- `booking_type` — `standard` or `group_ticketing`
- `creator_name` — from `meta.creator_context` when set (staff attribution)
- `detail_url` — `/agent/bookings/{booking_reference}`
- `next_action` — `{ code, label, url }`
- `commission` — owner only, when commission entry exists

### Group ticketing identification

```php
$bookingType = data_get($meta, 'group_booking_id') !== null ? 'group_ticketing' : 'standard';
```

When `meta.group_booking_id` is present, list/detail mark the booking as group ticketing. Detail payload still flows through the standard booking JSON presenter.

## Booking detail

`GET /agent/bookings/{booking_reference}?format=json`

Reuses `AgentPortalBookingDetailPresenter` wrapping JP-FE-10 `StandardBookingJsonPresenter::presentConfirmation` with agent-portal action URLs.

Loaded relations: passengers, contact, fare breakdown, payments, tickets, documents, supplier bookings, cancellation requests, refunds, commission entries.

Ownership enforced via existing booking policies before JSON is emitted.

## Filters

| Filter | Semantics |
|--------|-----------|
| `all` | All agency bookings |
| `pending_payment` | Unpaid/partial payment or `PaymentPending` status |
| `pnr_created` | PNR present |
| `needs_action` | Requires agent follow-up per portal rules |
| `cancelled` | Cancelled bookings |

## Pagination shape

```json
{
  "current_page": 1,
  "last_page": 3,
  "per_page": 20,
  "total": 42,
  "from": 1,
  "to": 20
}
```

## Dashboard cross-references

Overview JSON includes `recent_bookings`, `upcoming_booking`, and `first_pending_payment_booking` using the same status presenters.

## Excluded in Next.js phase

- Booking creation (`/agent/bookings/create`) — Blade + flight search entry
- Payment proof upload — POST remains Laravel form/API
- Cancellation submit — POST to `/agent/bookings/{ref}/cancellations` (Blade or future phase)

## Blade fallback

`GET /agent/bookings` and `GET /agent/bookings/{booking_reference}` without `format=json` render existing Blade views.
