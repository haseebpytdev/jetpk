# Same-SHA performance certification — b45975e3

## Identity

| Field | Value |
|---|---|
| RUNTIME_SHA | `b45975e36004cf74c9370e71a358fcb38de8ab69` |
| PUBLIC_BUILD_ID | `S49jLxNux1bMJZD6Wx_U6` |
| ROLLBACK_SHA | `ca0becc54c2aca165e236998fd2a975c3026bfa8` (prior) / `ee7c6012` (safe prod before cache) |
| Architecture | React `cache` → `unstable_cache` → Laravel `no-store` (TTL 3600) |

## Gates

| Gate | Result | Evidence |
|---|---|---|
| Soft-nav host N=10 | **PASS** 10/10 (worst 1477ms) | `01-soft-nav/` |
| Soft-nav host N=20 | **PASS** 10/10 (worst **242ms**) | `01-soft-nav/soft-nav-n20-host.json` |
| Traveler N≥30 P95≤2000 | **PASS** valid=30 `total_p95=1770` dup=0 | `02-traveler/` |
| Return Pair N≥30 post-supplier ≤1000 | **PASS** valid=30 `poll_total_p95=0.652` dup=0 | `03-return-pair/` |
| Pair↔Segmented N≥20 each | **PASS** pair via return N=30; segmented N=20 `poll=0.801` dup=0 | `04-pair-segmented/` |

## Cache proof

- PM2: ~393 HIT / 6 MISS; HIT ⇒ `LARAVEL_PUBLIC_*_CALLS=0`
- Local RSC :3010: 10–30ms
- Revalidate: `POST :3010/api/internal/revalidate-public-content` (401 without secret, 200 with)

## Overall

**SAME_SHA_PERF_CERT=PASS**

Next: SEO recovery / short URLs / AEO-GEO / parity / retirement / release-lock (per recovery plan).
