# JP-FE-02 — Homepage Hero, Flight Search & Dynamic Content Shell

## Phase metadata

| Field | Value |
| --- | --- |
| Phase name | JP-FE-02-HOMEPAGE-HERO-FLIGHT-SEARCH-AND-DYNAMIC-CONTENT-SHELL |
| Branch | `phase/jetpk-fe-02-homepage-search` |
| Objective | Replace JP-FE-01 homepage placeholder with full JetPakistan homepage presentation and interactive search shell (fixtures only, no supplier calls) |
| Final status | **COMPLETE** (local frontend scope) |

## Included scope

- Homepage hero with aviation/skyline composition and search card
- Search module: One Way, Return, Multi-City, Group Ticketing tabs
- Reusable `AirportField`, `DateField`, `TravelersCabinSelector`
- Trust/benefits strip, destinations carousel, featured offers, why section, support banner, travel inspiration
- Typed fixtures and Laravel service boundaries
- Upgraded `AnimatedFlightPath` (SVG, reduced motion, off-screen pause)
- Homepage + shell Playwright tests
- Frontend architecture documentation

## Excluded scope

- Real supplier search, bookings, PNR, payments
- Laravel API/CMS integration
- Results pages and booking flow
- `dashboard/` changes
- Production deployment

## Investigation findings

- JP-FE-01 shell (`PublicShell`, header, footer, tokens) is stable and reused without regression.
- Laravel Blade search shell (`search-shell.blade.php`, `groups-panel.blade.php`) informed field naming and group category alignment.
- HTML `min` on return date inputs provides practical outbound/return ordering; additional validation exists in `validateFlightSearch`.

## Root causes addressed

- Homepage was a placeholder announcing JP-FE-02 — replaced with feature-module architecture under `frontend/features/`.
- No typed search draft boundary existed — added `SearchDraft` / `GroupSearchDraft` with local validation and integration preview.

## Architecture summary

```
frontend/app/page.tsx (server)
  └── PublicShell
        └── HomepageContent
              ├── HomepageHero + SearchModule (client)
              ├── DestinationsSection (client carousel)
              ├── FeaturedOffersSection
              ├── WhyJetPakistanSection
              ├── SupportBanner
              └── TravelInspirationSection

frontend/features/search/   — interactive search shell
frontend/features/home/     — homepage presentation sections
frontend/services/          — AirportSearchService, HomepageContentService boundaries
```

## Component inventory

| Component | Location |
| --- | --- |
| `SearchModule` | `features/search/components/SearchModule.tsx` |
| `SearchTabs` | `features/search/components/SearchTabs.tsx` |
| `OneWayForm` / `ReturnForm` / `MultiCityForm` / `GroupTicketingForm` | `features/search/components/` |
| `AirportField` | `features/search/components/AirportField.tsx` |
| `DateField` | `features/search/components/DateField.tsx` |
| `TravelersCabinSelector` | `features/search/components/TravelersCabinSelector.tsx` |
| `SearchOptionsBar` | `features/search/components/SearchOptionsBar.tsx` |
| `SearchSubmitPreview` | `features/search/components/SearchSubmitPreview.tsx` |
| `HomepageHero` | `features/home/components/HomepageHero.tsx` |
| `TrustBenefitsStrip` | `features/home/components/TrustBenefitsStrip.tsx` |
| `DestinationsSection` | `features/home/components/DestinationsSection.tsx` |
| `FeaturedOffersSection` | `features/home/components/FeaturedOffersSection.tsx` |
| `WhyJetPakistanSection` | `features/home/components/WhyJetPakistanSection.tsx` |
| `SupportBanner` | `features/home/components/SupportBanner.tsx` |
| `TravelInspirationSection` | `features/home/components/TravelInspirationSection.tsx` |
| `AnimatedFlightPath` (upgraded) | `components/motion/AnimatedFlightPath.tsx` |

## Fixture inventory

| Fixture | File |
| --- | --- |
| Airports (PK + international) | `features/search/fixtures/airports.ts` |
| Cabin classes | `features/search/fixtures/cabins.ts` |
| Group categories (All, KSA, UAE, Muscat) | `features/search/fixtures/group-categories.ts` |
| Benefits strip | `features/home/fixtures/benefits.ts` |
| Destinations on the Rise | `features/home/fixtures/destinations.ts` |
| Featured offers | `features/home/fixtures/offers.ts` |
| Value props + inspiration | `features/home/fixtures/inspiration.ts` |

