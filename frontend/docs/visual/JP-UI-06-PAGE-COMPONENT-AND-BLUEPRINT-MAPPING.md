# JP-UI-06 — Page Component and Blueprint Mapping

Phase: **JP-UI-06**  
Branch: `phase/jetpk-ui-06-canonical-mockup-blueprint-parity`  
Baseline: `111b292`

## Authority

| Source | Role |
|--------|------|
| Backup Safe mockups (1122×1402) | Visual blueprint geometry and composition |
| Laravel / CMS / supplier APIs | Dynamic content, fares, routes, booking state |
| Exception register | Approved operational substitutions only |

## Family registry

| # | Family ID | Route | Comparison mode | Exception(s) | Mockup |
|---|-----------|-------|-----------------|--------------|--------|
| 1 | `homepage` | `/` | `exact` | D, E | `ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` |
| 2 | `about` | `/about-us` | `exact` | D, E | `ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png` |
| 3 | `support` | `/support` | `exact` | D, E | `ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png` |
| 4 | `flight-results` | `/flights/results` | `exact` | D, E | `520bfb29-bc9c-432c-88f1-b53cdadb1592.png` |
| 5 | `fare-selection` | `/flights/fare-selection` | `exact_with_operational_substitution` | A, D, E | `6ea78679-e345-49ea-a4be-2e2f539940c6.png` |
| 6 | `passenger-details` | `/booking/passengers` | `exact` | D, E | `ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png` |
| 7 | `seat-selection-capability-unavailable` | `/booking/passengers` | `capability_exception` | B | `45f39a0b-e38f-4ad2-9077-f631217bd185.png` |
| 8 | `review` | `/booking/review` | `exact` | D, E | `64460b63-9930-478c-96cb-e7a00345caea.png` |
| 9 | `payment` | `/booking/payment` | `exact_with_operational_substitution` | C, D, E | `ab903350-d59f-4b60-b254-9350e4da8f00.png` |
| 10 | `booking-success` | `/booking/confirmation` | `exact` | D, E | `ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png` |
| 11 | `login` | `/login` | `exact` | D, E | `542ee36d-c542-4eec-b5d4-995d555f8ba6.png` |
| 12 | `signup` | `/register` | `exact` | D, E | `0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png` |
| 13 | `manage-booking` | `/lookup-booking` | `exact` | D, E | `678318b0-28f6-4588-ad03-f405f361152e.png` |

---

## 1. Homepage

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/page.tsx` |
| **Layout** | `app/layout.tsx` + inline `PublicShell` |
| **Shell** | `PublicShell` → `SiteHeader`, `SiteFooter` |
| **Main components** | `HomepageContent`, `PublicHero`, `SearchModule`, `RoutesSection`, `FeaturedOffersSection`, `WhyJetPakistanSection`, `PublicSupportBanner` |
| **Data source** | `GET /api/public/content/homepage` + fixtures |
| **Fixture** | `jp-ui-06-fixtures.ts` → `homepage` |
| **Image slots** | Hero background (`PublicHero` / `ImageSlot` 1440×560) |
| **Current mismatches** | Hero uses `min-h-[clamp(22rem,48vh,34rem)]` not blueprint height; search is plain `rounded-jp-card` with pill tabs, not integrated tab-row panel; missing exact overlap/curve geometry |
| **Files to change** | `PublicHero.tsx`, `SearchModule.tsx`, `SearchTabs.tsx`, `OneWayForm.tsx`, `ReturnForm.tsx`, `tokens.css`, `SiteHeader.tsx`, `SiteFooter.tsx`, `HomepageContent.tsx` |

## 2. About

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/about-us/page.tsx` |
| **Layout** | `(public)/layout.tsx` → `PublicShell` |
| **Main components** | `AboutPageContent`, `ContentSection`, `ContentCardGrid`, `ContentRichText` |
| **Data source** | `PublicPageService.getAboutPage()` → `/api/public/content/pages/about` |
| **Fixture** | `about` |
| **Current mismatches** | Generic CMS template layout; missing timeline, metrics bar, scroll-linked animation area geometry |
| **Files to change** | `AboutPageContent.tsx`, new blueprint section components under `features/public-visual/about/` |

## 3. Support

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/support/page.tsx` |
| **Layout** | `(public)/layout.tsx` → `PublicShell` |
| **Main components** | `SupportPageClient`, `ContactForm`, FAQ accordion |
| **Data source** | `SupportContentService` + `fetchSupportCategories()` |
| **Fixture** | `support` |
| **Current mismatches** | Missing hero search bar geometry, 3×2 topic grid proportions, emergency banner, contact card grid |
| **Files to change** | `SupportPageClient.tsx`, support visual components |

## 4. Flight results

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/flights/results/page.tsx` |
| **Layout** | `flights/layout.tsx` → `PublicShell` |
| **Main components** | `FlightResultsPage`, `SearchSummaryBar`, `ResultsFilterPanel`, `FlightResultCard`, `BrandedFareCarousel` |
| **Data source** | `GET /flights/results/data` |
| **Fixture** | `flight-results` |
| **Current mismatches** | Uses `max-w-7xl` not blueprint sidebar ratio; inline fare carousel not dedicated page; card density differs |
| **Files to change** | `FlightResultsPage.tsx`, `FlightResultCard.tsx`, `ResultsFilterPanel.tsx`, `SearchSummaryBar.tsx` |

