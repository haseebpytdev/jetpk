# JP-BOOKING-MGMT-10 — Production closure

**Date (UTC):** 2026-10-09  
**PR:** #65 (merged)  
**Authorized SHA:** `0cffc7d507af42fd79b0269e2fb8a34bbef64a07`  
**Production SHA before:** `733f04d9ddc55f83866b39a11c99fe7e6108eb3c`  
**Dashboard build before:** `szyvRsj7IqYQwALIYdr5N`  
**Dashboard build after:** `Zycw6KaLjjyGopIy9o-F4`  
**Public frontend build after (incidental rebuild):** `0R6XtBT5NWBvLORcaE4bv`

## Phase 1 — Pre-deploy

| Check | Result |
|--------|--------|
| `jetpk/main` | `0cffc7d507af42fd79b0269e2fb8a34bbef64a07` |
| PR #65 merged | Yes |
| Backup timestamp | `20261009T085629Z` |
| `BACKUP_PATH` | `/home/pkjetp/backups/jetpk_app-20261009T085629Z.tar.gz`, `jetpk-db-20261009T085629Z.sql.gz`, `public_html-20261009T085629Z.tar.gz` |
| Zapways secrets outside release tree | `/home/pkjetp/jetpk_secrets/zapways` (present; key contents not read) |

## Phase 2 — Staging workflow proof

**Manifest self-test** (`bash scripts/jetpk/test-stage-release-manifest.sh` on server git at authorized SHA):

- `STAGE_RELEASE_MANIFEST_SELFTEST=PASS`
- `ALL_SCOPE_CASES_PASS=YES` (all seven scope cases PASS)

**Dry run** (`BASE_SHA=733f04d9…`, `AUTHORIZED_SHA=0cffc7d5…`, `RELEASE_SCOPE=frontend`, `DRY_RUN=1`):

- `ACTUAL_DEPLOY_DRY_RUN=PASS`
- `MANIFEST_UPLOADABLE_COUNT=11`
- `EMPTY_RUNTIME_MANIFEST=NO`
- `UNEXPECTED_RUNTIME_FILES=0`
- Runtime manifest included scoped Laravel + dashboard files and `frontend/package.json` anchor only; excluded docs/tests/secrets/markdown.

## Phase 3 — Stage

| Field | Value |
|--------|--------|
| `TRACKED_STAGE_SCRIPT_USED` | YES (`scripts/jetpk/stage-release-from-sha.sh`) |
| `MANUAL_STAGE_WORKAROUND_USED` | NO |
| `RELEASE_TIMESTAMP` | `20261009T090046Z` |
| `RELEASE_ARCHIVE` | `/home/pkjetp/jetpk_git/tmp/releases/jetpk-release-0cffc7d507af-20261009T090046Z.tar.gz` |
| `STAGED_SOURCE_SHA` | `0cffc7d507af42fd79b0269e2fb8a34bbef64a07` |
| `STAGED_RUNTIME_FILES` | 11 |
| `STAGED_DELETIONS` | 0 |

## Phase 4 — Deploy and runtime gates

- Deploy: `bash /tmp/jetpk-deploy.sh …/jetpk-20261009T090046Z 20261009T090046Z` → `PRODUCTION_RUNTIME_SHA=0cffc7d5…`
- Dashboard build: `npm ci` + `npm run build` as **pkjetp** (dedicated `/tmp/jetpk-dash-build-only.sh` after initial `jetpk-next-build.sh` hit transient `frontend` `npm ci` ENOTEMPTY; dashboard rebuild required because `PUBLIC_ONLY=1` skip was used once on retry path).
- Pre-proxy: `PRE_PROXY_GATE_PASS`

**Release markers (all `0cffc7d507af42fd79b0269e2fb8a34bbef64a07`):**

- `storage/app/deploy-sha.txt`
- `.jetpk-runtime-sha`
- `.jetpk-authorized-sha`
- `dashboard/.jetpk-dashboard-source-sha`

| Gate | Result |
|------|--------|
| `HASH_SOURCE_PARITY` | PASS |
| `RUNTIME_HEALTH` | PASS |
| `PRE_PROXY_GATE` | PASS |

## Phase 5 — Zapways TLS (filesystem only)

