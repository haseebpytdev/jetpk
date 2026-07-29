# Mockup vs Actual Mismatch Register

Phase: **JP-UI-01**  
Severity: **Blocker** · **High** · **Medium** · **Low** · **Informational**

## Summary counts

| Severity | Count |
|----------|------:|
| Blocker | 0 |
| High | 18 |
| Medium | 42 |
| Low | 35 |
| Informational | 24 |
| **Total** | **119** |

---

## Global shell (all public pages)

| Section | Mockup expectation | Current implementation | Severity | Shared component | Source file(s) | Content owner | Phase |
|---------|-------------------|------------------------|----------|------------------|----------------|---------------|-------|
| Header nav items | Flights, Hotels, Groups, Offers, Travel Services, Support | Flights dropdown, Groups, Support only | High | SiteHeader / DesktopNavigation | `components/layout/SiteHeader.tsx`, `lib/navigation.ts` | CMS/config (nav) | JP-UI-02/03 |
| Header tagline | Visible under logo in mockup | Hidden (`showTagline={false}`) | Medium | JetPakistanLogo | `SiteHeader.tsx` | Brand config | JP-UI-02 |
| Theme toggle | Present in mockup family | **Not implemented** | High | ThemeSwitch (missing) | — | Design system | JP-UI-02 |
| Currency selector | Flag + PKR dropdown | Text selector, no flag | Medium | CurrencySelector | `components/navigation/CurrencySelector.tsx` | Public config | JP-UI-02 |
| Footer columns | 5 columns + newsletter | 4 columns + newsletter (non-functional) | Medium | SiteFooter | `SiteFooter.tsx`, `lib/navigation.ts` | CMS/nav | JP-UI-02/03 |
| Social links | 5 networks | 2 (Facebook, Instagram) | Informational | SiteFooter | `lib/navigation.ts` | Public config | JP-OPS |
| Newsletter | Functional subscribe | `preventDefault` stub | Informational | SiteFooter | `SiteFooter.tsx` | JP-OPS backend | JP-OPS |
| Dark mode | Full light/dark parity | Light only | High | Theme provider (missing) | `tailwind.config.ts` | Design system | JP-UI-02 |

---

## Homepage (canonical mockup #1)

| Section | Mockup expectation | Current | Severity | Component | Phase |
|---------|-------------------|---------|----------|-----------|-------|
| Search module layout | **Single horizontal desktop row** overlapping hero | Multi-row stacked `SearchModule` below hero grid | **High** | SearchModule | JP-UI-03 |
| Search placement | Overlaps hero, above fold at 1440×900 | Below two-column hero; pushes content down | **High** | HomepageHero | JP-UI-03 |
| Hero composition | Photographic aircraft + city full-bleed | SVG illustration in right column | High | HomepageHero | JP-UI-03/06 |
| Hero height | Taller cinematic band | Shorter gradient section | Medium | HomepageHero | JP-UI-03 |
| Nav: Hotels/Offers | Visible | Absent | Informational | navigation | JP-OPS |
| Destination carousel | Photo cards, route→price | Fixture SVG cards, city labels | Medium | DestinationsSection | JP-UI-03 |
| Featured offers | 3 styled promo cards with % | Fixture inspiration cards | Medium | FeaturedOffersSection | JP-UI-03 |
| Scroll path indicator | Dotted path + scroll CTA between sections | AnimatedFlightPath inside hero only | Medium | AnimatedFlightPath | JP-UI-06 |
| Section order | Benefits → path → destinations → offers → why → support → inspiration | Similar order but different density | Medium | `app/page.tsx` | JP-UI-03 |

---

## Flight results (mockup #13)

