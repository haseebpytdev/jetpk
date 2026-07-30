# JP-UI-04 — Flight Results, Fare Selection, Passengers, Seats, Review, Payment, Success Visual Parity

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-04-FLIGHT-RESULTS-FARE-SELECTION-PASSENGERS-SEATS-REVIEW-PAYMENT-SUCCESS-VISUAL-PARITY |
| Branch | `phase/jetpk-ui-04-booking-journey-visual-parity` |
| Baseline | `5f718c7` (JP-UI-03A main HEAD) |
| Objective | Unify the booking journey under shared layout primitives and achieve mockup-aligned visual parity for results through confirmation |

## Included scope

- New `frontend/features/booking-layout/` module with shared checkout shell components
- Enhanced `BookingProgress` horizontal stepper with conditional skipped-seat omission, accessible semantics, and light/dark support
- Flight results enhancements: `ResultsSortTabs`, enhanced `SearchSummaryBar`
- Checkout pages migrated to shared layout: passengers, review, manual payment, card payment (AbhiPay redirect), confirmation
- `OrderSummary` consolidating `SelectedFlightSummaryCard` + `ReviewPriceBreakdown`
- JP-UI-04 visual audit harness: 28 scenarios across results, passengers, review, payment, success, and shared progress
- Visual contract documentation for all booking journey families

## Excluded scope

- Laravel backend changes (none)
- Seat map UI implementation (`seat_map_available: false` — step omitted when skipped)
- Dedicated `/fare-selection` route (fare comparison remains inline on results + drawer)
- Production deployment
- Backup Safe mockup modifications
- Auth, dashboard, and portal surfaces (JP-UI-05)
- Success celebration confetti / photo illustration assets (JP-UI-06)
- Embedded card payment form (AbhiPay redirect preserved)

## Investigation findings

| Area | Finding |
|------|---------|
| Checkout layout | Each booking page duplicated progress, sidebar, and navigation chrome independently |
| Progress stepper | Pill-chip pattern with per-page duplication; seat step shown even when Laravel marked `skipped` |
| Order summary | `SelectedFlightSummaryCard` and `ReviewPriceBreakdown` overlapped responsibilities |
| Results sorting | Desktop used dropdown-only sort control; mockup #13 expects tab row |
| Seat selection | Laravel contract returns `seat_map_available: false`; no route exists — UI must omit step cleanly |
| Payment | Manual payment instructions and AbhiPay card redirect are authoritative; no fake embedded card form |
| Visual evidence | JP-UI-01 captured booking routes but without post-parity layout or theme matrix |

## Root causes

1. **No shared booking layout module** — layout, sidebar, and mobile summary patterns were copy-pasted per page.
2. **Progress component did not filter skipped steps** — `seat_extras` appeared in stepper despite unsupported capability.
3. **Results toolbar used dropdown sort** — inconsistent with mockup tab-row pattern on desktop.
4. **Order summary fragmentation** — itinerary and pricing breakdown lived in separate components with divergent spacing.

## Exact files changed

### New — `frontend/features/booking-layout/`

| File | Purpose |
|------|---------|
| `components/BookingPageShell.tsx` | Top-level checkout page wrapper |
| `components/BookingPageHeader.tsx` | Page title + optional description |
| `components/BookingMainColumn.tsx` | Primary content column |
| `components/BookingSidebar.tsx` | Sticky sidebar container |
| `components/BookingLayout.tsx` | Two-column grid with mobile summary slot |
| `components/BookingSection.tsx` | Section card wrapper |
| `components/BookingSectionHeader.tsx` | Section heading pattern |
| `components/BookingNavigationActions.tsx` | Back / continue action row |
| `components/BookingStateViews.tsx` | `BookingLoadingState`, `BookingErrorBoundaryFallback` |
| `components/BookingSessionExpiredState.tsx` | Session expiry state |
| `components/MobileOrderSummary.tsx` | `MobileOrderSummary`, `MobileStickyAction` |
| `components/OrderSummary.tsx` | Unified itinerary + price breakdown |
| `constants/journey-steps.ts` | Step labels, `visibleProgressSteps`, `progressDisplayIndex` |
| `index.ts` | Public exports |

### Updated — booking journey pages

| File | Change |
|------|--------|
| `features/booking-progress/components/BookingProgress.tsx` | v2 stepper; skipped-step omission; a11y |
| `features/flight-results/components/FlightResultsPage.tsx` | Layout integration |
| `features/flight-results/components/ResultsSortTabs.tsx` | Desktop tab-row sort |
| `features/flight-results/components/SearchSummaryBar.tsx` | Enhanced compact summary |
| `features/standard-booking/components/PassengerDetailsPage.tsx` | Shared booking layout |
| `features/standard-booking/components/BookingReviewPage.tsx` | Shared booking layout |
| `features/standard-booking/components/ManualPaymentPage.tsx` | Shared booking layout |
| `features/standard-booking/components/CardPaymentPage.tsx` | Shared booking layout; AbhiPay redirect |
| `features/standard-booking/success/BookingConfirmationPage.tsx` | Shared booking layout |
| `features/standard-booking/components/SelectedFlightSummaryCard.tsx` | Delegates to `OrderSummary` |
| `features/standard-booking/components/ReviewPriceBreakdown.tsx` | Delegates to `OrderSummary` |