- No Zapways network calls.
- TLS directory present under `/home/pkjetp/jetpk_secrets/zapways`.
- Client key mode `640` (not world-readable); unchanged from pre-closure layout.
- `ZAPWAYS_TLS_PERMISSION_REGRESSION=NO`
- `NEW_ZAPWAYS_CALLS=0`

## Phases 6–7 — Supplier display name normalization

Dry-run then execute on production (`supplier:normalize-connection-names`):

| id | from | to |
|----|------|-----|
| 1 | JEtPK Binham Sabre | JetPK Binham Sabre |
| 4 | sabre-sandbox-qa | Sabre Sandbox QA (CERT) |
| 6 | JPak Group | JPAK Group |

- `NORMALIZE_APPLIED=3`
- Post-execute dry-run: `NORMALIZE_CANDIDATES_AFTER=0`
- `SUPPLIER_NAME_NORMALIZATION=PASS`
- `REAL_SUPPLIER_CALLS=0`

## Phases 8–20 — Production UAT (non-mutating)

Automated browser certification: local Playwright run against production (QA admin; credentials from secure local vault only; not committed).

**QA booking:** `JPQA-20261008-BOOKING`  
**PNR list → linked booking:** first row navigated to full booking page (`HDC7E3KM`, no drawer).

| Area | Result |
|------|--------|
| Full-page booking view / deep link / refresh | PASS |
| All management sections | PASS (or honest empty where applicable) |
| Documents | Authoritative; downloads N/A (no rows) |
| Cancellation / refund / payment record forms | Present; **not submitted** |
| Communication send | Gated (`COMMUNICATION_SEND_GATED=YES`) |
| Notes | UI PASS (`NOTE_MUTATION_USED=NO`) |
| Live preview / fixture leakage | NO |
| Responsive overflow | PASS (desktop / tablet / mobile) |
| `HYDRATION_ERRORS` | 0 |
| `PAGE_ERRORS` | 0 |
| `UNEXPECTED_NETWORK_FAILURES` | 0 |
| External side-effect gate | All zero / NO submissions |

## Certification summary

| Field | Value |
|--------|--------|
| `PR_NUMBER` | 65 |
| `MERGED_MAIN_SHA` / `PRODUCTION_SHA` | `0cffc7d507af42fd79b0269e2fb8a34bbef64a07` |
| `DASHBOARD_BUILD_ID` | `Zycw6KaLjjyGopIy9o-F4` |
| `HASH_SOURCE_PARITY` | PASS |
| `STAGE_RELEASE_MANIFEST_SELFTEST` | PASS |
| `ALL_SCOPE_CASES_PASS` | YES |
| `EMPTY_RUNTIME_MANIFEST_DEFECT` | CLOSED |
| `TRACKED_STAGE_SCRIPT_USED` | YES |
| `MANUAL_STAGE_WORKAROUND_USED` | NO |
| `SUPPLIER_NAME_NORMALIZATION` | PASS (`NORMALIZE_APPLIED=3`, `NORMALIZE_CANDIDATES_AFTER=0`) |
| `BOOKING_MANAGEMENT_FULL_PARITY` | YES |
| `HYDRATION_ERRORS` / `PAGE_ERRORS` / `UNEXPECTED_NETWORK_FAILURES` | 0 |
| External side-effect gate | `NEW_ZAPWAYS_CALLS=0`; no supplier cancel/booking/ticket/payment gateway mutations |
| `FINAL_STATUS` | FULL_PASS |
| `SENSITIVE_DATA_EXPOSED` | NO |
| `BUILD_NOTE` | Initial `jetpk-next-build.sh` encountered transient frontend `npm ci` ENOTEMPTY. Dashboard subsequently rebuilt successfully as **pkjetp** and production gates/UAT passed. |
| `BOOKING_MANAGEMENT_DEFECT` | NO |
| `DEPLOYMENT_ROBUSTNESS_BACKLOG` | YES (npm ENOTEMPTY not claimed fixed here) |

## Files changed (evidence only)

- `docs/evidence/jp-booking-mgmt-10/PRODUCTION-CLOSURE.md` (this file)
- `docs/evidence/jp-booking-mgmt-10/FINAL-REPORT.md`

## Rollback

1. Restore backups `20261009T085629Z`.
2. Redeploy previous runtime SHA `733f04d9…` via protected stage/deploy workflow.
3. Rebuild dashboard as **pkjetp** to prior build id if needed.
