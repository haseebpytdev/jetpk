# JetPakistan public performance baseline — frozen after JP-UI-PERF-STABILITY-01D3

## Freeze status

```text
PERFORMANCE_BASELINE_STATUS=FROZEN
FROZEN_AFTER_PHASE=JP_UI_PERF_STABILITY_01D3_A_ONLY_PRODUCTION_CLOSED
CANONICAL_MAIN_AT_FREEZE=f84ea8d14105c49c05ddd6638e067e7b893dd989
PRODUCTION_APPLICATION_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
PRODUCTION_FRONTEND_SHA=49f7f58e440018991d5e911664c6877fdc6529a1
```

This document defines the current accepted public-performance baseline. Closed phases must not be reopened by default.

## Closed phases

### Phase A — responsive media

Closed and preserved:

- responsive CMS image derivatives
- hero/card/support derivative selection
- giant originals excluded when derivatives exist
- below-fold lazy loading
- production media transfer reduced from the original ~29 MB class to roughly the ~1.7 MB page class observed during certification

Required invariant:

```text
PHASE_A_MEDIA_REGRESSION=NO
```

### Phase B — first-paint branding

Closed and preserved:

- correct organization logo in SSR
- no fallback-logo flash
- no post-hydration branding swap
- no redundant browser public-config fetch when SSR branding is present

Required invariant:

```text
PHASE_B_BRANDING_REGRESSION=NO
```

### Phase C — floating UI coordination

Closed and preserved:

- Ask FAB and mobile dock geometry coordinated
- lifted routes use dock bottom ~108px and Ask bottom ~178px
- mutual exclusion between Ask panel and dock
- no horizontal overflow/fixed-element collision across certified viewports
- flight-card layout unchanged

Required invariant:

```text
PHASE_C_FLOATING_REGRESSION=NO
```

### Phase D — Ask panel lazy load

Closed and preserved:

- Ask launcher remains initial
- heavy Ask panel code is not transferred before first open
- panel loads on first open / explicit hash deep link
- close/reopen and hash behavior certified

Required invariant:

```text
PHASE_D_ASK_LAZY_REGRESSION=NO
```

### Phase D2 — search/runtime diagnostic

Closed with no runtime change.

Rejected because benefits were below shipping threshold:

- Group mode island alone
- Multi-city island
- Return lazy split
- Travelers lazy split
- submit-only import split

The temporary Group + Multi-city split saved only ~4.5 KB and was reverted.

Server runtime changes were also rejected because measured warm TTFB and dependency timings did not justify new caching.

### Phase D3 — group search graph split

Closed and live.

Accepted:

- read-only group search/facets graph separated from mutation/CSRF-aware booking/payment graph
- GET search/facets paths do not bootstrap CSRF
- booking/payment mutation code remains in the mutation-aware group API path

Certified/live impact:

```text
BASE_GROUP_PREFETCH_JS_BYTES=96888
LIVE_GROUP_PREFETCH_JS_BYTES=53945
GROUP_PREFETCH_JS_REDUCTION≈44%

D3_LOCAL_BASE_INITIAL_JS_BYTES=883416
D3_LOCAL_CANDIDATE_INITIAL_JS_BYTES=828392
D3_LOCAL_JS_REDUCTION_BYTES=55024
D3_LOCAL_JS_REDUCTION_PERCENT=6.23

D3_LIVE_INITIAL_JS_BYTES=830018
```

Required invariant:

```text
GROUP_SEARCH_READ_ONLY_GRAPH_SEPARATION=PRESERVED
```

## Explicitly rejected performance experiments

The following must not be reintroduced casually.

### Priority-prefetch removal

Rejected in Phase D and again in D3-B.

Groups intent-only prefetch produced material P95 regressions, including candidate measurements around:

```text
home→groups: 182ms → 453ms P95
groups→home: 208ms → 361ms P95
```

Therefore:

```text
GROUPS_PRIORITY_PREFETCH=KEEP
LOGIN_PRIORITY_PREFETCH=KEEP
```

D3-A optimizes the payload behind Groups prefetch; it does not remove the prefetch.

### Delayed Groups background prefetch

Rejected in D3-B2.

Do not reintroduce without a new benchmark that materially differs from the prior evidence.

### PublicHero server-component split

Rejected in Phase D because no measurable JS/TBT benefit was established.

Current hero/search architecture remains frozen.

### Search-mode micro-splits

Return, Multi-city, Travelers and submit-only splits were rejected because the isolated byte savings were too small to justify complexity and possible first-interaction latency.

### Flights-results homepage prefetch change

D3-C was not shipped because the candidate harness did not reproduce a local idle flights RSC that justified changing the link behavior.

Do not globally disable Next Link prefetch.

## Framework-owned chunks

Large framework-owned chunks such as Next/React, react-dom and Next-bundled observability/OpenTelemetry are not app-level performance targets under this baseline.

Do not change:

```text
NEXT_VERSION
REACT_VERSION
FRAMEWORK_CHUNK_POLICY
```

as part of routine performance tuning.

A framework upgrade must be a separate migration project with its own regression and production plan.

## Current production invariants

```text
APPLICATION_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
PUBLIC_FRONTEND_SHA=49f7f58e440018991d5e911664c6877fdc6529a1

GROUPS_PRIORITY_PREFETCH_CHANGED=NO
LOGIN_PRIORITY_PREFETCH_CHANGED=NO
RSC_COUNT_OPTIMIZATION_CLAIM=NO

SUPPLIER_CALLS_DURING_PERF_CERT=0
BOOKING_MUTATIONS_DURING_PERF_CERT=0
PAYMENT_MUTATIONS_DURING_PERF_CERT=0
DATABASE_MUTATIONS_DURING_PERF_CERT=0
```

## Reopen criteria

Do not start another public-performance optimization phase merely because a smaller bundle is theoretically possible.

Reopen performance work only when at least one of these is true:

1. a verified production regression appears against this baseline;
2. a new route/feature creates a material JS, RSC, image, hydration or navigation regression;
3. field data or controlled same-host benchmarks show a new material bottleneck;
4. a framework/runtime upgrade is separately authorized;
5. a candidate can demonstrate a meaningful improvement before implementation, such as:
   - >=10 KB app-owned initial/background JS reduction,
   - >=15% reduction in a specific feature-prefetch graph,
   - elimination of a proven unnecessary request without material navigation regression,
   - or a material main-thread/critical-window improvement.

Any reopened phase must preserve all earlier invariants unless the regression evidence specifically demonstrates that one must change.

## Baseline rule

```text
DO_NOT_REOPEN_A_B_C_D_D2_D3_WITHOUT_NEW_REGRESSION_OR_NEW_MATERIAL_EVIDENCE
```

This freeze is intended to prevent repeated micro-optimization from destabilizing a now-certified public frontend.
