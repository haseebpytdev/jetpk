# Flight Details, Fare Family, and Revalidation Visual Contract (JP-UI-04)

## Scope

Inline fare comparison, flight details expansion, and offer revalidation on the results journey. Mockup reference: **#11**.

## Fare selection authority (JETPK-UI-04)

| Surface | When | Purpose |
|---------|------|---------|
| `BrandedFareCarousel` (inline on result card) | Preview on `/flights/results` | Quick fare-family preview; book navigates to dedicated route when branded |
| `/flights/fare-selection` (`FareSelectionPage`) | `has_branded_fares` with >1 family | Full-page mockup #11 comparison with segment detail and order summary |
| `FlightDetailsDrawer` | “Details” on result card | Segment timeline, baggage, fare rules; not primary fare comparison |

When more than three fare families are present, use a contained horizontal carousel (chevron navigation) — never wrap a fourth card to a new row.

Authority module: `frontend/lib/fare-selection-authority.ts`

## Entry points

- `FlightDetailsDrawer` — segment timeline, baggage, fare rules summary
- `BrandedFareCarousel` — horizontal fare family cards when `has_branded_fares: true`
- `FareSelectionPage` — dedicated branded fare comparison route
- Offer selection via `useOfferSelection` hook (non-branded direct book)

## Fare family carousel

- Renders only when Laravel indicates branded fares on the offer
- Each card: fare family name, price delta, key inclusions, radio/select affordance
- `data-testid="branded-fare-carousel"`
- No invented fare families or prices
- Selection updates offer ID and triggers revalidation when required

## Flight details drawer

- Triggered from result card “Details” or fare comparison context
- Segment rows: departure/arrival times, airports, duration, stops, aircraft when provided
- Focus trap and `aria-modal` semantics preserved
- Returns focus to trigger element on close

## Revalidation states

| State | UI behavior |
|-------|-------------|
| Price unchanged | Silent continue to passengers |
| Price changed | Laravel-provided message; user must confirm |
| Offer expired | `ExpiredSearchState` or inline expiry notice |
| Offer unavailable | Error state with return-to-results action |

## Pair view (return flights)

- Outbound selection may show `OutboundOptionCard` before return options
- Combined outbound+return card when Laravel provides round-trip offer shape
- Card density varies by supplier response — UI does not fabricate missing leg

## Visual audit scenarios

| ID | State | Notes |
|----|-------|-------|
| res-11 | Branded fare carousel visible | 1440 light |
| res-12 | Expired search | Revalidation failure path |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Fare family names, inclusions | D | Laravel offer JSON |
| Segment times, airports | D | Laravel |
| Revalidation messages | D | Laravel |
| Drawer UI labels | B | Frontend vocabulary |

## Deferred

- Always-visible segment timeline without drawer interaction on results cards
