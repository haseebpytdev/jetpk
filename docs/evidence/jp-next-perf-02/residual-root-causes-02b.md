# JP-NEXT-PERF-02B — residual root causes

## Fare → Traveler (application-controlled)

**Measured before (02A):** Book Now→shell P95 ≈ 6098 ms; FE overhead P95 ≈ 6098 ms; total P95 ≈ 20632 ms.

**Root cause (reproduced):**
1. Soft `router.push(/booking/passengers)` issued an RSC/document request that **never completed** while the results page still held in-flight resources.
2. Results page saturated the browser connection pool with:
   - Next.js `Link` default prefetch of `/` and `/login` RSC
   - next/font preloads (multiple woff2)
   - airline logo image fetches
   - CSRF token fetch
3. Hard document navigation then waited ~18s for passengers JS even when cached (`transferSize=0`, duration ≈ 18s) until the pool freed.

**Fix:**
- Authoritative `passengers_url` hard `location.assign` after releasing in-flight imgs/preloads (`window.stop`)
- `prefetch={false}` on public header/login/FAB links; `LinkButton` defaults to no prefetch
- Fewer font weights + `preload: false`
- Lazy native airline logos; CSRF fetch AbortController timeout 2.5s
- No commerce-gates I/O on Book Now critical path when guest booking is default-enabled

**After (r8 build `U9-V-YGZgQ3qKayMCp4BX`):** shell P95 780; FE overhead P95 1518.

## Return Pair ↔ Segmented

**Root cause:** `view=` previously cleared READY and re-fetched Laravel representation (same `search_id`, not new supplier search).

**Fix (earlier 02B):** client view cache + `history.replaceState` + no full skeleton.

**After:** Pair→Seg P95 131; Seg→Pair P95 227; supplier calls 0.

## Groups cold

`jpAuditReset` is harness-only. Clean cold n=20 P95 3521 ≤ 4000. No Groups source change.

## Data → render

Initial visible cards capped (4) + transition expand. P95 460 ≤ 500.

## Nearby / One Way

Documented attribution only; supplier-dominated where applicable. Laravel non-supplier not exposed via Server-Timing on this runtime.
