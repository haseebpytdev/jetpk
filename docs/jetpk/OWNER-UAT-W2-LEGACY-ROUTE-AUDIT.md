# OWNER UAT W2-23 — Legacy Presentation / Fallback Route Audit

LAST_UPDATED_UTC: 2026-08-12T21:10:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
SCOPE: User-facing legacy Blade presentation handoffs (Laravel domain/API remains)

## Policy

- Laravel domain/API/services remain authoritative.
- User-facing links/copy that send people to Blade/legacy presentation must be zero.
- Bookmark-safe GET presentation URLs may redirect to modern Next routes.
- Payment provider starts / mutation POSTs via `/laravel/*` transport are allowed when they are not Blade page handoffs.

## Findings and fixes

| SOURCE | ACTOR | CURRENT PAGE | CURRENT ACTION | LEGACY DESTINATION | INTENDED MODERN | BACKEND | STATUS | FIX |
|---|---|---|---|---|---|---|---|---|
| `BookingLookupPage.tsx` | Public | `/lookup-booking` | “Use secure Blade lookup” | `/laravel/lookup-booking` | Stay on `/lookup-booking` | POST `/laravel/lookup-booking` | FIXED | Removed nav link |
| `TurnstileUnavailableState` | Public | Lookup / Contact | Blade recovery link | `/laravel/lookup-booking`, `/laravel/support` | `/support` + retry | — | FIXED | Recovery → support/retry |
| `GuestBookingLookupController::showLookupForm` | Public | GET `/laravel/lookup-booking` | Blade form | Blade view | `/lookup-booking` | Redirect 302 | FIXED | Redirect to Next |
| `GuestBookingLookupController::showGuestBooking` | Public | GET HTML guest | Blade show | Blade view | `/guest/bookings/{id}/access/{token}` | JSON still Laravel | FIXED | HTML → Next redirect |
| `GuestBookingDetailPage.tsx` | Public | Guest detail | “View secure Blade booking page” | `blade_fallback_url` | Modern guest page already shown | JSON API | FIXED | Link removed |
| `GuestBookingDetailPage.tsx` | Public | Guest detail | AbhiPay CTA copy | Laravel payment start | Same URL, non-legacy label | Payment start | FIXED | Copy without “Blade” |
| `AgentTravelersPage.tsx` | Agent | Travelers | “Blade fallback” | `/laravel/agent/travelers` | Next travelers | JSON API | FIXED | Link removed |
| `AgentFinanceStatementPage.tsx` | Agent | Finance statement | “Blade fallback” | statement.blade_fallback_url | Next statement | JSON API | FIXED | Link removed |
| `AgentAccountingLedgerPage.tsx` | Agent | Accounting ledger | “Blade fallback” | ledger blade URL | Next ledger | JSON API | FIXED | Link removed |
| `/laravel/api/*`, login CSRF, finance CSV export | Mixed | — | API/transport | `/laravel/...` | Keep | Domain | KEEP | Not presentation |
| Comments in `allowlist.ts` / tokens | Dev | — | Code comments | — | — | — | KEEP | Non-user-facing |

## Required gates (this batch)

| Gate | Result |
|---|---|
| MANAGE_BOOKING_MODERN_PRESENTATION | PASS (Next `/lookup-booking`) |
| MANAGE_BOOKING_LEGACY_FALLBACKS | 0 (UI link removed; GET redirects modern) |
| BOOKING_LOOKUP_MODERN_PRESENTATION | PASS |
| LEGACY_PRESENTATION_FALLBACKS (scanned user strings) | 0 for listed actors in this batch |
| LEGACY_ERROR_FALLBACKS (Turnstile) | 0 |

## Remaining follow-ups (non-blocking if no user CTA)

- Broader crawl of all `/laravel/` anchors in production DOM (W2 route/link audit).
- Agent/customer deep pages that only appear when JSON fails — must use modern error recovery, not Blade.
- Optional: stop emitting `blade_fallback_url` from Laravel JSON payloads (API field can remain unused).

## Tests

- `frontend/tests/booking-lookup-turnstile.spec.ts` — script failure offers modern recovery, no Blade link
- `frontend/tests/guest-booking-detail.spec.ts` — no Blade fallback link; card payment handoff without Blade copy
- `php -l` GuestBookingLookupController
- `npx tsc --noEmit` frontend
