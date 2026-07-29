# Booking success and post-booking architecture (JP-FE-10)

## Authority

Laravel remains authoritative for booking identity, status, payment, PNR, ticketing, invoice, documents, cancellation eligibility, and post-booking actions. Next.js renders presentation only.

## Session-scoped JSON endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /booking/confirmation?format=json` | Full post-booking confirmation payload (`presentConfirmation`) |
| `GET /booking/checkout-state?format=json` | Pre-confirmation checkout state |
| `GET /booking/payment/status?format=json` | Payment + booking + ticketing status with polling hints |
| `GET /booking/invoice?format=json` | Invoice presentation payload |

All require `ota_public_booking_id` checkout session unless noted.

## Next.js routes

| Route | Component |
|-------|-----------|
| `/booking/confirmation` | `BookingConfirmationPage` |
| `/booking/status` | Alias of confirmation |
| `/booking/payment/status` | `PaymentStatusPage` |
| `/booking/invoice` | `InvoicePage` |
| `/lookup-booking` | `BookingLookupPage` |

Blade fallbacks remain on Laravel for guest show, cancellation forms, and document downloads.

## Feature module layout

```
frontend/features/standard-booking/
├── success/BookingConfirmationPage.tsx
├── lookup/BookingLookupPage.tsx
├── itinerary/ItineraryTimeline.tsx
├── post-booking/PostBookingActions.tsx
├── hooks/useBookingStatusPoll.ts
├── utils/status-presentation.ts
└── components/{BookingStatusHero,StatusCards,PassengerSummary,...}
```

## Polling

- Payment status polls while `poll.should_poll` is true (max 40 attempts, 3s interval).
- Confirmation polls while `poll.should_poll` is true (max 45 attempts, 4s interval).
- Polling pauses while tab is hidden and resumes on visibility return.
- Terminal states stop polling.

## Guest access

Post-session access uses `/lookup-booking` → Laravel guest token URL (`/guest/bookings/{id}/access/{token}`). Next.js lookup form POSTs to Laravel with CSRF and Cloudflare Turnstile when enabled (JP-FE-10A).

## Security

- No PII in URLs or browser storage
- No client-side status promotion
- Post-booking action URLs allowlisted in `utils/allowlist.ts`
- Document downloads only when Laravel provides paths

## Customer dashboard reuse (JP-FE-11)

Authenticated customers view booking detail at `/customer/bookings/[reference]` using the same JSON confirmation payload and UI components (`ItineraryTimeline`, `PostBookingActions`, status cards). Laravel remains authoritative; allowlist extended for `/customer/*` paths.