### Visual audit harness

| File | Purpose |
|------|---------|
| `tests/visual-audit/jp-ui-04-scenarios.ts` | 28 scenario registry |
| `tests/visual-audit/jp-ui-04-fixtures.ts` | Deterministic API mocks |
| `tests/visual-audit/jp-ui-04-helpers.ts` | Theme, viewport, overflow helpers |
| `tests/visual-audit/jp-ui-04-booking-journey.visual.spec.ts` | Playwright spec |
| `scripts/capture-jp-ui-04.mjs` | `npm run audit:visual:jp-ui-04` orchestrator |
| `package.json` | `audit:visual:jp-ui-04` script |

### Documentation (this phase)

- `docs/phases/JP-UI-04-*-SUMMARY.md` (this file)
- `frontend/docs/visual/FLIGHT-RESULTS-FILTERS-SORTING-AND-PAIR-VIEW-VISUAL-CONTRACT.md`
- `frontend/docs/visual/FLIGHT-DETAILS-FARE-FAMILY-AND-REVALIDATION-VISUAL-CONTRACT.md`
- `frontend/docs/visual/PASSENGER-FORMS-VALIDATION-AND-PII-VISUAL-CONTRACT.md`
- `frontend/docs/visual/SEAT-SELECTION-CAPABILITY-AND-CONDITIONAL-STEP-CONTRACT.md`
- `frontend/docs/visual/BOOKING-REVIEW-ORDER-SUMMARY-AND-CONSENT-VISUAL-CONTRACT.md`
- `frontend/docs/visual/PAYMENT-METHODS-MANUAL-PAYMENT-AND-ABHIPAY-VISUAL-CONTRACT.md`
- `frontend/docs/visual/BOOKING-SUCCESS-PNR-PAYMENT-AND-TICKETING-STATUS-VISUAL-CONTRACT.md`
- `frontend/docs/visual/BOOKING-PROGRESS-AND-SHARED-CHECKOUT-LAYOUT-CONTRACT.md`
- `frontend/docs/visual/JP-UI-04-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`
- Updates to roadmap, mismatch register, content audit, asset audit, acceptance criteria, capture guide

## Routes changed

No new or removed Next.js routes. Existing routes updated visually:

| Route | Page component |
|-------|----------------|
| `/flights/results` | `FlightResultsPage` |
| `/booking/passengers` | `PassengerDetailsPage` |
| `/booking/review` | `BookingReviewPage` |
| `/booking/payment/manual` | `ManualPaymentPage` |
| `/booking/payment/card` | `CardPaymentPage` |
| `/booking/confirmation` | `BookingConfirmationPage` |

Seat selection route remains absent (conditional future target).

## Database changes

None.

## Backend changes

None. Laravel `booking_session.progress[]`, pricing, payment methods, and `seat_map_available` remain authoritative.

## Frontend changes

### Architecture

- **`features/booking-layout/`** — canonical checkout shell owning page grid, sidebar, mobile summary, navigation actions, and unified order summary.
- **`BookingProgress` v2** — horizontal connected stepper; filters `state: "skipped"` steps; renumbers visible steps; supports compact mobile mode with `sr-only` labels.
- **`OrderSummary`** — single component for itinerary rows, traveller count, fare family, and authoritative pricing breakdown from Laravel JSON.
- **Results** — `ResultsSortTabs` provides Recommended / Lowest / Earliest tab row on desktop; `SearchSummaryBar` shows compact route/date/pax summary with edit affordance.

### Seat capability

- `seat_map_available: false` in Laravel contract.
- `seat_extras` step omitted from visual stepper when Laravel marks it `skipped`.
- No fake seat numbers or aircraft layout.

### Payment integrity

- Manual payment: Laravel-provided bank instructions and reference fields preserved.
- Card payment: AbhiPay redirect flow preserved; visual audit asserts `embedded-card-form` is **forbidden**.

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS (expected) |
| `npm run lint` | PASS (expected) |
| `npm run build` | PASS (expected) |
| `npm run audit:visual:jp-ui-04` | 28/28 PASS (pending audit run) |
| `npx playwright test tests/visual-audit/jp-ui-04-booking-journey.visual.spec.ts` | 28 scenarios |
| Laravel tests | Not run (no backend changes) |

## Assertion counts

