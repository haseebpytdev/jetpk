# JP-OPS-09 production closure

**Date (UTC):** 2026-10-09  
**PR:** #64 (merged)  
**MERGED_MAIN_SHA:** `733f04d9ddc55f83866b39a11c99fe7e6108eb3c`  
**PRODUCTION_SHA_BEFORE:** `50ae55c47161d211748ca2cc1204855a52442422`  
**Backup:** `20261009T074212Z` (`/home/pkjetp/backups/`)

## Pre-deploy

- `jetpk/main` at `733f04d9ddc55f83866b39a11c99fe7e6108eb3c` (server `jetpk_git` updated).
- Pre-deploy runtime: `deploy-sha.txt` / `.jetpk-runtime-sha` = `50ae55c…`.
- Zapways TLS dir `/home/pkjetp/jetpk_secrets/zapways`: `750` root:nogroup; key `640`; PHP `nobody` can read dir + key; key not world-readable. **ZAPWAYS_TLS_PERMISSION_REGRESSION=NO**

## Deploy

- Staging: dashboard-only diff vs `50ae55c…` — tracked `stage-release-from-sha.sh` with `RELEASE_SCOPE=frontend` yields `EMPTY_RUNTIME_MANIFEST`; used protected manual stage (`frontend/package.json` anchor + Laravel + dashboard archives) → `/home/pkjetp/releases/jetpk-20261009T074917Z`.
- `jetpk-deploy.sh` + `jetpk-next-build.sh` as **pkjetp** (no sudo composer).
- Post-deploy: `.jetpk-runtime-sha` = `733f04d9…`; `deploy-sha.txt` normalized to same; `dashboard/.jetpk-dashboard-source-sha` aligned to `733f04d9…`.
- **DASHBOARD_BUILD_ID:** `szyvRsj7IqYQwALIYdr5N`
- **HASH_SOURCE_PARITY:** PASS (runtime + authorized + dashboard source SHA = `733f04d9…` for this release)
- `jetpk-pre-proxy-gate.sh`: PASS; PM2 `jetpk-dashboard` / `jetpk-public-frontend` online.

## QA API connection cleanup

| Metric | Value |
|--------|-------|
| TOTAL_CONNECTIONS_BEFORE | 21 |
| QA_DELETE_CANDIDATES (dry-run) | 15 |
| QA_RECORDS_DELETED | 15 |
| REVIEW_RECORDS_LEFT | 3 |
| TOTAL_CONNECTIONS_AFTER | 6 |
| JPQA_WRITE_ROWS_REMAINING | 0 |

Remaining rows (DB names):

| id | name | Class |
|----|------|-------|
| 1 | JEtPK Binham Sabre | REVIEW |
| 2 | PIA JetPK | KEEP_REAL |
| 4 | sabre-sandbox-qa | REVIEW |
| 5 | SMTP JetPakistan LIVE | KEEP_REAL |
| 6 | JPak Group | REVIEW |
| 22 | AirBlue Zapways TEST v2 | KEEP_REAL (protected id) |

**REAL_SUPPLIER_CALLS_DURING_CLEANUP=0**

## Production browser UAT (admin QA)

- API connections: no `JPQA-WRITE-*` cards; AirBlue TEST v2 visible; credentials masked.
- Bookings View → full page; no primary drawer; refresh deep link OK.
- PNR View → linked booking full page; no drawer.
- Live preview banner: absent; placeholder payment/refund/cancel actions: absent.
- **HYDRATION_ERRORS=0 PAGE_ERRORS=0 UNEXPECTED_NETWORK_FAILURES=0**

## Supplier call accounting

**NEW_ZAPWAYS_CALLS=0** (and no book/ticket/cancel/payment supplier traffic during UAT).

## Notes

- Three legacy connections use display names that do not exactly match `PROTECTED_CONNECTION_NAMES` strings; classifier correctly leaves them in **REVIEW** (untouched).
- Booking management nav does not include separate Documents / Cancellation / Assignment sections (product scope); passengers section hidden when passenger list is empty (honest empty state).

**FINAL_STATUS:** FULL_PASS
