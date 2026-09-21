# Soft-nav + same-SHA perf gate status

## Current production tip

`5f78a5e138c9cdf649f2e1429a6c5cc3e55ca0e6` — SEO recovery + short-URL foundation  
PUBLIC_BUILD=`GpIljRfA8zEVcmPGCGRQS`  
Rollback=`b45975e36004cf74c9370e71a358fcb38de8ab69`

## Soft-nav

**SOFT_NAV_GATE=PASS** (host Playwright, same-origin)

| N | Pass | Worst APP P95 |
|---|---|---|
| 10 | 10/10 | 561ms |
| 20 | 10/10 | **333ms** |

Evidence: `docs/evidence/jp-final-perf-cert-5f78a5e1/01-soft-nav/`

## Same-SHA performance

**SAME_SHA_PERF_CERT=PASS** — `docs/evidence/jp-final-perf-cert-5f78a5e1/SUMMARY.md`

| Gate | Result |
|---|---|
| Traveler N≥30 P95≤2000 | PASS (`total_p95=1548`, dup=0) |
| Return Pair N≥30 post-supplier ≤1000 | PASS (`poll_total_p95=0.581`, dup=0) |
| Pair↔Segmented N≥20 each | PASS (pair via return N=30; segmented N=20 `poll=0.773`, dup=0) |

## SEO recovery

| Gate | Status |
|---|---|
| SEO audit WP0 | PASS |
| Metadata / FAQPage / robots / sitemap | IMPROVED (live probes PASS) |
| Short URL foundation | PARTIAL (alias + resolve; mint cutover deferred) |
| AEO / GEO | PARTIAL — continue §49 |
| Contact policy | KEEP 308 → `/about-us` |

## Next open gates

AEO/GEO §49 completion → search short-ref mint (soft-nav-safe) → parity / retirement / release-lock / main FF
