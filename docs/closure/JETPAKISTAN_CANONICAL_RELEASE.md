# JetPakistan — Canonical Release Manifest

**Closure date:** 2026-09-21  
**Annotated application tag (immutable):** `jetpakistan-recovered-final-20260921` → `78dadc7b330ca24a6183241458dd8d1f6aa0d454`

## Identity (two SHAs)

| Field | Value | Role |
|---|---|---|
| **application_release_sha** | `78dadc7b330ca24a6183241458dd8d1f6aa0d454` | Immutable binary authority; tag target |
| **closure_metadata_commit_sha** | _(filled in release-lock after metadata commit)_ | Docs/lock/CI correction tip; **not** binary source |
| PUBLIC_BUILD_ID | `wmNT0P2lJftqG2n9tM6gp` | Built from `application_release_sha` |
| DASHBOARD_BUILD_ID | `3TyyvdcNScpOGj0oCkQuT` | Built from `application_release_sha` |
| RUNTIME_MARKER | `jp-final-78dadc7b-20260921T085837Z` | Host marker after fresh dual rebuild |
| ROLLBACK_SHA | `cbd7686feadd35773fd0b597117538b8b99b59fa` | Prior certified runtime |

Machine truth: `docs/closure/jetpakistan-release-lock.json` (schema v2).

### Provenance rules

- Host `PRODUCTION_RUNTIME_SHA` / `PUBLIC_BUILD_SOURCE_SHA` / `DASHBOARD_BUILD_SOURCE_SHA` **must** equal `application_release_sha`.
- `REMOTE_MAIN` may equal `closure_metadata_commit_sha` after a metadata-only FF.
- Do not label the metadata commit as the source of binaries unless those binaries were rebuilt from that tree.
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