## 5. Fare selection (new route)

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/flights/fare-selection/page.tsx` (new) |
| **Layout** | `flights/layout.tsx` → `PublicShell` |
| **Main components** | `FareSelectionPage` (new), `BookingProgress`, `BrandedFareCarousel`, `OrderSummary` |
| **Data source** | `GET /flights/results/offer`, `POST /flights/results/revalidate-offer` |
| **Fixture** | `fare-selection` |
| **Exception A** | Stepper order: Search → Results → **Fare Selection** → Travelers → Review → Payment → Success |
| **Files to change** | New `features/fare-selection/`, `use-offer-selection.ts`, `use-revalidation.ts`, `journey-steps.ts` |

## 6. Passenger details

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/booking/passengers/page.tsx` |
| **Layout** | `(public)/layout.tsx` + `BookingPageShell` |
| **Main components** | `PassengerDetailsPage`, `PassengerCard`, `ContactDetailsSection`, `OrderSummary`, `SeatExtrasReadinessPanel` |
| **Data source** | `GET/POST /booking/passengers?format=json` |
| **Fixture** | `passenger-details` |
| **Files to change** | `PassengerDetailsPage.tsx`, booking layout components |

## 7. Seat capability (exception B)

| Field | Value |
|-------|-------|
| **Route** | `/booking/passengers` (fixture: `seat_map_available=false`) |
| **Capture state** | `SeatExtrasReadinessPanel` visible, no seat map DOM |
| **Contract** | `SEAT-SELECTION-CAPABILITY-AND-CONDITIONAL-STEP-CONTRACT.md` |
| **Not compared** | Seat-map mockup pixels |

## 8. Review

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/booking/review/page.tsx` |
| **Main components** | `BookingReviewPage`, `ReviewPassengerList`, `PaymentMethodSelector`, `OrderSummary` |
| **Data source** | `GET/POST /booking/review?format=json` |
| **Fixture** | `review` |

## 9. Payment

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/booking/payment/page.tsx` (replace redirect) |
| **Main components** | `PaymentPage`, `PaymentMethodSelector`, `PaymentMethodContent`, `AbhiPayHandoffPanel`, `ManualPaymentForm`, `OrderSummary` |
| **Data source** | `GET /booking/checkout-state?format=json`, AbhiPay `startCardPayment` |
| **Exception C** | Card-details region → AbhiPay handoff panel, no direct card fields |
| **Compatibility** | `/booking/payment/manual`, `/booking/payment/card` redirect to canonical |

## 10. Booking success

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/booking/confirmation/page.tsx` |
| **Main components** | `BookingConfirmationPage`, `BookingStatusHero`, `ItineraryTimeline`, `PostBookingActions` |
| **Data source** | `GET /booking/confirmation?format=json` |
| **Fixture** | `booking-success` |

## 11. Login

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(auth)/login/page.tsx` |
| **Layout** | `(auth)/layout.tsx` → `PublicShell` |
| **Main components** | `AuthShell`, `AuthPageShell`, `LoginForm` |
| **Data source** | Laravel session bootstrap, `POST /login` |
| **Fixture** | `login` |
| **No fake social** | Only configured OAuth providers |

## 12. Signup

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(auth)/register/page.tsx` |
| **Main components** | `AuthShell`, `CustomerRegistrationForm` |
| **Data source** | `POST /register` |
| **Fixture** | `signup` |
| **Account types** | Only Laravel-supported types |

## 13. Manage booking

| Field | Value |
|-------|-------|
| **Page file** | `frontend/app/(public)/lookup-booking/page.tsx` |
| **Main components** | `BookingLookupPage`, Turnstile region, lookup form |
| **Data source** | `POST /lookup-booking` |
| **Fixture** | `manage-booking` |
| **No unsupported controls** | Change Flight, Baggage, Refund, Live Status |

---

## Shared shell (all families)

| Component | Path | Changes |
|-----------|------|---------|
| `SiteHeader` | `components/layout/SiteHeader.tsx` | Nav height, logo box, spacing, Book Now |
| `SiteFooter` | `components/layout/SiteFooter.tsx` | Column geometry, top spacing |
| `PageContainer` | `components/layout/PageContainer.tsx` | `--jp-maxw` alignment |
| `SectionCurve` | `components/layout/SectionCurve.tsx` (new) | Wave/curve dividers |
| Tokens | `styles/tokens.css` | Measured radii, shadows, max-widths |

## Dark theme

All families preserve identical geometry under `[data-theme="dark"]` via semantic tokens in `tokens.css`.

## Responsive

Desktop blueprint controls composition. Mobile reflow at 390×844 preserves section order, panel design language, CTA hierarchy.