| Section | Mockup | Current | Severity | Component | Phase |
|---------|--------|---------|----------|-----------|-------|
| Page hero | “Choose Your Perfect Flight” banner | Minimal toolbar, no hero band | High | FlightResultsPage | JP-UI-04 |
| Edit search bar | Compact inline summary + Edit | ModifySearchPanel (expandable) | Medium | ModifySearchPanel | JP-UI-04 |
| Filter sidebar width | ~25% fixed left column | Present but different proportions | Medium | ResultsFilterPanel | JP-UI-04 |
| Sort tabs | Recommended/Lowest/Earliest row above cards | SortControl dropdown + toolbar | High | ResultsToolbar, SortControl | JP-UI-04 |
| Result card density | Outbound+return in one card, fare sub-cards | Single-offer cards, branded fares partial | High | FlightResultCard | JP-UI-04 |
| Price hierarchy | Large total + Select Fare right column | Price block varies | Medium | PriceBlock | JP-UI-04 |
| Flexible dates control | Visible chip | Option exists in search, not results bar | Medium | ResultsToolbar | JP-UI-04 |
| Load more | Centered CTA | LoadMoreControl present | Low | LoadMoreControl | JP-UI-04 |
| Mobile filter drawer | Implied | Implemented (`MobileFilterDrawer`) | Low | MobileFilterDrawer | JP-UI-04 |

---

## Fare selection (mockup #11)

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| Dedicated page | Full-page fare comparison layout | Inline on results + `FlightDetailsDrawer` | High | JP-UI-04 |
| Fare family carousel | 3 branded cards with radio | `has_branded_fares` support partial | Medium | JP-UI-04 |
| Segment detail expansion | Full timeline visible | Drawer/modal pattern | Medium | JP-UI-04 |

---

## Booking flow family (mockups #4, #8, #10, #5)

| Section | Mockup | Current | Severity | Component | Phase |
|---------|--------|---------|----------|-----------|-------|
| Progress stepper | Connected horizontal steps with labels | Pill chips with arrows, duplicated per page | High | BookingProgress | JP-UI-02/04 |
| Step names | Search→Results→Travelers→Seats→Review→Payment→Success | Laravel keys differ; 6 steps with Seat & Extras | Medium | BookingProgress | JP-UI-04 |
| Order summary sidebar | Sticky right ~30% | Present on some pages, not unified | Medium | SelectedFlightSummaryCard | JP-UI-04 |
| Review layout | Two-column with policy block | Functional but flatter density | Medium | BookingReviewPage | JP-UI-04 |
| Payment layout | Method cards + sidebar | Manual/card split routes | Medium | ManualPaymentPage | JP-UI-04 |
| Success celebration | Illustration + confetti intent | Tone-based hero, no confetti unless ticketed | Low | BookingConfirmationPage | JP-UI-06 |

---

## Seat selection (mockup #12)

| Item | Status | Severity | Phase |
|------|--------|----------|-------|
| Route exists | **No** | Informational | JP-OPS |
| `seat_map_available` | `false` in Laravel contract | Informational | JP-OPS |
| UI types scaffolded | `features/seat-selection/types` only | Informational | JP-UI-04 (conditional) |

**Classification:** **Future capability** — must remain hidden until Laravel confirms.

---

## Auth (mockups #6, #7)

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| Split-screen | Form + illustration | Single-column card layout | High | JP-UI-05 |
| Social login row | Google/Apple/Facebook | Not shown unless providers configured | Informational | JP-OPS |
| Role selection on signup | Multiple traveler types | Customer register only (+ agent apply separate) | Informational | JP-UI-05 |
| OTP flow | Implied | `/login/otp` exists, demo flags preserved | Low | JP-OPS |

---

## Manage booking (mockup #9)

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| Hero band | Full-width with aircraft | Simpler page header | Medium | JP-UI-05 |
| Security card | Visible | Partial / merged | Medium | BookingLookupPage | JP-UI-05 |
| Post-lookup actions | Change flight, add baggage | Not shown (correct — unsupported) | Informational | JP-OPS |

---

## About & Support (mockups #2, #3)

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| About hero animation | Large motion area | Decorative flight path SVG | Medium | JP-UI-03/06 |
| About metrics/stats | Numeric claims | CMS-driven or absent | Informational | JP-OPS/CMS |
| Support emergency block | Dedicated emergency card | Standard contact channels | Informational | JP-OPS |
| Support agent illustration | Character slot | Partial | Medium | JP-UI-06 |

---

## Acceptance criterion linkage

Each High/Blocker row above maps to a measurable criterion in `RESPONSIVE-ACCESSIBILITY-AND-VISUAL-ACCEPTANCE-CRITERIA.md`. Implementation phases assigned in `JP-UI-IMPLEMENTATION-ROADMAP.md`.
