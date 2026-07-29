# Post-booking actions and cancellation contract (JP-FE-10)

## Action eligibility

Laravel returns `actions[]` on confirmation JSON. Each action:

```json
{
  "code": "view_invoice",
  "label": "View invoice",
  "available": true,
  "url": "/booking/invoice",
  "reason_unavailable": null
}
```

Next.js renders only `available: true` actions with allowlisted URLs.

## Supported action codes (session checkout)

| Code | When available |
|------|----------------|
| `view_confirmation` | Always during session |
| `view_invoice` | Always during session |
| `contact_support` | Always |
| `download_*` | When generated document exists with download path |
| `my_bookings` | Authenticated customer |
| `lookup_booking` | Guest |
| `request_cancellation` | Authenticated customer without open cancellation request |

## Cancellation

Session checkout JSON reports eligibility only — execution remains on Laravel:

- Customer: `POST /customer/bookings/{booking}/cancellations`
- Guest: `POST /guest/bookings/{booking}/access/{token}/cancellations`

`cancellation` object on confirmation JSON:

- `eligible`: false for session flow (use portal/lookup)
- `request_pending`, `already_cancelled`, `message`

No refund amount or timeline invented in Next.js. `refund` object populated only when Laravel has refund rows.

## Resend email

Not exposed on public session JSON. Admin-only `resendFailedCommunication` unchanged.

## Sabre cancellation gates

`SABRE_CANCEL_*` environment flags unchanged. No live cancellation tests in this phase.
