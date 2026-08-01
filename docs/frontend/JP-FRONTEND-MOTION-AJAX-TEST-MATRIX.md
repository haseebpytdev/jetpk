# JP-FRONTEND Motion AJAX Test Matrix

## Suite

`frontend/tests/jp-frontend-ux-02/` (12 tests, evidence capture excluded)

| File | Coverage |
|---|---|
| `motion.spec.ts` | Scroll reveal visible; reduced motion disables translation |
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

## UX-02A final results (2026-08-01)

| Gate | Result |
|---|---|
| typecheck | PASS |
| lint | PASS |
| build | PASS |
| test:jp-frontend-ux-02 | 12/12 PASS |
| Integration regression | 119/119 PASS (prior run) |

## Regression

Run `test:jp-full-next-frontend` for integration-critical assertions.
