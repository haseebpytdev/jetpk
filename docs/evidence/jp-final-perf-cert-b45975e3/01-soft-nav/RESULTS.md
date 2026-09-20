# Soft-nav gate — b45975e3

## Production

- RUNTIME=`b45975e36004cf74c9370e71a358fcb38de8ab69`
- PUBLIC_BUILD=`S49jLxNux1bMJZD6Wx_U6`
- Architecture: React `cache` → `unstable_cache` → Laravel `no-store` (TTL 3600)
- Cache proof: ~393 HIT / 6 MISS; HIT ⇒ `LARAVEL_PUBLIC_*_CALLS=0`
- Local RSC (127.0.0.1:3010): 10–30ms all soft-nav routes
- Public HTTPS RSC from host: 55–90ms

## Gate result

### Host Playwright (authoritative same-origin)

| N | Pass | Worst APP P95 |
|---|---|---|
| 10 | **10/10** | 1477ms (`support_home`) |
| 20 | **10/10** | **242ms** (`home_login`) |

Harness note: after hard `goto(from)`, wait for App Router readiness (`networkidle` + router/`site-logo-link`) before measuring soft clicks. This is setup readiness, not destination warming. Without it, i=0 samples were ~11s (near `waitForURL` timeout) while i≥1 were ~150ms.

### SOFT_NAV_GATE

**PASS** (host N=10 and N=20, all routes ≤1500ms; N=20 worst 242ms).

Proceed to same-SHA Traveler / Return Pair / Pair↔Segmented certification.
