# Soft-nav + same-SHA perf gate status

## Current production tip

`5f78a5e138c9cdf649f2e1429a6c5cc3e55ca0e6` — SEO recovery + short-URL foundation  
PUBLIC_BUILD=`GpIljRfA8zEVcmPGCGRQS`  
Rollback=`b45975e36004cf74c9370e71a358fcb38de8ab69` (prior unstable_cache cert SHA)

## Soft-nav

**SOFT_NAV_GATE=PASS** on prior cert SHA `b45975e3` (host Playwright).  
Post-SEO deploy smoke: pending / in progress on `5f78a5e1` (additive redirects + metadata only; no soft-nav architecture change).

| N | Pass | Worst APP P95 | SHA |
|---|---|---|---|
| 10 | 10/10 | 1477ms | b45975e3 |
| 20 | 10/10 | **242ms** | b45975e3 |

Evidence: `docs/evidence/jp-final-perf-cert-b45975e3/01-soft-nav/`

## Same-SHA performance

**SAME_SHA_PERF_CERT=PASS** on `b45975e3` — see `docs/evidence/jp-final-perf-cert-b45975e3/SUMMARY.md`  
Recert on `5f78a5e1` required before retirement/release-lock (§51).

## SEO recovery (in progress)

| Gate | Status |
|---|---|
| SEO audit WP0 | PASS — `docs/closure/JETPAKISTAN_SEO_RECOVERY_AUDIT.md` |
| Metadata / FAQPage / robots / sitemap | IMPROVED — live probes PASS on `5f78a5e1` |
| Short URL foundation | PARTIAL — `/about` alias; `PublicShortRefService`; `/flights/s/[ref]`; mint cutover deferred |
| AEO / GEO | PARTIAL — checklist + FAQPage + sameAs |
| Contact policy | KEEP 308 → `/about-us` |

## Next open gates

Soft-nav smoke + same-SHA perf recert on `5f78a5e1` → finish AEO/GEO §49 → search short-ref mint → parity / retirement / release-lock / main FF
