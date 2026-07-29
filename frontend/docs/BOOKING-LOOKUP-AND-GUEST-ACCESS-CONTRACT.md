# Booking lookup and guest access contract (JP-FE-10 / JP-FE-10A)

## Lookup form

| Field | Required | Notes |
|-------|----------|-------|
| `booking_reference` | yes | Public booking reference |
| `email` | yes | Must match booking contact |
| `phone` | no | Optional additional verification |
| `cf-turnstile-response` | when enabled | Server-side `TurnstileVerifier` |

## Turnstile public configuration

`GET /api/public/content/turnstile-config`

```json
{
  "enabled": true,
  "site_key": "public-site-key-only",
  "response_field": "cf-turnstile-response"
}
```

- Site key exposed only when `TurnstileVerifier::isEnabled()` is true
- Secret key never exposed
- Next.js `TurnstileWidget` loads official Cloudflare script with explicit render
- Submit disabled until token acquired when required
- Token reset on expiry, error, generic failure, and Laravel rejection
- Script failure shows Blade fallback link at `/laravel/lookup-booking`

## Endpoints

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/lookup-booking` | Next.js lookup form (Blade fallback at `/laravel/lookup-booking`) |
| POST | `/lookup-booking` | Laravel validation + rate limit `throttle:lookup-booking` |

## Success path

On match: redirect to `/guest/bookings/{booking}/access/{token}` (opaque token, ~30 min TTL). Next.js validates redirect URL before navigation.

On failure: generic error — "Booking not found for the provided reference and email." No enumeration.

## Guest document download

`GET /guest/documents/{bookingDocument}/download?token=...` — ownership via guest token.

## Security controls preserved

- Rate limiting (429 surfaced safely)
- Turnstile when configured (no JSON/Next.js bypass)
- CSRF on POST
- Generic failure messages
- No PII in query strings
- No localStorage/sessionStorage for tokens
- No token logging

## Customer dashboard boundary (JP-FE-11)

Authenticated customers use `/customer/bookings` (not guest-token flow). Guest lookup and Turnstile behavior unchanged.
