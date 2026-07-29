# JP-FE-10A — Booking Lookup Turnstile Production Security and Guest Access Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-10A-BOOKING-LOOKUP-TURNSTILE-PRODUCTION-SECURITY-AND-GUEST-ACCESS-CLOSURE |
| Branch | `phase/jetpk-fe-10a-booking-lookup-turnstile-closure` |
| Baseline | `c5446b7` (JP-FE-10) |
| Feature commit | `b621bd8` |
| Merge commit | `f38c720` |
| Final status | COMPLETE (pending SHA docs commit) |

## Problem

JP-FE-10 shipped a Next.js `/lookup-booking` page without embedding Cloudflare Turnstile. Laravel still requires `cf-turnstile-response` when `TURNSTILE_ENABLED=true`, so production lookup would fail validation.

## Solution

1. Additive Laravel public config: `GET /api/public/content/turnstile-config`
2. Shared frontend module: `frontend/features/security/turnstile/`
3. Production-compatible lookup form with widget lifecycle, token reset, guest redirect validation, Blade fallback

## Included scope

- Public Turnstile site-key contract (no secret exposure)
- `TurnstileWidget`, `useTurnstileToken`, unavailable/fallback states
- `BookingLookupPage` Turnstile integration
- `booking-lookup-service` with generic failure, rate-limit, token rejection handling
- Safe guest redirect allowlist (`/guest/bookings/{id}/access/{token}`)
- Blade fallback link `/laravel/lookup-booking`
- Laravel + Playwright targeted tests

## Excluded scope

- Support form Turnstile (still Blade-only; shared module ready for future use)
- Guest show migration
- Lookup JSON success API (redirect contract preserved)

## Files changed

### Laravel
- `app/Http/Controllers/Api/PublicContentApiController.php`
- `routes/web.php`
- `tests/Feature/PublicTurnstileConfigTest.php`

### Frontend
- `frontend/features/security/turnstile/**`
- `frontend/features/standard-booking/lookup/BookingLookupPage.tsx`
- `frontend/features/standard-booking/lookup/booking-lookup-service.ts`
- `frontend/features/standard-booking/lookup/guest-redirect.ts`
- `frontend/tests/booking-lookup-turnstile.spec.ts`

### Documentation
- `frontend/docs/BOOKING-LOOKUP-AND-GUEST-ACCESS-CONTRACT.md`
- `frontend/docs/PUBLIC-CONTENT-ARCHITECTURE.md`
- `frontend/docs/BOOKING-SUCCESS-AND-POST-BOOKING-ARCHITECTURE.md`

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass |
| Playwright `booking-lookup-turnstile.spec.ts` | 7/7 |
| `php artisan test tests/Feature/PublicTurnstileConfigTest.php` | 4/4 |

## Known limitations

- Support/contact Next.js forms do not yet use shared Turnstile module (Blade support form unchanged)
- Rate-limit UI uses generic message (no retry-after countdown)

## No-deployment confirmation

Production untouched.

## Next phase

JP-FE-11-CUSTOMER-DASHBOARD-BOOKINGS-PAYMENTS-INVOICES-PROFILE-SUPPORT-AND-NOTIFICATIONS
