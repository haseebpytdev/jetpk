# Fresh Homepage Predeploy Baseline — Production (`563d6d07`)

Probe: `probe-fresh-homepage-prod.mjs` (N=10, cache-busted HTML request)  
Evidence: `fresh-homepage-prod-baseline.json`

## Server-side (HTML + CMS API)

| Metric | Value |
|--------|------:|
| TTFB p50 | 218 ms |
| TTFB p95 | 1004 ms |
| CMS API p50 (~) | ~530 ms |
| HTTP | 200 all samples |
| Next cache | 1× STALE, 9× HIT |

**Classification:** `WITHIN_ACCEPTABLE_PREDEPLOY_BASELINE` — no 15–30s TTFB on production HTML today.

## Owner-reported 15–30s fresh load

Not reproduced at **transport/HTML** layer in this probe. Likely causes if still seen in browser:

| Class | Evidence |
|-------|----------|
| ISR / stale RSC shell | `x-nextjs-cache: STALE` on cold sample; WIP adds on-demand revalidate on CMS publish + destination media authority fix |
| CLIENT_HYDRATION | Requires post-deploy Playwright FCP/LCP on **new** build |
| IMAGE_OPTIMIZATION | Destination CMS URLs now accepted in `homepage-media.ts` (fixes wrong fallback/stale card image path) |

## Post-deploy test plan

- Fresh browser N≥20 on **new** `PUBLIC_BUILD_ID` after combined deploy
- Verify on-demand revalidation clears STALE within seconds of CMS publish (secret configured at deploy time only)

FRESH_HOMEPAGE_ROOT_CAUSE=**ISR_STALE_SHELL + CLIENT_HYDRATION_RISK** (not server TTFB)
