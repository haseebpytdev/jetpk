# Performance comparison — JP-UX-PORTAL-PERF-01

## Methodology

Same Playwright harness class as JP-FLIGHT-PERF-01-R2:

- `page.goto` `waitUntil=domcontentloaded`
- Navigation Timing TTFB = `responseStart`
- cold = first sample, warm = rest
- FCP/LCP via PerformanceObserver
- fonts `woff`/`woff2` aborted
- samples = 10

Before: deployed `9979330c` / build `e4VIZqNpoHKKio-vCpzSp`  
After: deployed `9f5b70f4` / build `Nr53B3pSZ0L2kKgascNwz`

`PERFORMANCE_BROWSER_METHOD_SAME=YES`

## Shared-route p50 (ms)

| Route | Before TTFB | After TTFB | Δ | Before shell | After shell | Δ | Before FCP | After FCP | Δ |
|-------|-------------|------------|---|--------------|-------------|---|------------|-----------|---|
| `/` | 837 | 320 | -517 | 1627 | 1336 | -291 | 1904 | 1296 | -608 |
| `/groups` | 338 | 316 | -22 | 1226 | 954 | -272 | 1136 | 888 | -248 |
| `/groups/search` | 384 | 378 | -6 | 1078 | 928 | -150 | 1084 | 920 | -164 |
| `/groups/package/ALH-3348` | 509 | 649 | +140 | 1276 | 1813 | +537 | 1112 | 1436 | +324 |
| `/login` | 634 | 346 | -288 | 1614 | 895 | -719 | 1656 | 884 | -772 |
| `/flights/results` | 391 | 314 | -77 | 1505 | 1022 | -483 | 1380 | 948 | -432 |
| `/booking/passengers` | 288 | 334 | +46 | 951 | 919 | -32 | 908 | 892 | -16 |

## Engineering changes tied to gains

- Parallel `getPublicSession` + `PublicConfigService.getConfig` on `/` and `(public)/layout`
- Per-request `React.cache` session dedupe
- Homepage CMS short revalidate (non-preview)
- Dynamic `SearchModule` on hero; dynamic `FlightDetailsDrawer` on results

## Remaining concern

`/groups/package/ALH-3348` shell/FCP regressed in this sample set. Book-Now → usable traveler form handoff p50 was **not** re-sampled in the after harness (deep-link only). Prior wave usable-form p50 ≈ 5164 ms remains the handoff baseline until a dedicated handoff remeasure.

## Verdict

`PERFORMANCE_CLOSEOUT=NOT_PASS` — package detail regression unexplained; passenger handoff usable-form not remeasured under identical Book-Now methodology.
