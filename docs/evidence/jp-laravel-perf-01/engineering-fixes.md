# JP-LARAVEL-PERF-01 — Engineering fixes

## Changes (deployable)

1. **`SearchPerfTrace`** — sanitized T0–T13 + provider start offsets; exposed as `search_perf` on init + READY poll.
2. **`PricingRuleService`** — load active markup rows once per agency per request; filter in PHP.
3. **`AirportReferenceLookup`** — per-request memo + 1h TTL for stable city/country lines.
4. **`DefaultAgencyLookup`** — per-request memo for default agency slug.
5. **`FlightSearchResultStore::beginSearch`** — lightweight payload write (skip Sabre normalizer/split on empty searching).
6. **`FlightSearchService`** — precompute eligibility skip map once; record sequential dispatch mode + network offsets.
7. **`PlatformModuleSettingsService::overrides`** — settings lookup timing into search_perf.

## Explicit non-changes

- No fare/markup formula changes
- No eligibility semantic changes
- No parallel `Concurrency::run` wait-all (would hurt progressive first-card)
- No Next.js changes / no rebuild
- No MOFA
- No speculative DB indexes (`MARKUP_INDEX_CHANGE_REQUIRED=NO`)

## Tests

- `SearchPerfTraceTest`
- `PricingRuleServiceRequestMemoTest`
- `AirportReferenceLookupTest`
- `NextjsFlightSearchInitJsonTest` (expects `search_perf`)
