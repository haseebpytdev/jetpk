# JP-UI-03A Search Interaction State QA

## Captured states (homepage)

| ID | State | Theme | Viewport | Result |
|----|-------|-------|----------|--------|
| hp-23–24 | One Way tab | light, dark | 1440 | Pass |
| hp-25–26 | Return tab | light, dark | 1440 | Pass |
| hp-27–28 | Multi-City tab | light, dark | 1440 | Pass |
| hp-29–30 | Group Ticketing tab | light, dark | 1440 | Pass |
| hp-31 | Autocomplete origin open | light | 1440 | Pass |
| hp-32 | Autocomplete destination open | dark | 1440 | Pass |
| hp-33–34 | Validation error (empty submit) | light, dark | 1440 | Pass |
| hp-35–36 | Traveler/cabin panel open | light, dark | 1440 | Pass |
| hp-37 | Departure field focused | light | 1440 | Pass |
| hp-38–39 | Mobile search active | light, dark | 390 | Pass |

## Content states

| ID | State | Result |
|----|-------|--------|
| hp-40 | Hero media present | Pass |
| hp-41 | Hero media fallback | Pass |
| hp-42 | Destinations present | Pass |
| hp-43 | Destinations empty | Pass |
| hp-44 | Offers present | Pass |
| hp-45 | Offers empty/hidden | Pass |
| hp-46 | Support CTA present | Pass |
| hp-47 | Homepage API failure | Pass |
| hp-48 | Airport API error | Pass |

## Operational preservation

- Airport search uses deterministic Playwright mocks in visual fixtures (`mockAirportSearch`); no hardcoded airport list in production paths
- Group Ticketing uses `mockGroupFacets` for Laravel facets endpoint
- Swap, passenger limits, cabin options, Direct Flights, Nearby Airports, and Flexible Dates behavior unchanged (regression: `homepage.spec.ts`, `search-laravel-*`)

## Support / contact interaction states

Captured on support and contact routes: FAQ closed/expanded, support search initial/results/no-results, contact validation, Turnstile required/provider error, Laravel 422 rejection, rate limit, accepted success fixture.

No fake success timers or unsupported live chat introduced.
