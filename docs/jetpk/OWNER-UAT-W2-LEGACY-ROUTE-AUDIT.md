# OWNER UAT W2-23 — Legacy Presentation / Fallback Route Audit

LAST_UPDATED_UTC: 2026-08-12T21:25:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
SCOPE: User-facing legacy Blade presentation handoffs (Laravel domain/API remains)

## Policy

- Laravel domain/API/services remain authoritative.
- User-facing links/copy that send people to Blade/legacy presentation must be zero.
- Bookmark-safe GET presentation URLs may redirect to modern Next routes.
- Payment provider starts / mutation POSTs via `/laravel/*` transport are allowed when they are not Blade page handoffs.

## Findings and fixes

| SOURCE | ACTOR | CURRENT PAGE | CURRENT ACTION | LEGACY DESTINATION | INTENDED MODERN | BACKEND | AUTH/RBAC | STATUS | FIX | TEST | PRODUCTION PROOF |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `BookingLookupPage.tsx` | Public | `/lookup-booking` | “Use secure Blade lookup” | `/laravel/lookup-booking` | Stay on `/lookup-booking` | POST `/laravel/lookup-booking` | Public + Turnstile | FIXED | Removed nav link | Playwright turnstile | Form present; blade testId=0; no Blade copy |
| `TurnstileUnavailableState` | Public | Lookup / Contact | Blade recovery | `/laravel/lookup-booking`, `/laravel/support` | `/support` + retry | — | Public | FIXED | Recovery → support/retry | Playwright | No Blade recovery CTA |
| `GuestBookingLookupController::showLookupForm` | Public | GET `/laravel/lookup-booking` | Blade form | Blade view | `/lookup-booking` | Redirect 302 away(app.url) | Public | FIXED | Redirect to Next public URL | curl max-redirs 0 | 302 → `https://jetpakistan.pk/lookup-booking` |
| `GuestBookingLookupController::showGuestBooking` | Public | GET HTML guest | Blade show | Blade view | `/guest/bookings/{id}/access/{token}` | JSON kept | Token | FIXED | HTML → Next redirect | php -l | Deployed with away() |
| `GuestBookingDetailPage.tsx` | Public | Guest detail | “View secure Blade booking page” | `blade_fallback_url` | Modern guest page | JSON API | Token | FIXED | Link removed | guest-booking-detail.spec | Deployed FE |
| `GuestBookingDetailPage.tsx` | Public | Guest detail | AbhiPay CTA | Laravel payment start | Same URL, modern label | Payment start | Token | FIXED | “Continue to card payment” | guest-booking-detail.spec | Label not Blade |
| Agent travelers / finance / ledger pages | Agent | Those modules | “Blade fallback” | `/laravel/agent/...` | Next pages | JSON API | Agent | FIXED | Links removed | Feature suite | Deployed FE |
| `/laravel/api/*`, CSRF, CSV export | Mixed | — | API/transport | `/laravel/...` | Keep | Domain | Scoped | KEEP | Not presentation | — | — |
| Bare `/groups` | Public | typed URL | access-denied | — | `/groups/search` | Next redirect | Public | FIXED | Hub page redirect | Deploy + browser | This batch |

## Required gates

| Gate | Result |
|---|---|
| MANAGE_BOOKING_MODERN_PRESENTATION | **PASS** |
| MANAGE_BOOKING_LEGACY_FALLBACKS | **0** |
| BOOKING_LOOKUP_MODERN_PRESENTATION | **PASS** |
| LEGACY_PRESENTATION_FALLBACKS | **0** (user-facing audited) |
| LEGACY_BLADE_USER_LINKS | **0** |
| BROKEN_FALLBACK_LINKS | **0** |
| OLD_PORTAL_HANDOFFS | **0** |
| LEGACY_ERROR_FALLBACKS | **0** |
| MODERN_ERROR_RECOVERY | **PASS** (support + retry) |

## Remaining non-blocking

- Laravel JSON may still emit unused `blade_fallback_url` fields; UI does not render them as Blade CTAs.
- Rename API field `blade_fallback_urls.abhipay_start` → payment start capability (cosmetic schema).
