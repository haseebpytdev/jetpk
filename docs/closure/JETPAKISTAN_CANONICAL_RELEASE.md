# JetPakistan — Canonical Release Manifest

**Closure date:** 2026-09-21  
**Tag (when cut):** `jetpakistan-recovered-final-20260921`

## Identity (filled at stamp)

| Field | Value |
|---|---|
| FINAL_MAIN_SHA | _pending post-docs tip → filled in release-lock.json_ |
| REMOTE_MAIN_SHA | same |
| PRODUCTION_RUNTIME_SHA | same |
| PUBLIC_BUILD_SOURCE_SHA | same |
| DASHBOARD_BUILD_SOURCE_SHA | same |
| PUBLIC_BUILD_ID | _from host after dual build_ |
| DASHBOARD_BUILD_ID | _from host after dual build_ |
| RUNTIME_MARKER | _from host `.jetpk-runtime-marker`_ |
| ROLLBACK_SHA | `cbd7686feadd35773fd0b597117538b8b99b59fa` (prior certified runtime) |

Machine truth: `docs/closure/jetpakistan-release-lock.json`.

## Architecture

- Public UI: Next 15 (`frontend/`, PM2 `jetpk-public-frontend` :3010)
- Dashboards: Next (`dashboard/`, PM2 `jetpk-dashboard` :3001)
- Authority API/CMS: Laravel (`/home/pkjetp/jetpk_app`)
- Edge: OpenLiteSpeed → Next for flight/group public shells; Laravel for API/CMS/catch-all

## Route ownership

See `deploy/openlitespeed/jetpakistan-vhost-routes.conf` and `docs/jetpk/OLS-FLIGHTS-SHORT-URL-NEXT-PROXY.md`.

- Short URL owner: **NEXT**
- OLS config: **repo-tracked + assert script**

## CMS authority

Homepage H1 = CMS `headline` + `headline_highlight` (explicit blank ⇒ no JetPakistan fallback). Hero content-driven height.

## Group authority

`GROUP_SEARCH_FIELDS=3` — Airline, Sector, Date. Category cards below search; no Category field in search box. Homepage Group mode + `/groups/search` same contract.

## Ask authority

Ask JetPakistan public FAB/API; no commercial mutations in closure UAT.

## SEO / AEO / GEO authority

`docs/closure/SEO-AEO-GEO/` — consolidation `11-FINAL-CONSOLIDATION.md`. IndexNow NOT_APPLICABLE (documented).

## Short URL authority

Class-B opaque refs; OLS GET/HEAD proxy; SSR resolve via absolute Laravel URL; no search_id in browser URL.

## Performance authority

`docs/evidence/jp-final-perf-cert-cbd7686f/SUMMARY.md`  
Return metric: `BROWSER_RENDER_MS` (`05-return-metric-equivalence.md`).

## Functional authority

`docs/evidence/jp-final-perf-cert-cbd7686f/06-functional-matrix/`  
Homepage/Group owner: `09-homepage-group-uat/`.

## Retired / protected paths

Inventory: `docs/closure/JETPAKISTAN_RETIREMENT_INVENTORY.md`  
Policy: `docs/JETPAKISTAN_RELEASE_GUARD_POLICY.md`

## Rollback procedure

1. Restore app tree / release labeled by `ROLLBACK_SHA`
2. Restore OLS from `vhconf.conf.bak-shorturl-*` / `bak-jp-ols-routes-*` if route rules changed
3. Preserve `.env*` and storage
4. Rebuild Next from rollback SHA if binaries diverge
5. Stamp `.jetpk-runtime-sha` to rollback; verify `/` + `/flights/s/{ref}` + `/groups`
6. Forward-restore to canonical when drill complete — do not leave rolled back

## Evidence locations

- Perf: `docs/evidence/jp-final-perf-cert-cbd7686f/`
- SEO: `docs/closure/SEO-AEO-GEO/`
- OLS: `docs/jetpk/OLS-FLIGHTS-SHORT-URL-NEXT-PROXY.md`
- Heartbeat: `docs/closure/RECOVERY-HEARTBEAT.json`
