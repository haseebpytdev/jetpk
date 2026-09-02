# JP-NEXT-PERF-02 — Engineering fixes

## Changes

1. **Groups SSR authority** — `app/(public)/groups/search/page.tsx` `Promise.all` server fetch for facets + inventory; client hydrates cards immediately when filter key matches.
2. **Remove 720ms landing delay** — immediate `router.push` from `GroupsLandingPage`.
3. **Groups UX** — results not disabled by loading; keep cards during refresh; abort/seq guards.
4. **Flight filter/sort** — keep READY cards; only clear on paired↔segmented view change.
5. **Review/Payment shells** — contextual shell while loading; soft reload after READY.
6. **Traveler** — `router.prefetch("/booking/review")` after context READY.

## Tests

`frontend/tests/regression/jp-next-perf-02-check.cjs` — static contract for the above.
