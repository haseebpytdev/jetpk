# JP-FLIGHT-PERF-01-R2 performance live comparison (corrected)

## Verdict

**PRODUCTION_BEFORE_AFTER_METRICS = NOT_PASS**

Prior after file used Invoke-WebRequest TTFB on `bc83e503` and is **invalid** for final PASS. This comparison uses apples-to-apples Playwright Navigation Timing against live **`9979330c`** / build `e4VIZqNpoHKKio-vCpzSp`.

## Methodology (same class as before)

| Field | Before | After (this run) |
|-------|--------|------------------|
| Tool | Playwright | Playwright |
| Navigation | `page.goto` `domcontentloaded` | same |
| TTFB | Navigation Timing `responseStart` | same |
| Cold/warm | cold=first sample, warm=rest | same |
| Samples/route | 5 | **10** |
| FCP/LCP | often unavailable | PerformanceObserver (+ paint entries) |
| Fonts | not stated | `woff`/`woff2` aborted (harness hang avoidance) |
| Runtime SHA | `460cdae0…` | `9979330c…` |

## Shared-route p50 (Playwright)

| Route | Before TTFB | After TTFB | Δ TTFB | Before shell | After shell | Δ shell | After DCL | After FCP | After LCP |
|-------|-------------|------------|--------|--------------|-------------|---------|-----------|-----------|-----------|
| `/` | 656 | 741 | +85 | 1133 | 2717 | +1584 | 1748 | 2372 | 2712 |
| `/groups` | 303 | 289 | -14 | 580 | 829 | +249 | 709 | 764 | 764 |
| `/groups/search` | 311 | 293 | -18 | 478 | 850 | +372 | 717 | 804 | 804 |
| `/login` | 295 | 294 | -1 | 540 | 884 | +344 | 742 | 856 | 1156 |
| `/groups/package/ALH-3348` | 386 | 443 | +57 | 652 | 1112 | +460 | 1032 | 1008 | 1008 |

Shell metric note: before used wall-clock≈DCL as `nav_to_shell`; after uses wall-clock to visible `main`/body shell after goto. Home DCL itself also regressed (1098 → 1748), so the slowdown is not only metric definition.

## New routes (after-only; no before baseline)

| Route | TTFB p50 | Shell p50 | FCP p50 | LCP p50 |
|-------|----------|-----------|---------|---------|
| `/flights/results` | 298 | 896 | 856 | (see JSON) |
| `/booking/passengers` (deep-link goto) | 334 | 863 | 828 | 828 |
| `/booking/review` | NOT MEASURED | — | — | no synthetic passenger submit |

### Passenger handoff (Continue → passengers)

Separate clock starting at fare Continue (UX-relevant):

| Metric | p50 ms | p95 ms |
|--------|--------|--------|
| NAVIGATION_START_TO_BOOKING_SHELL_MS | 1782 | 2566 |
| NAVIGATION_START_TO_TRAVELER_FORM_USABLE_MS | 5164 | 6197 |

**PASSENGER_SHELL_PERFORMANCE = NOT_PASS** — form usable ~5.2s p50; no before baseline; do not call 3678ms (prior single handoff) “immediate”.

## Top 3 slowest routes (after shell p50)

1. `/` — 2717 ms
2. `/groups/package/ALH-3348` — 1112 ms
3. `/flights/results` — 896 ms

## Regression root cause (home)

Observed, not hidden:

1. **Server/document TTFB outliers** on `/` (slowest document request samples with multi-second TTFB).
2. **Next.js public layout / shared JS chunks** among slowest requests (`app/(public)/layout-*.js`, large shared chunks) correlating with late LCP.
3. **FCP/LCP now measured** — home LCP p50 ≈ 2712 ms (previously missing, so prior PASS was invalid).

**No engineering optimization deployed** in this reopen: no safe flight-behavior-neutral fix identified within scope without broader SSR/bundle work. Improvement claims are not manufactured.

## Cold vs warm

Per-route cold/warm breakdowns are in `performance-live-after.json` (`routes[].cold` / `routes[].warm`).
