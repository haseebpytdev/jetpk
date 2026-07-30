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

---

## JP-UI-03A visual evidence closure (2026-07-30)

| Item | JP-UI-03 status | JP-UI-03A status |
|------|-----------------|------------------|
| Visual capture count | 6 light-desktop only | **119** full matrix |
| Dark theme captures | Not run | Complete |
| System theme captures | Not run | Complete |
| Mobile 320/375/390 | Partial | Complete |
| Zoom 125%/150% | Not run | Complete |
| Search interaction states | Partial (3 tabs in one test) | Complete (17 scenarios) |
| Horizontal overflow gate | Not enforced | 0 failures |
| Hydration gate | Not enforced | Fixed `ThemeProvider` + 0 warnings |

Evidence: `npm run audit:visual:jp-ui-03a` → `frontend/docs/visual/jp-ui-03a-capture-result.json`

---

## JP-UI-04 booking journey closure (2026-07-30)

Branch: `phase/jetpk-ui-04-booking-journey-visual-parity` · Baseline: `5f718c7`

### Resolved (High → Low or closed)

| Section | Prior severity | JP-UI-04 resolution |
|---------|----------------|---------------------|
| Sort tabs dropdown-only on desktop | High | `ResultsSortTabs` tab row on desktop |
| Progress stepper duplicated per page | High | Shared `BookingProgress` v2 via `booking-layout` |
| Order summary not unified | Medium | `OrderSummary` consolidates sidebar across checkout |
| Review layout flatter density | Medium | `BookingLayout` two-column with sticky sidebar |
| Payment layout inconsistent | Medium | Shared layout on manual + card routes |
| Seats step shown when unsupported | Medium | `visibleProgressSteps` omits `skipped` seat step |

### Remaining accepted gaps

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| Page hero on results | “Choose Your Perfect Flight” banner | Functional `SearchSummaryBar` toolbar | Low | Deferred |
| Edit search bar | Compact inline summary + Edit | `ModifySearchPanel` expandable | Low | Accepted |
| Filter sidebar width | ~25% fixed | ~25% at `lg+`; proportions tokenized | Low | Accepted |
| Result card pair density | Outbound+return in one card | Data-dependent; partial support | Medium | Data-dependent |
| Dedicated fare page | Full-page fare comparison | Inline carousel + drawer | Medium | Deferred |
| Flexible dates chip | Visible on results bar | Search module only | Low | Deferred |
| Success celebration | Illustration + confetti | Tone-based hero; no confetti unless ticketed | Low | JP-UI-06 |
| Seat selection | Mockup #12 | No route; `seat_map_available: false` | Informational | JP-OPS |

### Seat selection status (unchanged classification)

**Future capability** — UI scaffold only; step omitted from progress when Laravel marks `seat_extras` as `skipped`.

Evidence: `npm run audit:visual:jp-ui-04` → 28 scenarios · `frontend/docs/visual/JP-UI-04-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`

---

## JP-UI-05 auth, lookup, and portal closure (2026-07-30)

Branch: `phase/jetpk-ui-05-auth-portals-dashboard-visual-parity` · Baseline: `6d27f9d`

### Resolved or improved

| Section | Prior severity | JP-UI-05 resolution |
|---------|----------------|---------------------|
| Auth split-screen (mockups #6, #7) | High | `AuthPageShell` + `AuthIllustrationPanel` + `AuthFormCard` on login, register, agent register, forgot/reset |
| Auth benefits panel | High | `AuthBenefits` with `LOGIN_BENEFITS` / `SIGNUP_BENEFITS` per route |
| Social login row when unconfigured | Informational | Hidden unless Laravel providers configured; `forbiddenTestIds` gate |
| Session expired notice | Medium | `LoginSessionNotice` for `?reason=session-expired` |
| OTP flow | Low | Shell parity only; **OTP logic unchanged** |
| Manage booking hero band (mockup #9) | Medium | Hero band + trust chips on `BookingLookupPage` |
| Lookup security card | Medium | Trust chips + Turnstile preserved (`lookup-turnstile`) |
| Post-lookup fake actions | Informational | `lookup-change-flight`, `lookup-add-baggage`, `lookup-live-status` remain forbidden |
| Customer/agent shell duplication | Medium | Shared `PortalShell` primitives; shells refactored |
| Dashboard theme flash | Medium | `theme-bootstrap-script.ts` in dashboard app |
| Dashboard token mismatch | Medium | `globals.css` + `dashboard-shell` aligned to `jp-*` tokens |

### Remaining accepted gaps

| Section | Mockup | Current | Severity | Phase |
|---------|--------|---------|----------|-------|
| Auth illustration asset | Photographic hero | Shared SVG (`auth-illustration.svg`) | Low | JP-UI-06 |
| Role selection on signup | Multiple traveler types | Customer + agent apply routes only | Informational | JP-OPS |
| Full dashboard feature depth | Rich ops workspaces | Shell/RBAC visual states; some stubs | Medium | JP-OPS |
| OAuth providers | Google/Apple/Facebook | Hidden until Laravel enables | Informational | JP-OPS |

Evidence: `npm run audit:visual:jp-ui-05` → 132 scenarios · `frontend/docs/visual/JP-UI-05-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`
