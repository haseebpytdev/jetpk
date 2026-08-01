# JP-FRONTEND Journey Progress Contract

## Authority

Laravel `progress[]` arrays drive step state and href. Next.js displays only.

## Steps

Search → Results → Fare Selection → Travelers → Review → Payment → Success

Seats omitted when `seat_map_available=false` (no `/booking/seats` route).

## Per-route progress source (UX-02A audit)

| Route | Progress source | Authoritative? |
|---|---|---|
| Fare Selection | Static `progressSteps` in `FareSelectionPage.tsx` | **Display-only** (see below) |
| Travelers | `context.booking_session.progress` from `fetchStandardPassengersContext` | Yes |
| Review | `context.booking_session.progress` from `fetchBookingReview` | Yes |
| Manual/Card Payment | `state.booking_session.progress` from `fetchCheckoutState` | Yes |
| Payment Status | No progress bar; status from `fetchPaymentStatus` poll | Yes |
| Confirmation | `confirmation.booking_session.progress` from poll/API | Yes |
| Groups | `booking.progress` / `context.progress` from group APIs | Yes |

## Fare Selection display-only clarification (non-blocking)

**What is display-only:** On `/flights/fare-selection`, the `BookingProgress` stepper uses a hardcoded `progressSteps` array (Search/Results completed, Fare Selection current, later steps upcoming). This array is passed only to the `BookingProgress` render component.

**What it cannot do:**
- It is not written to localStorage/sessionStorage
- It does not gate navigation or API calls
- Upcoming steps have no `href` — `BookingProgress` only links completed steps (`step.href && isComplete`)
- Travelers cannot be unlocked by URL or client progress state

**Access to Travelers requires:** `useRevalidation.continueToPassengers()` → `POST /flights/results/revalidate-offer` (or return-combo handoff) → Laravel returns `passengers_url` → `window.location.assign` to allowed handoff URL. Failure leaves `revalidation.state` in error/expired/unavailable and does not navigate.

**Passenger page resume:** `fetchStandardPassengersContext(searchParams)` loads Laravel session; invalid/missing session shows error states, not client-inferred progress.

## Journey-related client storage audit

| Mechanism | Booking journey use |
|---|---|
| `localStorage` | Theme preference only (`jp-theme-preference`) — not journey |
| `sessionStorage` | Not used for booking |
| `completedSteps` / `currentStep` | Not present in booking code |
| `searchParams` | Fare Selection: `search_id`, `offer_id` for offer load only; Passengers: session lookup key; Payment Status: `reference` optional — never sets Paid/Success |
| `pathname` | Route location only |

## Rules

1. Advance progress only after server-confirmed step success
2. Never infer completion from URL or localStorage
3. Future steps not clickable (`BookingProgress` enforces `href` only when `state === "completed"`)
4. Completed steps may link back when Laravel provides `href`
5. `BookingProgress` connector uses `jp-progress-fill` animation after confirmed state
6. Route navigation progress (`RouteNavProgress`) is separate UI and never represents business completion

## Validation failure behavior

- Fare revalidation failure: `useRevalidation` sets error state; hardcoded display progress unchanged; no navigation
- Passenger submit failure: `submitLock` released; `context.booking_session.progress` unchanged (Laravel response)
- Review submit failure: `submitLock` released; progress from Laravel context unchanged
- Payment: status label from `payload.payment_status` API only

## Refresh/resume

Derive from Laravel booking/session APIs on page load. Client does not maintain `completedSteps`.
