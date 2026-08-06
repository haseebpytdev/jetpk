# JP-FULLSTACK-01E — Guest Lookup Follow-on and Customer Portal Verification

**Phase:** JP-FULLSTACK-01E
**Branch:** `phase/jetpk-fullstack-01e-guest-lookup-customer-portal-verification`
**Baseline SHA:** `b492b60a69bac98c87dbe09d216c2cd20b71e34a`
**Objective:** Close GAP-002 (authoritative Next guest detail), verify GAP-012 (customer payments) and GAP-015 (profile/security).

## Included scope

- Additive `GET guest.bookings.show?format=json` guest-safe JSON contract
- Additive `POST lookup-booking` JSON `redirect_url` when `wantsJson()`
- Canonical Next route `/guest/bookings/{booking}/access/{token}`
- Guest cancellation and payment-proof JSON mutations consumed by Next
- Lookup redirect allowlist for internal Next guest paths
- Customer payments ownership Laravel tests + Playwright states
- Customer profile/security mutation Playwright matrix

## Excluded scope

- AbhiPay callback, promo apply/remove, guest AbhiPay start (Blade fallback)
- Checkout, supplier, ticketing, cancellation approval engines
- Agent, CMS, notifications, dashboard, OTP demo, env/config changes

## GAP closures

### JP-FS01-GAP-002 (HIGH → CONNECTED_AND_VERIFIED)

**Laravel contracts**

| Route | Method | Response |
|-------|--------|----------|
| `guest.bookings.show` | GET | HTML Blade **or** `?format=json` guest-safe JSON |
| `lookup-booking.submit` | POST | HTML redirect **or** JSON `{ ok, redirect_url }` when `wantsJson()` |
| `guest.bookings.cancellations.store` | POST | JSON when `?format=json` |
| `guest.bookings.payment-proof` | POST | JSON when `?format=json` |

**Next**

- `frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx`
- `frontend/features/guest-booking/components/GuestBookingDetailPage.tsx`
- `frontend/features/guest-booking/components/GuestCancellationPanel.tsx`
- `frontend/features/guest-booking/components/GuestPaymentProofPanel.tsx`
- `frontend/features/guest-booking/services/guest-booking-api.ts`

**Controls:** `GuestBookingAccessService` token validation; generic 403 denial; token only in URL; no client storage; `resolveSafeGuestLookupRedirect` internal allowlist.

**Blade fallback:** HTML guest show, AbhiPay start, promo apply/remove.

**Tests:** `GuestBookingDetailJsonTest` (9), `guest-booking-detail.spec.ts` (3), `booking-lookup-turnstile.spec.ts` (8 incl. canonical redirect).

### JP-FS01-GAP-012 (LOW → CONNECTED_AND_VERIFIED)

**Scope:** `/customer/payments`, `customer.payments.index` JSON.

**Result:** No production defect. Laravel ownership isolation verified; Playwright populated, empty, server-error, session states.

**Tests:** `CustomerPaymentsJsonTest` (2), `jp-ops-03-customer-operational.spec.ts` payments (3).

### JP-FS01-GAP-015 (LOW → CONNECTED_AND_VERIFIED)

**Scope:** `/customer/profile`, `/customer/security`, PATCH profile, PUT password.

**Result:** No production defect. CSRF, bounded 419 retry, 422 validation, session recovery verified.

**Tests:** `jp-fullstack-01e-profile-security.spec.ts` (6).

## Test execution

| Command | Result |
|---------|--------|
| `php artisan test tests/Feature/Guest/GuestBookingDetailJsonTest.php tests/Feature/Customer/CustomerPaymentsJsonTest.php` | 9 passed, 45 assertions, exit 0 |
| `php artisan test tests/Feature/PublicTurnstileConfigTest.php` | 4 passed, 12 assertions, exit 0 |
| `php artisan test tests/Feature/Guest/GuestBookingLookupRedesignTest.php` | 7 passed, 46 assertions, exit 0 |
| `php artisan test tests/Feature/CustomerPortalAndGuestLookupTest.php --filter=guest_lookup` | 2 passed, 4 assertions, exit 0 |
| `php artisan test tests/Feature/Customer/CustomerPortalJsonContractTest.php --filter=profile` | 1 passed, 4 assertions, exit 0 |
| `php artisan test tests/Feature/Auth/ForcePasswordChangeJsonTest.php` | 10 passed, 23 assertions, exit 0 |
| `php artisan test tests/Feature/Auth/PasswordUpdateTest.php` | 2 passed, 8 assertions, exit 0 |
| `php artisan test tests/Feature/StandardBookingReviewJsonTest.php` | 8 passed, 70 assertions, exit 0 |
| `php artisan test tests/Feature/Payments/AbhiPayReturnHandoffTest.php` | 2 passed, 10 assertions, exit 0 |
| `npx playwright test tests/guest-booking-detail.spec.ts` | 3 passed, exit 0 |
| `npx playwright test tests/jp-fullstack-01e-profile-security.spec.ts` | 6 passed, exit 0 |
| `npx playwright test tests/booking-lookup-turnstile.spec.ts` | 8 passed, exit 0 |
| `npx playwright test tests/jp-ops-03-customer-operational.spec.ts` | 11 passed, exit 0 |
| `npm run typecheck` | pass, exit 0 |
| `npm run lint` | pass, exit 0 |
| `npm run build` | pass, exit 0 |

## Force-password regression baseline classification

Isolated command (01E inventory present):

`npx playwright test tests/jp-fullstack-01a-force-password.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0`

| Result | Value |
|--------|-------|
| Passed | 6 |
| Failed | 3 |
| Skipped | 0 |
| Exit code | 1 |

Failed tests (post-submit redirect did not leave `/password/force-change`):

- successful submission redirects to customer dashboard
- 419 csrf refresh retries once
- agent success redirects to agent dashboard

Clean-baseline comparison (stash `JP-FULLSTACK-01E force-password baseline comparison` at `b492b60a`, then identical command):

| Result | Value |
|--------|-------|
| Passed | 6 |
| Failed | 3 |
| Skipped | 0 |
| Exit code | 1 |

Same three failed test titles; same failure category (`toHaveURL` — expected portal redirect, remained on force-change page).

**Conclusion:** Pre-existing baseline exception (Classification B). 01E did not modify auth, password, OTP, or force-password production paths. Defect remains queued for separate regression repair; not waived as fixed.

## Known limitations

- Guest AbhiPay and promo mutations remain Blade handoff
- Lookup Blade form POST still uses HTML redirect (Next uses JSON `redirect_url`)
- Playwright uses mocked Laravel responses (no live supplier/booking/payment calls)

## Risks

- Lookup JSON redirect is new contract surface — Blade HTML path unchanged
- Production smoke should confirm real Laravel opaque-redirect + JSON paths on staging

## Rollback

Revert branch; no migrations; no env changes.

## Final status

**READY FOR JP-FULLSTACK-01E COMMIT WITH VERIFIED PRE-EXISTING FORCE-PASSWORD BASELINE EXCEPTION** (no commit performed in this phase pass).
