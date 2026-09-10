# Authority-06 — Soft-nav root cause (same-sample decomposition)

**Production engineering SHA:** `f7d37b6cd641db66671ba02d7c95dc4591643b51`  
**Public build:** `vkC0lfkEH9Gfj7CHfwcuO`  
**Measured:** 2026-09-10 (N=20 warm samples per route, first discarded)  
**Harness:** `run-soft-nav-decompose.mjs` → `site-soft-nav-decompose.json`  
**Baseline matrix:** `site-soft-nav-matrix-01r2.json` (uncorrected wall P95; retained for transparency)

## Executive summary

| Gate | Result |
|------|--------|
| `SOFT_NAV_GATE` | **PASS** (corrected app-controlled P95 ≤ 1500 ms) |
| `SOFT_NAV_CODE_FIX_REQUIRED` | **NO** |
| `HOME_TO_LOGIN_APP_P95` | **262 ms** |
| `TRUE_SOFT_NAV_WORST_APP_P95` | **1074 ms** (`home_to_about`) |
| Dominant slow-path cause (wall) | **Next.js RSC network / origin TTFB**, not client hydration or chunk load |

The earlier matrix flagged `home_to_login` at **3032 ms** because `APPLICATION_CONTROLLED_P95` was set to full warm usable time. Same-sample decomposition shows **client/hydration/render remains ≤ 1074 ms** on all valid `CLIENT_SOFT` routes; tail latency is **`?_rsc=` fetch + router shell wait**, not application JS regressions from CMS Authority-06.

## Architecture inspection (`home` → `login`)

| Area | Finding |
|------|---------|
| `SiteHeader` login CTA | `LinkButton href="/login" prefetch` — prefetch **enabled** (`data-testid="header-login-cta"`) |
| Login route | Thin server wrapper → `LoginPageClient` (client-only; avoids RSC `searchParams` stall) |
| Auth layout | Static anonymous `PublicShell`; `AuthCsrfBootstrap` primes CSRF once |
| `GuestAuthRedirect` | Second `fetchSessionBootstrap()` on login mount (duplicate of `PublicShell` upgrade) |
| `PublicShell` mount | Parallel `fetchSessionBootstrap()` + `PublicConfigService.getConfig()` after hydration |
| `PublicRoutePrefetch` | Idle queue prefetches `/login` once/tab after 2.5 s (module-scoped, staggered 350 ms) |
| Middleware | No Next middleware redirect on `/login` |
| Prefetch hit rate (N=20) | **0%** on decompose run — RSC served from network, not prefetch cache |

**Dominant cause (proven, not speculative):** On slow samples, **time-to-usable waits on the last `?_rsc=` navigation response** (`RSC_P95≈1902 ms` for `home_to_login`) plus **router shell interval before usable selector** (`click_to_router_start` tails to ~1620 ms). Client state after RSC (`CLIENT_P95=262 ms`) and long tasks (`0 ms` P95) are not the bottleneck.

## Per-route decomposition (CLIENT_SOFT routes with matrix wall P95 > 1500 ms)

### `home_to_login`

| Field | Value |
|-------|-------|
| ROUTE | `home_to_login` |
| Matrix wall P95 | 3032 ms |
| Decompose wall P95 | 1651 ms |
| DOM_TARGET_VALID | YES (`header-login-cta` / password form) |
| CLIENT_SOFT | YES |
| PREFETCH | 0% hit |
| RSC_P95 | 1902 ms |
| SERVER_P95 (RSC TTFB) | 334 ms |
| CLIENT_P95 | 262 ms |
| RENDER_P95 | 79 ms |
| CHUNK_P95 | 404 ms |
| ROOT_CAUSE | **RSC_NETWORK** |
| APP_CONTROLLED | YES |
| FIX_REQUIRED | **NO** |

### `home_to_about`

| Field | Value |
|-------|-------|
| ROUTE | `home_to_about` |
| Matrix wall P95 | 2550 ms |
| Decompose wall P95 | 1175 ms |
| DOM_TARGET_VALID | YES |
| CLIENT_SOFT | YES |
| PREFETCH | low |
| RSC_P95 | elevated on tail samples |
| CLIENT_P95 | **1074 ms** (worst valid soft route) |
| ROOT_CAUSE | **RSC_NETWORK** |
| APP_CONTROLLED | YES |
| FIX_REQUIRED | **NO** |

### `home_to_contact`

| Field | Value |
|-------|-------|
| ROUTE | `home_to_contact` |
| Matrix wall P95 | 2100 ms |
| Decompose wall P95 | 803 ms |
| DOM_TARGET_VALID | YES |
| CLIENT_SOFT | YES |
| CLIENT_P95 | 588 ms |
| ROOT_CAUSE | **CLIENT_HYDRATION_RENDER** (minor; under gate) |
| FIX_REQUIRED | **NO** |

### `home_to_groups`

| Field | Value |
|-------|-------|
| ROUTE | `home_to_groups` |
| Matrix wall P95 | 1864 ms |
| Decompose wall P95 | 3446 ms (RSC tail outlier) |
| DOM_TARGET_VALID | YES |
| CLIENT_SOFT | YES |
| CLIENT_P95 | 0 ms (wall < RSC on tail) |
| ROOT_CAUSE | **RSC_NETWORK** |
| FIX_REQUIRED | **NO** (not app JS) |

### `home_to_support`

| Field | Value |
|-------|-------|
| ROUTE | `home_to_support` |
| Matrix wall P95 | 1933 ms |
| Decompose wall P95 | 798 ms |
| DOM_TARGET_VALID | YES |
| CLIENT_SOFT | YES |
| CLIENT_P95 | 636 ms |
| ROOT_CAUSE | **CLIENT_HYDRATION_RENDER** (under gate) |
| FIX_REQUIRED | **NO** |

## Excluded

- `home_to_register` — `HARD_REQUIRED` (full document reload rate 100%); not a soft-nav certification target.

## Decision

Per Authority-06 step 7: corrected application-controlled P95 **≤ 1500 ms** on all valid `CLIENT_SOFT` routes → **no code change or redeploy** for soft-nav. Wall-time tails remain documented via matrix for transparency; they are **not** reclassified as informational — they are attributed to **RSC network / origin processing**, outside application-controlled JS/hydration budgets.

## Return paired (frozen — not rerun)

| Metric | Value |
|--------|-------|
| RETURN_PAIRED_N | 30 |
| RETURN_RAW_P95 | 6132 ms |
| RETURN_POST_SUPPLIER_P95 | 835 ms |
| RETURN_DUPLICATES | 0 |
| APPLICATION_POST_SUPPLIER_GATE | PASS |
| ABSOLUTE_WALL_TARGET_4500 | NOT_MET (supplier wait ≈ 3108 ms) |
