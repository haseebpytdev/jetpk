# JETPK-UI-04 — Results and Fare Selection Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-04 |
| Branch | `phase/jetpk-ui-04-results-fare-selection-closure` |
| Baseline (parent) | `32435ada4e22d846bac40f7d0ce650d69825b7d7` |
| Gaps targeted | JETPK-UI-004, JETPK-UI-005 |
| Commit subject | `feat: close JetPakistan results and fare selection gaps` |
| Merge subject | `merge: close JETPK-UI-04` |
| Deployment | NOT PERFORMED |

## Investigation findings

| Gap | Pre-phase state | Root cause |
|-----|-----------------|------------|
| JETPK-UI-004 | Results page had functional `SearchSummaryBar` only; no mockup #13 hero band | Hero band intentionally deferred in JP-UI-04 visual contract |
| JETPK-UI-005 | Dedicated `/flights/fare-selection` route coexisted with inline carousel + drawer; docs said “no dedicated route” | Product evolved in fullstack phases without updating authority docs; `FareFamilyDetails` lacked carousel for >3 families |

## Fare selection authority (resolved)

| Surface | Authority |
|---------|-----------|
| Inline `BrandedFareCarousel` on result cards | Quick preview; book on branded offers navigates to dedicated route |
| `/flights/fare-selection` | Full mockup #11 comparison page for multi-family branded fares |
| `FlightDetailsDrawer` | Segment timeline, baggage, fare rules — not primary fare comparison |

Module: `frontend/lib/fare-selection-authority.ts`

Carousel rule: when fare families > 3, use contained horizontal carousel with chevron navigation (no fourth-row wrap).

## Implementation summary

### JETPK-UI-004

- Added `ResultsHeroBand` with “Choose Your Perfect Flight” headline (`data-testid="results-hero-band"`).
- Integrated hero + overlapping `SearchSummaryBar` in `FlightResultsPage`.
- Preserved honest empty/error/expired states when `search_id` or backend data unavailable.
- Playwright fixture pattern unchanged (`MOCK_SEARCH_ID` + route mocks).

### JETPK-UI-005

- Documented and enforced fare-selection authority in `fare-selection-authority.ts`.
- Upgraded `FareFamilyDetails` on `FareSelectionPage` with carousel navigation when >3 families.
- Aligned `BrandedFareCarousel` threshold via shared `shouldUseFareCarousel()`.
- Updated visual contracts to reflect dedicated route + hero band closure.

## Files changed

| File | Change |
|------|--------|
| `frontend/features/flight-results/components/ResultsHeroBand.tsx` | New results hero band |
| `frontend/features/flight-results/components/FlightResultsPage.tsx` | Hero + summary layout |
| `frontend/features/flight-details/components/FareFamilyDetails.tsx` | Carousel for >3 fares |
| `frontend/features/flight-results/components/BrandedFareCarousel.tsx` | Shared carousel threshold |
| `frontend/lib/fare-selection-authority.ts` | Fare UX authority module |
| `frontend/tests/flight-results.spec.ts` | Hero + branded route tests |
| `frontend/docs/visual/FLIGHT-RESULTS-FILTERS-SORTING-AND-PAIR-VIEW-VISUAL-CONTRACT.md` | Hero band in scope |
| `frontend/docs/visual/FLIGHT-DETAILS-FARE-FAMILY-AND-REVALIDATION-VISUAL-CONTRACT.md` | Route authority |

## Gap closure

| Gap | Status |
|-----|--------|
| JETPK-UI-004 | **CLOSED** |
| JETPK-UI-005 | **CLOSED** |

**Remaining open gaps after phase:** 15 (of 22 original)

## Tests executed

- `npx playwright test tests/flight-results.spec.ts --project=chromium --workers=1`
- `npx playwright test tests/jp-full-next-frontend/fare-selection.spec.ts -c playwright.jp-full-next-frontend.config.ts`
- `npx playwright test tests/jp-ui-04a-fare-states.spec.ts --project=chromium --workers=1`

## Responsive verification

- Hero band readable at 1280×800 and 390×844 (manual + Playwright mobile filter test unaffected).
- Search summary overlap preserved on narrow viewports.

## Accessibility verification

- Hero uses `aria-labelledby` with visible `h2`.
- Carousel chevrons retain `aria-label` on results and fare-selection surfaces.
- Existing focus-visible rings preserved.

## Known limitations

- Hero band is decorative; route/prices remain supplier-authoritative.
- Drawer fare families still use button row (not full card layout) — acceptable for detail context.

## Risks

- Low: layout shift from hero band on results; mitigated by negative margin overlap pattern.

## Rollback

Revert merge commit on `main` or restore pre-phase `FlightResultsPage` and remove `ResultsHeroBand`.

## Final status

Pending merge after acceptance suite PASS.
