# JetPakistan — Canonical Release Manifest

**Closure date:** 2026-09-21  
**Tag:** `jetpakistan-recovered-final-20260921`

## Identity

| Field | Value |
|---|---|
| FINAL_MAIN_SHA | `1fc2dad1386d417d128473891afbc47c0ba330a8` (dual-build tip; lock-stamp docs tip follows) |
| PUBLIC_BUILD_ID | `Mxj-CVOg4w_poyBeBzoIp` |
| DASHBOARD_BUILD_ID | `E4_XqZnXxChe1Bdw4rJ0l` |
| RUNTIME_MARKER | `jp-final-1fc2dad1-20260921T081450Z` |
| ROLLBACK_SHA | `cbd7686feadd35773fd0b597117538b8b99b59fa` |

Machine truth after stamp commit: `docs/closure/jetpakistan-release-lock.json`.

## Architecture

- Public UI: Next 15 (`frontend/`, PM2 `jetpk-public-frontend` :3010)
- Dashboards: Next (`dashboard/`, PM2 `jetpk-dashboard` :3001)
- Authority API/CMS: Laravel (`/home/pkjetp/jetpk_app`)
- Edge: OpenLiteSpeed → Next for flight/group public shells; Laravel for API/CMS/catch-all

## Route ownership

See `deploy/openlitespeed/jetpakistan-vhost-routes.conf` and `docs/jetpk/OLS-FLIGHTS-SHORT-URL-NEXT-PROXY.md`.

- Short URL owner: **NEXT**
- OLS config: **repo-tracked + assert script** (`docs/evidence/jp-final-perf-cert-cbd7686f/10-ols-assert-final.log`)

## CMS / Group / Ask / SEO / Short URL / Performance

Unchanged from certified `cbd7686f` evidence packs. Return metric: `BROWSER_RENDER_MS`.  
SEO/AEO/GEO: `docs/closure/SEO-AEO-GEO/11-FINAL-CONSOLIDATION.md`. IndexNow NOT_APPLICABLE.

## Retirement

`docs/closure/JETPAKISTAN_RETIREMENT_INVENTORY.md` — nested homepage duplicate deleted; host deep cleanup PARTIAL_SAFE.

## Rollback procedure

1. Restore app / pointer to `ROLLBACK_SHA` (`cbd7686f`)
2. Restore OLS from `vhconf.conf.bak-shorturl-*` / `bak-jp-ols-routes-*` if needed
3. Preserve `.env*` and storage
4. Rebuild Next from rollback SHA if binaries diverge
5. Verify `/` + `/flights/s/{ref}` + `/groups`
6. Forward-restore to canonical — do not leave rolled back
