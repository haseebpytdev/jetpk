# JP-PERF-FINAL-02R3 — authority correction

## Objective
Stop using Book Now `sessionStorage` timing as commercial skip authority. Skip Traveler auto-reprice only when passengers JSON proves server-authoritative exact selection.

## Included
- Expose `itinerary.authoritative_after_revalidation`, `bound_search_id`, `bound_offer_id`
- Client skip helper: exact search/offer/fare match + server flag
- Honest harness POST counters by phase

## Excluded
Return latency, Traveler HARD_ASSIGN, email QA, supplier mutations, architecture rewrite of ordinary routes.

## Tests
`npx tsx --test frontend/tests/regression/jp-perf-final-02-prevalidation.test.ts` — 9 pass.
PHPUnit `JpBo04gPriceAuthorityPersistenceTest` did not boot locally (unrelated `ClientManagedPageReservedSlugs` autoload). Presenter `php -l` clean.

## Status
Code complete; production cert after protected deploy of this SHA.
