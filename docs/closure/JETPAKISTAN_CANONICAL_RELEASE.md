# JetPakistan — Canonical Release Manifest

**Current production application source (verified 2026-09-30):** `feef2e7fa489cee778b81c6db370946394337999`  
**Dashboard build ID (verified):** `m1WsmAjtpdlOgryIYPtp7`  
**Prior certified public/application release (2026-09-21):** `78dadc7b330ca24a6183241458dd8d1f6aa0d454`  
**Annotated application tag (immutable prior):** `jetpakistan-recovered-final-20260921` → `78dadc7b330ca24a6183241458dd8d1f6aa0d454`

## Identity (component-aware)

| Field | Value | Role |
|---|---|---|
| **application_release_sha** | `feef2e7fa489cee778b81c6db370946394337999` | Current verified Laravel/application production source |
| **closure_metadata_commit_sha** | _(see release-lock)_ | Docs/lock tip; **not** binary source unless rebuilt |
| PUBLIC_BUILD_ID | `wmNT0P2lJftqG2n9tM6gp` | Last verified public Next build (from prior certified release `78dadc7b`) |
| DASHBOARD_BUILD_ID | `m1WsmAjtpdlOgryIYPtp7` | Built/deployed with Dashboard operational recovery |
| RUNTIME_MARKER | `dor-b5b6-gap-20260930T213805Z` | Host marker for gap deploy |
| ROLLBACK_SHA | `5c1b3fd6dd114f5d0f11f6737e368f5b122a587e` | Pre-gap Dashboard deploy source |

Machine truth: `docs/closure/jetpakistan-release-lock.json` (schema v3 — component-specific provenance allowed).

### Provenance rules

- Host `PRODUCTION_RUNTIME_SHA` **must** equal `application_release_sha`.
- `DASHBOARD_BUILD_SOURCE_SHA` / `PUBLIC_BUILD_SOURCE_SHA` may differ when `component_parity.require_all_source_shas_equal` is false.
- Do not claim public Next rebuild from `feef2e7f` without build evidence.
- Do not stamp feature branches (including AirBlue Zapways-05) as deployed.
- Existing annotated tag is **not** moved for metadata corrections.

## Architecture

- Public UI: Next 15 (`frontend/`, PM2 `jetpk-public-frontend` :3010)
- Dashboards: Next (`dashboard/`, PM2 `jetpk-dashboard` :3001)
- Authority API/CMS: Laravel (`/home/pkjetp/jetpk_app`)
- Edge: OpenLiteSpeed → Next for flight/group public shells; Laravel for API/CMS/catch-all

## Route ownership

See `deploy/openlitespeed/jetpakistan-vhost-routes.conf` and `docs/jetpk/OLS-FLIGHTS-SHORT-URL-NEXT-PROXY.md`.

- Short URL owner: **NEXT**
- OLS config: **repo-tracked + assert script**

## CMS / Group / Ask / SEO / Short URL / Performance

Unchanged product architecture. Return metric: `BROWSER_RENDER_MS`.  
SEO/AEO/GEO: `docs/closure/SEO-AEO-GEO/11-FINAL-CONSOLIDATION.md`. IndexNow NOT_APPLICABLE.  
Perf authority: `docs/evidence/jp-final-perf-cert-cbd7686f/SUMMARY.md`.

## Retirement

`docs/closure/JETPAKISTAN_RETIREMENT_INVENTORY.md`

- `ZERO_REFERENCE_RETIREMENT=PASS_FOR_PROVEN_ITEMS`
- `UNKNOWN_RETIREMENT_ITEMS` retained (not deleted)

## Rollback procedure

1. Restore app / pointer to `ROLLBACK_SHA` (`cbd7686f`)
2. Restore OLS from `vhconf.conf.bak-shorturl-*` / `bak-jp-ols-routes-*` if needed
3. Preserve `.env*` and storage
4. Rebuild Next from rollback SHA if binaries diverge
5. Verify `/` + `/flights/s/{ref}` + `/groups`
6. Forward-restore to `application_release_sha` — do not leave rolled back
