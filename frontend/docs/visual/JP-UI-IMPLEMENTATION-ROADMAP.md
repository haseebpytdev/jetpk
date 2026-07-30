# JP-UI Implementation Roadmap (JP-UI-02 – JP-UI-06 + JP-OPS)

Phase: **JP-UI-01** (evidence source)  
Baseline: `5fad262`

---

## JP-UI-02 — SHARED-DESIGN-SYSTEM-DAY-NIGHT-THEME-TYPOGRAPHY-SHELL-HEADER-FOOTER-IMAGE-SLOTS-AND-FOUNDATION

**Status:** Complete (see `docs/phases/JP-UI-02-*-SUMMARY.md`)

**Objective:** Shared visual foundation before page-level parity work.

### Pages
- All public routes (shell only)
- Checkout shell chrome
- Dashboard shells (token alignment, not full parity)

### Components
- `ThemeProvider`, `ThemeSwitch`
- Tokenized colors (light + dark)
- `SiteHeader` / `SiteFooter` geometry per mockup
- `PublicPageContainer` / `SectionContainer` consolidation
- `ImageSlot`, `EmptyState`, `ErrorState`, `LoadingSkeleton` base
- Typography scale refinement (compact density)

### Dependencies
- JP-UI-01 audit (this phase)
- No Laravel behavior changes

### Backend constraints
- Nav/social may remain static until JP-UI-03 CMS wiring

### Acceptance criteria
- See JP-UI-02 section in `RESPONSIVE-ACCESSIBILITY-AND-VISUAL-ACCEPTANCE-CRITERIA.md`

### Tests
- Theme hydration smoke
- Header/footer Playwright shell tests
- `typecheck`, `lint`, `build`

### Deferred
- Full homepage hero
- Results card redesign

---

## JP-UI-03 — HOMEPAGE-ABOUT-SUPPORT-PUBLIC-CMS-AND-COMPACT-HERO-SEARCH-VISUAL-PARITY

**Status:** Complete (see `docs/phases/JP-UI-03-*-SUMMARY.md`)

**Objective:** Public marketing and CMS pages match mockups #1, #2, #3.

### Pages
- `/`, `/about-us`, `/support`, `/contact`, `/faq`, legal, CMS slugs

### Components
- `HomepageHero` rebuild
- `SearchModule` compact single-row desktop variant
- `DestinationsSection`, `FeaturedOffersSection`, `WhyJetPakistanSection`, `TravelInspirationSection`
- `BenefitStrip`, `SupportCard`, `CMSSectionRenderer`

### Dependencies
- JP-UI-02 tokens and shell

### Backend constraints
- Replace `features/home/fixtures/*` with Laravel public content APIs
- No invented statistics or emergency claims

### Acceptance criteria
- Homepage compact search above fold at 1440×900
- Section order matches mockup #1
- CMS-driven destinations/offers/articles

### Tests
- `homepage.spec.ts`, `public-content.spec.ts`
- Visual capture diff vs JP-UI-01 baseline

### Deferred
- Hotels/Offers nav unless JP-OPS approves routes

---

## JP-UI-03A — DARK-SYSTEM-RESPONSIVE-INTERACTION-STATE-VISUAL-MATRIX-AND-FINAL-PARITY-CLOSURE

**Status:** Complete (see `docs/phases/JP-UI-03A-*-SUMMARY.md`)

**Objective:** Complete JP-UI-03 visual evidence across themes, viewports, zoom, and interaction states; fix evidenced defects only.

### Scope
- 119-scenario visual matrix (`npm run audit:visual:jp-ui-03a`)
- Theme/system/dark/mobile/zoom/interaction captures
- Horizontal overflow + hydration gates
- `ThemeProvider` hydration fix

### Out of scope
- JP-UI-04 booking-flow redesign

### Tests
- `jp-ui-03a-theme-matrix.spec.ts`
- `homepage.spec.ts`, `public-content.spec.ts`, `jp-ui-02-theme.spec.ts` (regression)

---

