# Soft-nav + same-SHA perf gate status

## Current production tip

`b45975e36004cf74c9370e71a358fcb38de8ab69` — persistent public CMS `unstable_cache`  
PUBLIC_BUILD=`S49jLxNux1bMJZD6Wx_U6`

## Soft-nav

**SOFT_NAV_GATE=PASS** (host Playwright, same-origin)

| N | Pass | Worst APP P95 |
|---|---|---|
| 10 | 10/10 | 1477ms |
| 20 | 10/10 | **242ms** |

Evidence: `docs/evidence/jp-final-perf-cert-b45975e3/01-soft-nav/`

Harness: App Router readiness wait after hard `goto(from)` (not destination warm).

Historical best `2bb48065` 7/10 superseded.

## Same-SHA performance

**SAME_SHA_PERF_CERT=PASS** — see `docs/evidence/jp-final-perf-cert-b45975e3/SUMMARY.md`

| Gate | Result |
|---|---|
| Traveler N≥30 P95≤2000 | PASS (`total_p95=1770`, dup=0) |
| Return Pair N≥30 post-supplier ≤1000 | PASS (`poll_total_p95=0.652`, dup=0) |
| Pair↔Segmented N≥20 each | PASS (pair via return N=30; segmented N=20 `poll=0.801`, dup=0) |

## Next open gates

SEO recovery / short URLs / AEO-GEO / parity / retirement / release-lock / main FF
