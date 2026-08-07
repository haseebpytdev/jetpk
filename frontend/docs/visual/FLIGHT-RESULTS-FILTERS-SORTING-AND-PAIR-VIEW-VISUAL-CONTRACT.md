# Flight Results, Filters, Sorting, and Pair View Visual Contract (JP-UI-04)

## Scope

Canonical layout and interaction patterns for `/flights/results` and return-option selection. Mockup reference: **#13**.

## Page structure

1. Shared public header (JP-UI-02 `SiteHeader`)
2. `ResultsHeroBand` — “Choose Your Perfect Flight” decorative band (mockup #13)
3. `SearchSummaryBar` — overlaps hero lower edge; compact route, dates, passenger count, Edit affordance
4. `ModifySearchPanel` — expandable inline search modification (not a separate route)
5. Two-column body at `lg+`:
   - Left (~25%): `ResultsFilterPanel` (sticky)
   - Right (~75%): toolbar, sort tabs, result cards, load more
6. Mobile: filter drawer via `MobileFilterDrawer`; no persistent sidebar

## Search summary bar

- Entry: `features/flight-results/components/SearchSummaryBar.tsx`
- Displays authoritative search parameters from URL + Laravel search summary
- `data-testid="search-summary-bar"`
- Edit action toggles `ModifySearchPanel`
- No invented routes or prices

## Sorting

- Entry: `features/flight-results/components/ResultsSortTabs.tsx`
- Desktop: horizontal tab row — **Recommended**, **Lowest price**, **Earliest departure**
- Mobile: tabs may compress; sort state synced to URL `sort` param
- Replaces dropdown-only sort on desktop (JP-UI-04 parity fix)
- Labels are UI vocabulary (**B**); sort logic uses Laravel offer data (**D**)

## Filters

- `ResultsFilterPanel` — stops, airlines, times, price range (when Laravel provides facets)
- `MobileFilterDrawer` — full-screen drawer on `<lg`
- `data-testid="open-mobile-filters"` on mobile filter trigger
- Filter state in URL search params; no client-side fare invention

## Result cards

- `FlightResultCard` — single-offer presentation
- `OutboundOptionCard` — return-flow outbound selection when applicable
- Airline logo from Laravel `airline_logo_url` with initials fallback
- Price block hierarchy: total price prominent; per-person secondary when provided
- `data-testid="flight-result-card"`

## States

| State | Component | Trigger |
|-------|-----------|---------|
| Loading | `ResultSkeleton`, `SearchProgress` | Fetch in progress |
| Empty | `EmptyResultsState` | Zero offers |
| Partial | `PartialResultsNotice` | Supplier partial failure |
| Expired | `ExpiredSearchState` | Search TTL expired |
| Error | `SearchErrorState` | API failure |

## Load more

- `LoadMoreControl` — centered CTA when Laravel pagination indicates more results

## Responsive rules

| Breakpoint | Behavior |
|------------|----------|
| ≥1280px | Filter sidebar + results ~1:3 ratio |
| 1024–1279px | Sidebar narrows; cards stack normally |
| <1024px | Sidebar hidden; mobile filter drawer |
| 320px | No horizontal overflow; filter drawer scrollable |

## Visual audit scenarios

| ID | State | Viewport |
|----|-------|----------|
| res-01–04 | Results present | 1440 light/dark, 390 light/dark |
| res-05 | Tablet | 1024 light |
| res-06 | 150% zoom | 1280 light |
| res-07 | Loading | 1440 light |
| res-08 | No results | 1440 light |
| res-09 | Partial failure | 1440 light |
| res-10 | Filter drawer open | 390 light |
| res-11 | Branded fare carousel | 1440 light |
| res-12 | Expired search | 1440 light |

## Content ownership

| Item | Class | Owner |
|------|-------|-------|
| Sort/filter labels | B | Frontend vocabulary |
| Prices, airlines, routes | D | Laravel search results API |
| Filter facet counts | D | Laravel |

## Deferred (not in JP-UI-04)

- Flexible dates chip on results toolbar