## JP-UI-04 — FLIGHT-RESULTS-FARE-SELECTION-PASSENGERS-SEATS-REVIEW-PAYMENT-SUCCESS-VISUAL-PARITY

**Objective:** Booking journey family matches mockups #13, #11, #4, #8, #10, #5.

### Pages
- `/flights/results`, return options, `/booking/passengers`, `/booking/review`, `/booking/payment/*`, `/booking/confirmation`

### Components
- `FlightResultCard`, `ResultsFilterPanel`, `ResultsToolbar`, sort tabs
- `BrandedFareCarousel`, `FareFamilyCard`, `FlightDetailsDrawer`
- `BookingProgress` v2 (shared)
- `OrderSummary` unified
- `PassengerForm`, `PaymentMethodSelector`, `ManualPaymentPanel`, `StatusCard`
- `MobileStickyAction`
- `SeatMap` — **only if** JP-OPS enables `seat_map_available`

### Dependencies
- JP-UI-02 foundation

### Backend constraints
- Progress from Laravel only
- Seats hidden when unsupported
- No fake fares or seat numbers

### Acceptance criteria
- See JP-UI-04 section in acceptance criteria doc
- Match ratings target ≥4 on results and checkout

### Tests
- `flight-results.spec.ts`, `flight-details.spec.ts`, `standard-booking-*.spec.ts`
- Visual capture regression

### Deferred
- Dedicated `/fare-selection` route (optional; may remain inline)

---

## JP-UI-05 — LOGIN-SIGNUP-MANAGE-BOOKING-CUSTOMER-AGENT-AND-DASHBOARD-VISUAL-PARITY

**Objective:** Auth and portal surfaces match mockups #6, #7, #9; dashboard alignment.

### Pages
- `/login`, `/register`, `/forgot-password`, `/lookup-booking`
- `/customer/*`, `/agent/*` (visual shell)

### Components
- Auth split layout + illustration slots
- `BookingLookupPage` hero and cards
- Dashboard shell sidebars, cards, tables

### Dependencies
- JP-UI-02 theme and typography

### Backend constraints
- Social login visibility from Laravel
- OTP demo unchanged
- Lookup Turnstile authoritative

### Tests
- `auth.spec.ts`, `booking-lookup-turnstile.spec.ts`, dashboard specs

### Deferred
- Full dashboard feature parity (JP-OPS)

---

## JP-UI-06 — ASSETS-ANIMATIONS-RESPONSIVE-ACCESSIBILITY-SCREENSHOT-DIFF-AND-FINAL-VISUAL-CLOSURE

**Objective:** Production assets, motion, responsive closure, visual diff gate.

### Scope
- Photo/illustration assets per slot audit
- `AnimatedFlightPath` extensions, success animation
- 320–1440 responsive verification
- Playwright screenshot diff vs JP-UI-01 + mockup geometry
- WCAG contrast dark/light
- Contact sheets and parity sign-off

### Tests
- Full `audit:visual:jp-ui-01` + diff tooling
- Accessibility spot checks
- Reduced-motion suite

### Deferred
- None for public/booking visual closure

---

## JP-OPS-01 onward — Operational Laravel closure

**After UI phases stabilize:**

- Public nav CMS (Hotels/Offers/Services decisions)
- Newsletter, offers API
- Seat map supplier integration
- OAuth providers
- Payment/webhook edge cases
- Route health, queue, email internals
- Dashboard operational completeness

Each JP-OPS phase must reference finalized UI contracts from JP-UI-06.

---

## Dependency graph

```mermaid
flowchart LR
  UI01[JP-UI-01 Audit]
  UI02[JP-UI-02 Foundation]
  UI03[JP-UI-03 Public/CMS]
  UI04[JP-UI-04 Booking]
  UI05[JP-UI-05 Auth/Portals]
  UI06[JP-UI-06 Closure]
  OPS[JP-OPS-01+]

  UI01 --> UI02
  UI02 --> UI03
  UI02 --> UI04
  UI02 --> UI05
  UI03 --> UI06
  UI04 --> UI06
  UI05 --> UI06
  UI06 --> OPS
```
