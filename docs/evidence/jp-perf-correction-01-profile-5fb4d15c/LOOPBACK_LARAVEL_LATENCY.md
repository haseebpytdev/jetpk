# Loopback Laravel latency (production 127.0.0.1:8088)

Date: 2026-09-19
Method: curl no-store from host to private Laravel listener

## Results (seconds)

config N=8: 0.135–0.226 (p50≈0.16, max 0.226)
faq N=5: 0.109–0.118
privacy N=5: 0.120–0.134

## Interpretation

Private Laravel handlers are **fast**. Browser-measured public-config p95 ~3.7s from the soft-nav diagnostic is **not** explained by Laravel handler CPU on loopback.

Likely contributors to soft-nav APP spikes remain:
1. Cold Next RSC when footer click precedes prefetch
2. Edge/public hop or concurrent load on browser→HTTPS `/laravel/...` probes
3. RSC flight multiplication / streaming under load

## Follow-up applied

`PublicRoutePrefetch`: promote `/privacy`, `/faq`, `/terms` to immediate priority prefetch (with login/register) so early footer soft-nav hits warm RSC more often.

Full N=20 soft-nav cert still **not ready** until post-deploy diagnostic shows worst APP P95 ≤1500.