## Search-state model

- **Mode:** `one_way` \| `return` \| `multi_city` \| `group`
- **Flight segments:** `from`, `to`, `departureDate` per segment; multi-city min 2 / max 6 segments
- **Passengers:** adults (≥1), children, infants (≤ adults), cabin
- **Options (flight modes):** direct only, nearby airports (origin only), flexible dates (outbound)
- **Submit:** validates → builds `SearchDraft` or `GroupSearchDraft` → shows integration preview (no API call)

## Routes changed

| Route | Change |
| --- | --- |
| `/` (`frontend/app/page.tsx`) | Full homepage content |

No Laravel routes changed.

## Database changes

None.

## Backend changes

None (`dashboard/`, Laravel business logic, suppliers untouched).

## Frontend changes

See **Exact files changed** below.

## Tests executed

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS — `/` static, 14.3 kB page JS |
| `npm run test:smoke` / Playwright | **15/15 PASS** |

### Playwright coverage

- Homepage hero + search shell load
- One Way submit preview
- Return tab fields + return `min` date
- Multi-city add/remove
- Group ticketing categories
- Airport keyboard selection
- Travelers infant constraint
- Mobile search layout
- Reduced motion flight path
- JP-FE-01 mobile nav regression (shell spec)

## Assertion counts

- Playwright: 15 tests, 15 passed

## Screenshots

Local manual QA recommended at 320–1440px and 125%/150% zoom. No screenshots committed.

## Responsive QA matrix

| Breakpoint | Status | Notes |
| --- | --- | --- |
| 320px | Verified via Playwright 390px + layout patterns | Tabs scroll horizontally |
| 375px / 390px | Playwright mobile tests pass | Search module visible |
| 768px | Grid breakpoints in forms | Swap button on md+ |
| 1024px+ | Hero two-column layout | Search card below hero copy |
| 125% / 150% zoom | Token clamp scales | Manual spot-check recommended |

## Accessibility notes

- Single page `h1`
- Tablist with `aria-selected`, arrow-key navigation
- Airport combobox + listbox keyboard support, Escape closes
- Travelers popover: Escape, focus return, increment labels
- Visible `focus-visible:shadow-jp-focus` focus rings
- Reduced motion disables flight-path animation
- Decorative SVGs use `aria-hidden` or meaningful `aria-label`

## Animation notes

- SVG dotted path with CSS `flight-draw` / `flight-move` keyframes
- `IntersectionObserver` pauses animation off-screen
- `prefers-reduced-motion` disables animation
- No canvas/WebGL/heavy libraries

## Known limitations

- No real Laravel airport search or flight results
- Group ticketing uses fixture sectors/categories, not live inventory facets
- Multi-city does not stitch itineraries
- Support/WhatsApp CTAs are placeholders (no submission)
- Offer/destination prices labeled as sample content

## Risks

- Low: presentation-only; no production or supplier impact
- Future Laravel integration must map API responses to existing types

## Rollback instructions

```bash
git checkout main -- frontend/
# Or revert the phase commit on branch phase/jetpk-fe-02-homepage-search
```

## Exact files changed

### Modified
- `frontend/README.md`
- `frontend/app/globals.css`
- `frontend/app/page.tsx`
- `frontend/components/motion/AnimatedFlightPath.tsx`
- `frontend/tests/public-shell.spec.ts`

### Added
- `frontend/docs/HOMEPAGE-AND-SEARCH.md`
- `frontend/features/home/**`
- `frontend/features/search/**`
- `frontend/public/images/home/*.svg`
- `frontend/services/airports.ts`
- `frontend/services/homepage-content.ts`
- `frontend/tests/homepage.spec.ts`
- `docs/phases/JP-FE-02-HOMEPAGE-HERO-FLIGHT-SEARCH-AND-DYNAMIC-CONTENT-SHELL-SUMMARY.md`

## No-production-deployment confirmation

**Confirmed.** No production deploy, DNS, SSH, SFTP, or live supplier calls were made. `dashboard/` and Laravel business logic were not modified.

## Exact next recommended phase

**JP-FE-03-PUBLIC-CONTENT-PAGES-ABOUT-SUPPORT-FAQ-CONTACT-AND-CMS-SHELL**

## Commit SHA

_To be recorded after commit._
