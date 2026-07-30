# Booking Review, Order Summary, and Consent Visual Contract (JP-UI-04)

## Scope

Pre-payment review at `/booking/review`. Mockup reference: **#8**.

## Page structure

1. `BookingPageShell` + `BookingProgress`
2. `BookingPageHeader` — “Review your booking”
3. `BookingLayout`:
   - Main: review sections (itinerary recap, travellers, policies, consent)
   - Sidebar: `OrderSummary` (sticky desktop)
   - Mobile: `MobileOrderSummary`
4. `BookingNavigationActions` — Back / Proceed to payment
5. `data-testid="booking-review-page"`

## Order summary (`OrderSummary`)

Unified component replacing separate `SelectedFlightSummaryCard` + `ReviewPriceBreakdown`:

| Section | Source |
|---------|--------|
| Route label | `SelectedFlightSummary.route_label` |
| Airline, flight number | Laravel itinerary |
| Depart / return dates | Laravel |
| Fare family | Laravel |
| Traveller count | Session |
| Price lines (base, taxes, fees, total) | `AuthoritativePricing` from Laravel |
| Payment status (if applicable) | Laravel |

- Desktop: sticky in `BookingSidebar`
- Mobile: collapsible `MobileOrderSummary`
- No invented discounts or totals

## Review main column

- `BookingSection` cards for itinerary, passengers, baggage, fare rules
- Policy blocks from Laravel text (not hardcoded legal copy)
- Consent checkbox(es) required before payment — labels from Laravel or CMS

## Selected flight summary card

- `SelectedFlightSummaryCard` delegates to `OrderSummary` for backward compatibility
- `ReviewPriceBreakdown` delegates to `OrderSummary` pricing section

## Responsive rules

| Breakpoint | Layout |
|------------|--------|
| ≥1024px | Two-column; sidebar sticky `top` offset below header |
| <1024px | Single column; mobile summary above form |
| 390 / 320px | No horizontal overflow; summary collapses |

## Visual audit scenarios

| ID | Viewport | Theme |
|----|----------|-------|
| rev-01 | 1440 | light |
| rev-02 | 1440 | dark |
| rev-03 | 390 | light |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Pricing, itinerary | D | Laravel review API |
| Policy text | D/C | Laravel / CMS |
| Section headings | B | Frontend vocabulary |
| Consent label text | D | Laravel |

## Deferred

- Denser policy accordion matching mockup illustration density
- Edit-flight inline action (unsupported operation)