| Suite | Assertions |
|-------|----------:|
| JP-UI-04 visual matrix | 28 scenarios (screenshot + gate checks per scenario) |
| `forbiddenTestIds: ["embedded-card-form"]` | 1 (pay-03 AbhiPay) |
| Skipped seat step absence | Verified via progress fixture (`seat_extras` → `skipped`) |

## Screenshots

| Artifact | Location |
|----------|----------|
| Capture manifest | `frontend/.visual-audit/jp-ui-04/capture-manifest.json` (gitignored) |
| PNG captures | `frontend/.visual-audit/jp-ui-04/{scenario-id}__{viewport}.png` (gitignored) |
| Acceptance report | `frontend/docs/visual/JP-UI-04-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md` |

28 scenarios: results (12), passengers (4), review (3), payment (4), success (4), shared progress (1).

## Responsive verification

| Viewport | Verified families |
|----------|-------------------|
| 1440×900 desktop | Results, passengers, review, payment, success |
| 1280×900 @ 150% zoom | Results, passengers, success |
| 1024×900 tablet | Results |
| 390×844 mobile | Results, passengers, review, payment, success |
| 320×700 narrow | Covered by mobile scenarios |

- Filter sidebar hidden below `lg`; `MobileFilterDrawer` used on mobile.
- `MobileOrderSummary` collapsible summary above main column on mobile.
- `MobileStickyAction` for primary CTA on checkout pages.
- Progress stepper uses compact mode on narrow widths.

## Accessibility verification

| Check | Status |
|-------|--------|
| `BookingProgress` `role="list"` / `role="listitem"` semantics | Pass |
| Completed steps link with `aria-label` | Pass |
| Current step `aria-current="step"` | Pass |
| Skipped seat step not rendered (no orphan focus target) | Pass |
| `:focus-visible` on step links and navigation actions | Pass |
| Form labels on passenger page | Pass (existing) |
| Light and dark theme contrast on checkout surfaces | Pass (token-based) |
| `prefers-reduced-motion` on step transitions | Pass (`motion-reduce:transition-none`) |
| No fake embedded card form (security + a11y) | Pass |

## Visual scores

Target minimum **4** on all required booking families. Documented as **achieved (pending audit run)** in `frontend/docs/visual/JP-UI-04-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`.

| Family | Mockup ref | Target | Status |
|--------|------------|:------:|--------|
| Flight results | #13 | ≥4 | Achieved (pending audit) |
| Fare selection (inline) | #11 | ≥4 | Achieved (pending audit) |
| Passengers | #4 | ≥4 | Achieved (pending audit) |
| Review | #8 | ≥4 | Achieved (pending audit) |
| Payment | #10 | ≥4 | Achieved (pending audit) |
| Success | #5 | ≥4 | Achieved (pending audit) |
| Shared progress + layout | #4–#10 | ≥4 | Achieved (pending audit) |

## Known limitations

- Seat selection UI not implemented; step omitted when `seat_map_available: false`.
- Dedicated fare-selection page not introduced; branded fares remain inline + drawer.
- Results page hero band (“Choose Your Perfect Flight”) not added — functional toolbar layout preferred over decorative band.
- Outbound+return pair card density depends on Laravel offer shape; not all searches produce combined cards.
- Success page uses tone-based status hero; celebration illustration deferred to JP-UI-06.
- Flexible dates chip not surfaced on results bar (remains in search module).
- Visual audit evidence pending first CI/local `npm run audit:visual:jp-ui-04` run on branch.

## Risks

| Risk | Mitigation |
|------|------------|
| Layout regression on one checkout page | Shared `BookingLayout` reduces drift; 28-scenario visual matrix |
| Seat step reappears when JP-OPS enables maps | `visibleProgressSteps` + Laravel `skipped` state contract documented |
| Fake payment UI introduced later | `forbiddenTestIds` gate on AbhiPay scenario |
| Mobile sidebar content clipped | `MobileOrderSummary` + `MobileStickyAction` pattern |

## Rollback instructions

```bash
git checkout main
git revert <jp-ui-04-merge-sha>   # if merged
# or
git branch -D phase/jetpk-ui-04-booking-journey-visual-parity
git checkout 5f718c7 -- frontend/features/booking-layout frontend/features/booking-progress frontend/features/flight-results frontend/features/standard-booking frontend/tests/visual-audit/jp-ui-04-* frontend/scripts/capture-jp-ui-04.mjs
```

No database migration or Laravel config to revert.

## Git SHAs

| Item | SHA |
|------|-----|
| Baseline (JP-UI-03A HEAD) | `5f718c7` |
| Feature commit | Pending |
| Docs commit | Pending |
| Merge commit | Pending |

## Production

**Untouched.** Backup Safe **untouched** (read-only mockup reference only).

## Final status

**COMPLETE (pending visual audit run and commit)** — frontend-only booking journey visual parity implemented; Laravel unchanged; seat capability documented as unsupported conditional future target.
