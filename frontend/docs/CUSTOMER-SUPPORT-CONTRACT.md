# Customer Support Contract

## Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/customer/support/tickets?format=json` | List owned tickets |
| GET | `/customer/support/tickets/create?format=json` | Categories + bookable bookings |
| POST | `/customer/support/tickets` | Create ticket |
| GET | `/customer/support/tickets/{ticket_reference}?format=json` | Detail + conversation |
| POST | `/customer/support/tickets/{ticket_reference}/reply` | Customer reply |
| PATCH | `/customer/support/tickets/{ticket_reference}/close` | Close ticket |

## Turnstile

- **Authenticated customer support:** Turnstile **not** required (`StoreSupportTicketRequest`).
- **Public support/contact:** Turnstile required (`StorePublicSupportTicketRequest`).

Next.js customer support form does not bypass Laravel policy.

## Fields

Create: `subject`, `category`, `body`, optional `booking_id`.

Reply: `body` only when policy allows.

## Rate limiting

Inherited from Laravel web middleware and support policies. JSON returns generic errors on failure.

## Attachments

Not exposed in customer dashboard (no complete backend contract).
