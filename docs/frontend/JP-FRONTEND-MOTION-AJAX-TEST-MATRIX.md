# JP-FRONTEND Motion AJAX Test Matrix

## Suite

`frontend/tests/jp-frontend-ux-02/` (17 tests, evidence capture excluded)

| File | Coverage |
|---|---|
| `motion.spec.ts` | Pre-arm visibility; scroll activation; reveal-once; reduced motion; no-JS visible; IO unavailable; no layout shift |
| `scroll-reveal-helpers.ts` | Shared reveal-all + marketing section assertions |
| `navigation-loading.spec.ts` | Internal nav; external links not intercepted |
| `api-client.spec.ts` | Error code + field error mapping |
| `forms-autocomplete.spec.ts` | Login keyboard focus; airport debounce |
| `loading.spec.ts` | Customer bookings skeleton; fare-selection route |
| `payment-portal.spec.ts` | Query param cannot create Paid; customer session |

## Evidence capture (local only, not committed)

```bash
cd frontend
npx playwright test -c playwright.jp-frontend-ux-02-evidence.config.ts
```

Output: `frontend/.evidence/jp-frontend-ux-02/*.png`

### UX-02B evidence corrections

- Homepage capture scrolls all `.jp-scroll-reveal` targets and asserts none remain hidden.
- Portal dashboard shots saved as `08-customer-dashboard-backend-error.png` and `09-agent-dashboard-backend-error.png`.
- Dark Login captured in a fresh browser context without agent session.
- Dark Agent dashboard saved separately as `11-dark-theme-agent-dashboard.png`.
- Loading skeleton captures: `12-customer-bookings-loading-skeleton.png`, `13-agent-bookings-loading-skeleton.png`.

### Scroll-reveal hardening (UX-02B)

| Behavior | Implementation |
|---|---|
| SSR / no-JS visible | Default `.jp-scroll-reveal { opacity: 1 }` without `--armed` |
| Enhancement gate | `.jp-scroll-reveal--armed` added in `observeRevealElement()` after hydration |
| IO unavailable | Immediate `revealElement()` |
| In-viewport failsafe | 600ms timeout reveals if still hidden and in viewport |
| Reduced motion | Immediate reveal; no arming |
| Unmount cleanup | Clears failsafe timer; disconnects observer |

## Authority proofs (code audit UX-02A)

### Journey
- Passengers/Review/Payment/Confirmation: `booking_session.progress` from Laravel JSON
- Fare Selection: display-only stepper; revalidation gates Travelers access
- No booking PII in localStorage (see `standard-booking-passengers.spec.ts`)
- `/booking/seats` returns 404 (route matrix)

### Duplicate mutation
| Action | Lock mechanism | File |
|---|---|---|
| Login | `submitting` + early return | `LoginForm.tsx` |
| OTP verify | `submitting` + early return | `OtpForm.tsx` |
| OTP resend | `resending` + cooldown | `OtpForm.tsx` |
| Search | `isSubmitting` disables form + `AbortController` | `SearchModule.tsx` |
| Fare revalidation | `inFlightRef` | `use-revalidation.ts` |
| Passengers | `submitLock` ref + `submitting` | `PassengerDetailsPage.tsx` |
| Booking create | `submitLock` ref + `submitting` | `BookingReviewPage.tsx` |
| Payment status refresh | `inFlightRef` + `reload()` | `useBookingStatusPoll.ts` |
| Customer/Agent forms | `submitting` + early return | profile/support/deposit pages |

Mutations are not auto-retried by `laravelRequest`. Stale reads: `requestIdRef` in `useFlightDetails`, `useAsyncAction`, airport search.

### Payment polling
- Interval: Laravel `poll.interval_ms` / `booking_poll.interval_ms`
- Max duration: client `DEFAULT_MAX_DURATION_MS` = 180000
- Max attempts: Laravel `poll.max_attempts`
- Terminal: `should_poll === false` or max attempts/duration
- Unmount: `cancelledRef` + `clearTimer`
- Duplicate poller: `inFlightRef` on `load()`
- Visibility: pauses poll timer when `document.hidden`; refreshes on visible
- Manual refresh: `reload()` resets attempts and calls `load()`
- Paid source: `payload.payment_status` from API only; test proves `?paid=1` does not set Paid label

## Commands

```bash
cd frontend
npm run typecheck
npm run lint
npm run build
npm run test:jp-frontend-ux-02
npx playwright test -c playwright.jp-full-next-frontend.config.ts --grep-invert "capture "
```

## UX-02B final results (2026-08-01)

| Gate | Result |
|---|---|
| typecheck | PASS |
| lint | PASS |
| build | PASS |
| test:jp-frontend-ux-02 | 17/17 PASS |
| Integration regression | 119/119 PASS |

## Regression

Run `test:jp-full-next-frontend` for integration-critical assertions.
