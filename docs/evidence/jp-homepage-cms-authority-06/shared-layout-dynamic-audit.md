# Authority-06 R2 — shared layout / RSC dynamicness audit

**Production SHA:** `2c521c73ba18fba0947bb1a27e738c3c0602a792`  
**PUBLIC_BUILD_ID:** `cDqLQnU11t6QnUKL1LNaK`

## Summary

| Field | Finding |
|-------|---------|
| `ROOT_LAYOUT_DYNAMIC` | **PARTIAL** — `generateMetadata()` awaits `PublicConfigService.getConfig()` (ISR tag `public-config`, revalidate 60s server-side). Root `layout.tsx` body is static. |
| `AUTH_LAYOUT_DYNAMIC` | **NO** — anonymous `PublicShell`, no `cookies()`/`force-dynamic`. |
| `PUBLIC_LAYOUT_DYNAMIC` | **NO** — same anonymous static shell pattern. |
| `SHARED_RSC_CACHEABLE` | **YES** for target marketing routes (`force-static` on `/`, `/login`, `/register`, `/support`, `/terms`, `/privacy`, `/about-us` group). |
| `DYNAMIC_TRIGGER` | Client-side `PublicShell` post-hydration `fetchSessionBootstrap()` + `PublicConfigService.getConfig()` (`cache: no-store` in browser); competes with early `router.prefetch`. `PublicRoutePrefetch` priority routes deferred via `setTimeout(0)` after `useEffect` (post-hydration). Deferred idle queue starts at **2500ms** and staggers 7 routes @ 350ms — can flood RSC pipe before/at early click. |

## Route ancestry (soft-nav targets)

```
app/layout.tsx (metadata fetch only)
  └─ (public)/layout.tsx OR (auth)/layout.tsx OR app/page.tsx
       └─ PublicShell (client)
            ├─ PublicRoutePrefetch (client, useEffect + timers)
            ├─ SiteHeader
            └─ page (static segment where configured)
```

## Hypothesis focus (R2)

Early-click slowness is **not** explained by per-page `force-dynamic` on login/about/support. More likely:

1. Prefetch starts only after hydration + `useEffect` + `setTimeout(0)`.
2. Navigation RSC may not reuse prefetch response (`_rsc` tree variant / router cache).
3. Attribution `ROUTER_WAIT` may include time until pathname changes while RSC is in flight (needs decomposed cert).
