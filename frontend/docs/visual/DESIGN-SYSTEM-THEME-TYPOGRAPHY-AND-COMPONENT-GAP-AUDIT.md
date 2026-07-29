# Design System, Theme, Typography, and Component Gap Audit

Phase: **JP-UI-01**

## Current design system

- **Stack:** Tailwind CSS 3 + `jp-*` tokens in `tailwind.config.ts`
- **Components:** Blade-era naming mapped to `SiteHeader`, `SiteFooter`, `SearchModule`, `BookingProgress`
- **Fonts:** `font-display` + system/sans stack (see `app/layout.tsx` / globals)
- **Theme:** **Light mode only** — no `ThemeProvider`, no `darkMode` in Tailwind config, no header toggle

## Theme architecture audit

| Area | Light | Dark | Notes |
|------|-------|------|-------|
| Homepage | Yes | No | Hardcoded gradients `#f4f9fd`, `sky-200` |
| Public CMS pages | Yes | No | |
| Auth | Yes | No | |
| Results / booking | Yes | No | Uses `jp-*` tokens |
| Customer dashboard | Yes | No | |
| Agent dashboard | Yes | No | |
| Dialogs / drawers | Yes | No | |
| Skeletons | Yes | No | |
| Charts | N/A | N/A | |

### Gaps

- No shared theme provider or SSR flash prevention.
- No persisted preference / system preference fallback.
- Mockup family assumes **light/dark** parity → **High** gap.
- Turnstile hardcoded `theme: "light"` in widget.

### Future shared tokens (JP-UI-02)

`background`, `surface`, `elevated-surface`, `muted-surface`, `text`, `muted-text`, `border`, `brand`, `focus`, `success`, `warning`, `danger`, `information`, `inputs`, `skeletons`, `shadows`, `overlays`, `illustrations`.

---

## Typography audit

| Token | Mockup intent | Current | Gap |
|-------|---------------|---------|-----|
| H1 | Large, bold, compact | `text-jp-h1 font-display` | Close; mockup slightly denser |
| H2 section | Clear hierarchy | `text-jp-h2` | Medium |
| Body | Readable, compact | `text-jp-body` | OK |
| Labels | Small caps in places | `text-jp-sm` | OK |
| Line height | Tighter on cards | `leading-relaxed` in places | Medium — reduce on dense flows |
| Button height | ~44–48px | `min-h-jp-button` | OK |
| Form controls | Compact single-row search | Taller stacked fields | **High** on homepage search |
| Table/card density | Dense results | Moderate | Medium |

**Approved production font target:** Licensed geometric sans close to mockup (e.g. existing `font-display` stack) — do not copy unlicensed mockup font files.

---

## Component mapping

| Intended (mockup) | Current file(s) | Routes | Duplication | Visual gap | A11y | Theme | Consolidation | Phase |
|-------------------|-----------------|--------|-------------|------------|------|-------|---------------|-------|
| PublicHeader → **SiteHeader** | `components/layout/SiteHeader.tsx` | All public | None | High (nav density) | OK | Broken dark | Extend in JP-UI-02 | JP-UI-02 |
| PublicFooter → **SiteFooter** | `components/layout/SiteFooter.tsx` | All public | None | Medium | Newsletter stub | Broken dark | CMS nav | JP-UI-02 |
| ThemeSwitch | — | — | — | Missing | — | — | **Create** | JP-UI-02 |
| PublicPageContainer | `PageContainer`, `SectionContainer` | Public | Minor | Low | OK | — | Unify | JP-UI-02 |
| HeroSection | `HomepageHero` | `/` | None | **High** | OK | — | Rebuild layout | JP-UI-03 |
| CompactFlightSearch → **SearchModule** | `features/search/components/SearchModule.tsx` | `/`, results modify | None | **High** | OK | — | Compact row variant | JP-UI-03 |
| BookingProgress | `features/booking-progress/components/BookingProgress.tsx` | Checkout | Per-page wiring | High style | OK | — | Shared stepper v2 | JP-UI-04 |
| OrderSummary | `SelectedFlightSummaryCard`, checkout summary | Checkout | Split | Medium | OK | — | Unify | JP-UI-04 |
| FlightCard | `FlightResultCard` | Results | None | High density | OK | — | Redesign card | JP-UI-04 |
| BrandedFareCarousel | Partial in results | Results | — | High | OK | — | Extract | JP-UI-04 |
| FareFamilyCard | Inline | Results/drawer | — | Medium | OK | — | Extract | JP-UI-04 |
| FilterSidebar | `ResultsFilterPanel` | Results | None | Medium | OK | — | Polish | JP-UI-04 |
| MobileFilterDrawer | `MobileFilterDrawer` | Results | None | Low | OK | — | — | JP-UI-04 |
| ItinerarySegment | `ItineraryTimeline` | Checkout | None | Medium | OK | — | — | JP-UI-04 |
| PassengerForm | `PassengerDetailsPage`, `PassengerCard` | Passengers | None | Medium | OK | — | — | JP-UI-04 |
| SeatMap | `features/seat-selection/types` only | — | — | N/A | — | — | Future | JP-OPS |
| PaymentMethodSelector | `PaymentMethodSelector` | Review | None | Medium | OK | — | — | JP-UI-04 |
| ManualPaymentPanel | `ManualPaymentPage` | Payment | None | Medium | OK | — | — | JP-UI-04 |
| StatusCard | `StatusCards` | Confirmation | None | Low | OK | — | — | JP-UI-04 |
| SupportCard | Support/lookup partial | Support, lookup | Scattered | Medium | OK | — | Extract | JP-UI-03/05 |
| BenefitStrip | `TrustBenefitsStrip` | Home, lookup | None | Low | OK | — | CMS-driven | JP-UI-03 |
| CMSSectionRenderer | `features/public-content` | CMS pages | None | Low | OK | — | — | JP-UI-03 |
| ImageSlot | — | Various | — | Missing | — | — | **Create** | JP-UI-02 |
| AnimatedFlightPath | `components/motion/AnimatedFlightPath` | Home, About | None | Medium | OK | — | Extend | JP-UI-06 |
| EmptyState / ErrorState | Various | Results, booking | Some duplication | Low | OK | — | Consolidate | JP-UI-02 |
| LoadingSkeleton | `ResultSkeleton`, others | Results | Partial | Low | OK | — | Tokenize | JP-UI-02 |
| MobileStickyAction | Partial on booking | Checkout mobile | Incomplete | Medium | OK | — | Create | JP-UI-04 |

## Progress stepper inconsistencies

| Issue | Detail |
|-------|--------|
| Step count | 6 steps when seat included; mockup shows 5–6 depending on Seats |
| Labels | "Flight Selected" vs mockup "Search/Results" |
| Style | Pills vs connected stepper |
| Mobile | Wraps; mockup shows compact single row |
| State source | Laravel `booking_session.progress` (correct) |
| Skipped seats | `skipped` state exists but styling subtle |

**Requirement:** One shared `BookingProgress` v2 — completed, active, pending, error, skipped, optional; numbering adjusts when Seats absent; no client-inferred completion.

## Duplication findings

- Order summary split across `SelectedFlightSummaryCard`, `ReviewPriceBreakdown`, checkout summary on passengers.
- Error states duplicated in `BookingStateCards` vs results error states (acceptable; could share base in JP-UI-02).

No second copy of operational components created in JP-UI-01.
