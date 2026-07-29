# Booking status contract (JP-FE-10)

## Booking status (`booking_status`)

Authoritative enum: `App\Enums\BookingStatus`

Values: `draft`, `pending`, `fare_review`, `confirmed`, `payment_pending`, `paid`, `ticketing_pending`, `ticketed`, `cancelled`, `expired`, `failed`, `refunded`

JSON presentation (`mapBookingStatus`):

| Condition | Label | `terminal` |
|-----------|-------|------------|
| Ticketed / issued / `ticketed_at` | Confirmed | true |
| PNR present | Pending ticketing | false |
| Submitted | Pending | false |
| Otherwise | Draft | false |

## Payment status (`payment_status`)

Mapped from `PublicAbhiPayCheckoutPresenter` label:

| Label | Code | Terminal |
|-------|------|----------|
| Paid | `succeeded` | true |
| Payment pending | `pending` | false |
| Payment failed | `failed` | true |
| Other | `not_started` | false |

Payment status and booking status are always displayed separately.

## Ticketing status (`ticketing_status`)

Operational codes from `ticketing_status` column + ticket rows:

| Code | Label | Terminal |
|------|-------|----------|
| `ticketed` | Ticketed | true |
| `pending`, `ready` | Pending | false |
| `failed` | Failed | true |
| `not_supported` | Not supported | true |
| default | Not started | false |

## Success presentation matrix (`presentation`)

| State | Heading | Tone | Celebration |
|-------|---------|------|-------------|
| Cancelled | Booking cancelled | neutral | no |
| Failed | Booking requires attention | warning | no |
| Ticketed | Booking complete | success | yes |
| PNR present | Booking confirmed | success | no |
| Paid, processing | Payment received | processing | no |
| Manual unpaid | Booking request received | pending | no |
| Default | Booking request received | pending | no |

## PNR contract (`pnr_details`)

- `booking_reference`: uppercase PNR when `bookings.pnr` filled
- `airline_locator`: supplier reference when available
- `available`: true only when either field is authoritative

## Tickets (`tickets`)

Only rows with non-empty `ticket_number` are returned. Includes passenger name and `issued_at` when available.
