# Booking lookup and guest access contract (JP-FE-10)

## Lookup form

| Field | Required | Notes |
|-------|----------|-------|
| `booking_reference` | yes | Public booking reference |
| `email` | yes | Must match booking contact |
| `phone` | no | Optional additional verification |
| `cf-turnstile-response` | when enabled | Server-side `TurnstileVerifier` |

## Endpoints

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/lookup-booking` | Next.js lookup form (Blade fallback preserved) |
| POST | `/lookup-booking` | Laravel validation + rate limit `throttle:lookup-booking` |

## Success path

On match: redirect to `/guest/bookings/{booking}/access/{token}` (opaque token, ~30 min TTL).

On failure: generic error — "Booking not found for the provided reference and email." No enumeration.

## Guest document download

`GET /guest/documents/{bookingDocument}/download?token=...` — ownership via guest token.

## Security controls preserved

- Rate limiting
- Turnstile when configured
- CSRF on POST
- Generic failure messages
- No PII in query strings
- No localStorage authority
