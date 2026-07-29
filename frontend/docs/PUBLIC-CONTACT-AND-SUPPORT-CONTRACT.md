# Public Contact and Support Contract (JP-FE-13)

## Contact page (`/contact`)

- **Presentation:** Next.js
- **Contact details:** `GET /api/public/content/site-contact` → `ClientGlobalContactResolver`
- **Form endpoint:** `POST /support` with `form_type=contact`
- **Fields:** `name`, `email`, `body` (required); honeypot `website` prohibited
- **Turnstile:** `cf-turnstile-response` when `TurnstileVerifier::isEnabled()`
- **Rate limit:** `throttle:10,1` on POST
- **Success:** JSON `{ ok: true, ticket_reference }` only after Laravel acceptance
- **Blade fallback:** `/laravel/support` when Turnstile script fails

## Support page (`/support`)

- **Presentation:** Next.js
- **Content:** `GET /api/public/content/pages/support`
- **Categories:** `GET /api/public/content/support/categories`
- **Form:** `form_type=support` with `subject`, `category`, optional `booking_reference`
- **Turnstile / CSRF / rate limit:** same as contact

## Security

- No PII in URLs, `localStorage`, or `sessionStorage`
- No direct email from Next.js
- No fake ticket references
- Widget reset on Laravel Turnstile rejection
