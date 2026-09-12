# Authority-06 R3 — router-wait causality diagnostic summary

**Production SHA:** `8c50fc61967e51c5b576375b55ae7701d122c121`  
**PUBLIC_BUILD_ID:** `IZ_eW6HoJobLsxboJJL7C`  
**Evidence captured:** 2026-09-11  
**Scope:** Read-only diagnostics — no deploy, no source change.

## §1 Production freeze

| Gate | Result |
|------|--------|
| PRODUCTION_SHA | `8c50fc61967e51c5b576375b55ae7701d122c121` |
| PUBLIC_BUILD_ID | `IZ_eW6HoJobLsxboJJL7C` |
| LIVE_HTTP | 200 |
| RUNTIME_OWNERSHIP_GATE | PASS |
| PUBLIC_PM2 | online (`jetpk-public-frontend` restarted at R2 deploy) |

## §2 Harness validation (N=30 per route)

| Field | Value |
|-------|-------|
| HARNESS_ROUTER_WAIT_VALID | **YES** |
| HARNESS_ARTIFICIAL_WAIT_MS | **0** |
| TRUE_RSC_END_TO_ROUTE_COMMIT_P95 | **1045ms** |
| TRUE_ROUTE_COMMIT_TO_USABLE_P95 | **246ms** |
| home_to_login USABLE_P95 | 2388ms |
| home_to_support USABLE_P95 | 2277ms |

**Interpretation:** The binding slow interval is real user-visible latency to route-specific destination markers (`login-form` password field, `#support-form-heading`). It is **not** inflated by `networkidle`, fixed sleeps, or generic `body` visibility.

Decomposition on validated harness:
- **Post-RSC router/commit stall is material** (`TRUE_RSC_END_TO_ROUTE_COMMIT` P95 ≈1.0s).
- **Post-commit render to interactive is comparatively small** (`TRUE_ROUTE_COMMIT_TO_USABLE` P95 ≈246ms).
- Old label `ROUTER_WAIT` ≈ time-to-pathname-change; it bundles RSC flight + post-RSC commit and remains directionally valid for total usable, but R3 splits the interval correctly.

## §3 Trace samples (12 slow, Playwright zip + long-task probe)

| Field | Value |
|-------|-------|
| ROUTER_WAIT_TRACE_DOMINANT_CAUSE | **CHUNK_PARSE_EVAL / concurrent RSC+chunk network** |

Slow samples show heavy overlapping `/_next/static/chunks/*` and multiple `_rsc` streams during the navigation window. Several trace interval calculations were negative due to async multi-RSC completion ordering; harness timestamps (§2) are authoritative for interval sizing.

Representative slow login sample (harness):
- RSC_END → HISTORY_URL_CHANGE: **720ms**
- HISTORY_URL_CHANGE → password interactive: **81ms**
- Overlapping prefetch: `groups?_rsc`, auth layout chunks, branding image

## §4 PublicShell A/B (diagnostic intercept only)

| Cohort | login P95 | support P95 |
|--------|-----------|-------------|
| baseline | 4007 | 1853 |
| session intercept | 15900 | 16163 |
| config intercept | 20019 | 14546 |
| both intercept | 12825 | 14321 |

| Classification | Result |
|----------------|--------|
| SESSION_BOOTSTRAP_CONTENTION | **NOT_PROVEN** |
| PUBLIC_CONFIG_CONTENTION | **NOT_PROVEN** |
| COMBINED_CONTENTION | **NOT_PROVEN** |

Intercept cohorts **degraded** latency vs baseline (likely route-fulfill side effects / broken parallel fetches), so this experiment does **not** authorize Hypothesis E. It also does **not** positively disprove bootstrap cost — methodology insufficient for fulfillment-based A/B.

## §5 Network concurrency (60 harness samples)

| Field | Rate |
|-------|------|
| RSC_COMPETES_FOR_CONNECTION | **YES on 92%** |
| DEFERRED_PREFETCH_QUEUE_OVERLAPS_NAV | **YES on 87%** |
| RSC_COMPETES_FOR_MAIN_THREAD_CALLBACK | **YES** (inferred: chunk eval + long RSC download windows align with post-RSC commit delay; long-task observer captured tasks in some samples) |

Overlapping request classes during nav: deferred route `_rsc` prefetches (`/groups`, `/register`), destination `_rsc`, auth layout chunks, session/config XHR, branding assets.

## §6 Destination-shell dependencies

See `destination-shell-deps-audit.md`. No audited component **blocks route commit** by code structure. `AuthCsrfBootstrap` can delay **submit** interactivity only.

## §7 Decision (R3)

| Field | Value |
|-------|-------|
| PUBLICSHELL_BOOTSTRAP_CONTENTION | **NOT_PROVEN** (A/B inconclusive/degraded) |
| NEXT_HYPOTHESIS | **Idle/deferred prefetch RSC contention** — suppress or deprioritize `PublicRoutePrefetch` deferred queue during hydration + first 1–2s navigation window; ensure navigation `_rsc` wins connection/main-thread over background `/groups`+`/register` prefetches |
| SOURCE_CHANGE_REQUIRED | **NO** (R3 diagnostic complete; implement only after targeted local proof) |
| Hypothesis E authorized? | **NO** |

## Artifacts

- `router-wait-harness-validation.json`
- `publicshell-ab-contention.json`
- `router-wait-trace.md` + `router-wait-traces/*.zip`
- `destination-shell-deps-audit.md`
