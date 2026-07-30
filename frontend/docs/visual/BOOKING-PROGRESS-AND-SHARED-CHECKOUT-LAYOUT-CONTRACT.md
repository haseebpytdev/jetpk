# Booking Progress and Shared Checkout Layout Contract (JP-UI-04)

## Scope

Shared checkout chrome for all post-results booking routes. Applies to mockups **#4, #8, #10, #5**.

## Module

`frontend/features/booking-layout/` — canonical owner of checkout layout primitives.

## Component hierarchy

```
BookingPageShell
├── SiteHeader (JP-UI-02)
├── BookingProgress
├── BookingPageHeader
├── BookingLayout
│   ├── MobileOrderSummary (mobile only)
│   ├── BookingMainColumn
│   │   └── BookingSection × n
│   └── BookingSidebar
│       └── OrderSummary
├── BookingNavigationActions
└── MobileStickyAction (mobile primary CTA)
```

## BookingProgress v2

- Entry: `features/booking-progress/components/BookingProgress.tsx`
- Horizontal connected stepper with numbered circles and labels
- `data-testid="booking-progress"`
- States: `completed`, `current`, `upcoming`, `skipped` (skipped not rendered)
- Completed steps with `href` render as links with `aria-label`
- Current step: `aria-current="step"`
- Compact mode on mobile: smaller circles; labels `sr-only`
- Light and dark token support
- `prefers-reduced-motion`: `motion-reduce:transition-none`

## Journey step labels

`BOOKING_JOURNEY_STEP_LABELS` in `journey-steps.ts` maps Laravel keys to display labels:

| Key | Label |
|-----|-------|
| search | Search |
| results / flight_selected | Results |
| passenger_details | Travelers |
| seat_extras | Seats |
| review | Review |
| payment | Payment |
| confirmation | Success |

Laravel `progress[].label` takes precedence when provided.

## Conditional seat omission

```typescript
visibleProgressSteps(steps) // filters state !== "skipped"
progressDisplayIndex(steps, key) // renumbers visible steps only
```

When `seat_map_available: false`, Laravel sends `seat_extras` as `skipped` — step absent from UI.

## BookingLayout grid

- `lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)]`
- Sidebar hidden below `lg`; `MobileOrderSummary` shown instead
- Gap: `gap-6`; top margin: `mt-6`

## State views

| Component | Use |
|-----------|-----|
| `BookingLoadingState` | Session fetch in progress |
| `BookingErrorBoundaryFallback` | React error boundary |
| `BookingSessionExpiredState` | Expired booking session |

## Pages using shared layout

| Page | Component |
|------|-----------|
| `/booking/passengers` | `PassengerDetailsPage` |
| `/booking/review` | `BookingReviewPage` |
| `/booking/payment/manual` | `ManualPaymentPage` |
| `/booking/payment/card` | `CardPaymentPage` |
| `/booking/confirmation` | `BookingConfirmationPage` |

Flight results (`/flights/results`) uses results-specific layout but shares `SearchSummaryBar` and public shell.

## Visual audit

| ID | Verification |
|----|--------------|
| prog-01 | Progress stepper on passengers page; no seat step when skipped |

## Accessibility checklist

- [x] `role="list"` on stepper container
- [x] `role="listitem"` on each step
- [x] Focus-visible rings on interactive completed steps
- [x] Skipped steps not in tab order
- [x] Sidebar does not trap focus
- [x] Mobile sticky action does not cover focused inputs

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Step state, href | D | Laravel `progress[]` |
| Display labels | B/D | Label map + Laravel |
| Layout spacing | A | Design tokens |
