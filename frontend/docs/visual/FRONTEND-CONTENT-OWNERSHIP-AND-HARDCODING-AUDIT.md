# Frontend Content Ownership and Hardcoding Audit

Phase: **JP-UI-01**

## Classification key

| Code | Meaning |
|------|---------|
| **A** | Design-system constant (spacing, radius, typography, breakpoints, motion timing) |
| **B** | Localized UI vocabulary (labels, a11y text, generic statuses) |
| **C** | Laravel / CMS / public configuration |
| **D** | Supplier / booking / payment data |
| **E** | Test-only fixture (never production) |
| **F** | Invalid production hardcoding (migrate or remove) |

---

## File-by-file register (high-traffic)

### `frontend/lib/navigation.ts`

| Item | Class | Owner | Phase |
|------|-------|-------|-------|
| `primaryNavigation` links | C | CMS/public nav API | JP-UI-03 |
| `footerColumns` | C | CMS/public nav | JP-UI-03 |
| `socialLinks` URLs | C | Public config | JP-OPS |
| `currencyOptions` | C | Public config | JP-OPS |

### `frontend/features/home/fixtures/*.ts`

| File | Content | Class | Risk | Phase |
|------|---------|-------|------|-------|
| `destinations.ts` | Cities, sample prices | **E/F** | Fixture used in production homepage | JP-UI-03 (CMS) |
| `offers.ts` | Offer titles, sample prices | **E/F** | Same | JP-UI-03 |
| `benefits.ts` | Benefit copy | C (should be CMS) | Medium | JP-UI-03 |
| `inspiration.ts` | Articles, dates | **E/F** | Same | JP-UI-03 |

### `frontend/features/public-content/fixtures/site-contact.ts`

| Item | Class | Notes |
|------|-------|-------|
| Phone `0311 1222427` | C | Verified public contact; should load from Laravel API |
| Email `ota@jetpakistan.pk` | C | Same |

### `frontend/components/layout/SiteFooter.tsx`

| Item | Class | Phase |
|------|-------|-------|
| Brand blurb paragraph | C | JP-UI-03 |
| Newsletter form handler (stub) | Informational | JP-OPS |

### `frontend/features/search/components/SearchModule.tsx`

| Item | Class |
|------|-------|
| Default airports ISB/DXB | A/B (sensible defaults) |
| Tab labels One Way/Return/Multi-City/Group | B |
| Validation messages | B |

### `frontend/features/flight-results/**`

| Item | Class |
|------|-------|
| Sort labels | B |
| Filter labels | B |
| Prices, airlines, routes | **D** (from Laravel JSON) |

### `frontend/features/standard-booking/**`

| Item | Class |
|------|-------|
| Progress step labels | **D** (Laravel `booking_session.progress`) |
| Payment method labels | **D** |
| Manual payment instructions | **D** |

### `frontend/features/auth/**`

| Item | Class |
|------|-------|
| Form labels | B |
| OTP demo copy | C (env-controlled) |

### `frontend/tailwind.config.ts`

| Item | Class |
|------|-------|
| `jp-*` tokens (colors, spacing, radii) | **A** |
| No `darkMode` strategy configured | Gap → JP-UI-02 |

---

## Invalid production hardcoding (F) — deferred fixes

| Location | Issue | Recommended owner | Phase |
|----------|-------|-------------------|-------|
| `features/home/fixtures/destinations.ts` | Sample PKR prices in production UI | CMS offers API | JP-UI-03 |
| `features/home/fixtures/offers.ts` | Hardcoded promo content | CMS | JP-UI-03 |
| `features/home/fixtures/inspiration.ts` | Hardcoded articles | CMS | JP-UI-03 |
| `lib/navigation.ts` | Static nav diverges from mockup | Public nav config | JP-UI-03 |
| `lib/navigation.ts` | Social URLs hardcoded | Public config endpoint | JP-OPS |

---

## Homepage-specific hardcoding findings

- **Destinations on the Rise:** uses `DESTINATION_FIXTURES` not supplier/CMS data → **F** for prices; **C** target.
- **Featured Offers:** `FEATURED_OFFER_FIXTURES` → **F**; migrate to CMS.
- **Why JetPakistan / Trust strip:** `VALUE_PROPOSITION_FIXTURES` / `BENEFIT_FIXTURES` → should be **C**.
- **Hero SVG:** design asset **A** slot; production photograph **C**.

---

## Booking flow hardcoding

- No fake PNR/ticket numbers in components (correct).
- Test fixtures in `tests/*.spec.ts` and `tests/visual-audit/jp-ui-01-fixtures.ts` → **E** only.
- Seat map: no hardcoded aircraft layout (correct).

---

## Dashboard hardcoding (inventory)

Customer/Agent sidebars use static `NAV_ITEMS` in shell components → **C** (capabilities-driven labels mostly from Laravel); visual audit deferred to JP-UI-05.

---

## Correction assignment summary

| Count | JP-UI | JP-OPS |
|------:|-------|--------|
| Design tokens (A) | JP-UI-02 | — |
| UI vocabulary (B) | JP-UI-02 | — |
| CMS/config migration (C) | JP-UI-03 | JP-OPS-01 |
| Invalid fixtures (F) | JP-UI-03 | — |

No broad hardcoding fixes performed in JP-UI-01.

---

## JP-UI-04 audit pass (2026-07-30)

Branch: `phase/jetpk-ui-04-booking-journey-visual-parity` · Baseline: `5f718c7`

### New module: `frontend/features/booking-layout/`

| Item | Class | Owner | Notes |
|------|-------|-------|-------|
| `BOOKING_JOURNEY_STEP_LABELS` | B | Frontend vocabulary | Laravel `progress[].label` overrides when present |
| `visibleProgressSteps` / `progressDisplayIndex` | A | Layout logic | Filters `skipped` steps |
| `OrderSummary` display labels (Airline, Flight, etc.) | B | Frontend vocabulary | Values from Laravel **D** |
| Layout spacing, grid ratios | A | Design tokens | — |

### Booking flow (updated)

| Item | Class | JP-UI-04 status |
|------|-------|-----------------|
| Progress step labels | D | Correct — Laravel `booking_session.progress` |
| Payment method labels | D | Correct — Laravel |
| Manual payment bank details | D | Correct — no invented accounts |
| AbhiPay redirect | D | Correct — no embedded card form |
| Order summary pricing | D | Correct — `AuthoritativePricing` only |
| Seat map layout | — | Not rendered (`seat_map_available: false`) |

### Results flow

| Item | Class | JP-UI-04 status |
|------|-------|-----------------|
| Sort tab labels | B | Correct |
| Filter labels | B | Correct |
| Prices, airlines, routes | D | Correct — Laravel JSON |
| Branded fare names | D | Correct — offer payload |

### Invalid hardcoding (F) — none introduced

JP-UI-04 did not add fake PNRs, seat numbers, bank details, or fare prices. Visual audit fixtures remain **E** only (`jp-ui-04-fixtures.ts`).

### Correction assignment (JP-UI-04)

| Count | Status |
|------:|--------|
| New F violations | 0 |
| Booking D-sourced fields verified | Pass |
| Seat UI hardcoding | N/A (step omitted) |
