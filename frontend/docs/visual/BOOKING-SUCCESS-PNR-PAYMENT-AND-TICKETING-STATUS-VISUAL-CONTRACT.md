# Booking Success, PNR, Payment, and Ticketing Status Visual Contract (JP-UI-04)

## Scope

Post-booking confirmation at `/booking/confirmation`. Mockup reference: **#5**.

## Page structure

1. `BookingPageShell` + `BookingProgress` (Success step current/completed)
2. Status hero — tone-based banner from Laravel `booking_status` / `ticketing_status`
3. `BookingLayout`:
   - Main: confirmation details, next steps, support links
   - Sidebar: `OrderSummary` with final pricing and payment status
4. `data-testid="booking-confirmation-page"`

## Status display

| Laravel field | UI treatment |
|---------------|--------------|
| PNR / booking reference | Prominent monospace or emphasized block |
| Payment status | Badge + label from Laravel |
| Ticketing status | Badge (confirmed, pending, failed) — no fake “ticketed” |
| `show_celebration` | Reserved for JP-UI-06; no confetti unless Laravel authorizes |

## Content blocks

- Itinerary recap (from `SelectedFlightSummary`)
- Traveller names (from session — no re-fetch of full PII in URL)
- Payment receipt summary when provided
- “What happens next” steps from Laravel or static operational copy (**B**)
- Support / contact link to `/support`

## Rules

- **Never** display fake PNR or ticket numbers
- **Never** show “Ticket issued” unless Laravel `ticketing_status` confirms
- Confirmation accessible without JavaScript for SSR shell (hydration for interactive elements only)

## Responsive

| Viewport | Behavior |
|----------|----------|
| 1440 desktop | Two-column with sidebar summary |
| 390 mobile | Stacked; PNR block full width |
| 1280 @ 150% | No clipped CTA or reference block |

## Visual audit scenarios

| ID | State | Viewport | Theme |
|----|-------|----------|-------|
| suc-01 | Confirmed | 1440 | light |
| suc-02 | Confirmed | 1440 | dark |
| suc-03 | Confirmed | 390 | light |
| suc-04 | Confirmed | 1280 @ 150% | light |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| PNR, statuses, amounts | D | Laravel confirmation API |
| Support links | C | Public config |
| Status badge colors | A | Design tokens |

## Deferred (JP-UI-06)

- Success illustration / confetti animation
- Email confirmation preview block
- Download itinerary PDF button (when Laravel provides endpoint)
