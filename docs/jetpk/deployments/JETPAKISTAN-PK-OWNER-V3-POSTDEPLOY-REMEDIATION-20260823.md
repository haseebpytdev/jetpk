# JetPakistan Owner V3 Post-Deploy Remediation — Production Deployment

**Date:** 2026-08-23  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner retest state:** `RETEST_REQUIRED` (not PASS)  
**Deployment status:** `DEPLOYED` (protected scripts; restrained verification complete)

## SHA pins

| Role | SHA |
|------|-----|
| Previous production runtime | `26ff103287437b995074847a74be1cd227404594` |
| Deployed engineering runtime | `8911d208be9b42330c2157e6cd3d4a288c643d94` |
| Test harness (not deployed) | `93452d9b23608eba39467a9bca4ef621ec25d9b2` |
| Predeploy docs HEAD | `e4154a58b7e67134261c35faf23e53835bab5afd` |

**Important:** runtime activated strictly from Git object `8911d208`. Commits after engineering pin are test-harness/docs only.

## Scope activated

`AUTHORIZED_SHA=8911d208be9b42330c2157e6cd3d4a288c643d94`  
`BASE_SHA=26ff103287437b995074847a74be1cd227404594`  
**Deploy runtime file count: 39**  
**Migrations: 0**  
**UNEXPECTED_RUNTIME_SUBSYSTEMS: NONE**

Manifest: `tmp/_owner_v3_postdeploy_manifest_filtered.txt` / `tmp/owner-v3-postdeploy-remediation/runtime-manifest-filtered-8911d208.txt`

### Runtime areas

- Integration Hub failure isolation + configuration authority (Laravel)
- Legacy integration redirects (Dashboard + Laravel)
- CMS Homepage Builder + Pages management + media attach (Dashboard + Laravel)
- Checkout/Review display + exact PKR formatting + passenger null normalization (Public frontend)
- Public hero mobile/desktop media support

## Protected production deployment (executed)

### Predeploy checkpoint

| Gate | Result |
|------|--------|
| SSH_AUTH | PASS |
| HOMEPAGE / LOGIN / ABOUT / FAQ | PASS |
| Public PM2 `jetpk-public-frontend` | online (`pkjetp`) |
| Dashboard PM2 `jetpk-dashboard` | online |
| Old public build | `cwoD2Mw7-UfruNTmM3h3p` |
| OLS SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` (PASS) |
| `OTA_CLIENT_REQUIRE_LOGIN_OTP` | `false` preserved |
| OTP demo keys | present |
| Sabre safety keys | preserved |
| `MIGRATIONS_PENDING` | `0` |
| `NODE_MODULES_NON_PKJETP` / `NEXT_NON_PKJETP` | `0` / `0` |

Evidence: `tmp/owner-v3-postdeploy-remediation/predeploy-output.txt`

### Backup

| Field | Value |
|-------|-------|
| `BACKUP` | PASS |
| `BACKUP_ID` | `owner-v3-postdeploy-20260823T083313Z` |
| `BACKUP_INTEGRITY` | PASS |
| Rollback runtime | `26ff103287437b995074847a74be1cd227404594` |
| Rollback public build | `cwoD2Mw7-UfruNTmM3h3p` |

### Staging / activation

| Field | Value |
|-------|-------|
| Release | `/home/pkjetp/releases/jetpk-owner-v3-postdeploy-20260823T083107Z` |
| Archive | `tmp/releases/jetpk-owner-v3-postdeploy-8911d208be9b-20260823T083107Z.tar.gz` |
| `STAGED_SOURCE_PARITY` | PASS |
| `STAGED_RUNTIME_FILES` | 39 |
| Build user | `pkjetp` |
| Public `npm ci` / build | PASS |
| Dashboard `npm ci` / build | PASS |
| Old public build | `cwoD2Mw7-UfruNTmM3h3p` |
| New public build | `ARSJVIridIInsI-qoq0kd` |
| Dashboard build | `1pwMLYZZXVK9L4jzZOj75` |
| Public PM2 | online (`pkjetp`) |
| Dashboard PM2 | online (`pkjetp`) |
| `OLS_HASH` | PASS |
| `OWNERSHIP_DRIFT` | 0 |
| `LIVE_SOURCE_DRIFT` | 0 |
| Post-activate cache clear | PASS |
| `ROLLBACK_USED` | NO |

Evidence: `tmp/owner-v3-postdeploy-remediation/deploy-output.txt`

### Database migrations

| Field | Result |
|-------|--------|
| `MIGRATIONS` | 0 |
| `MIGRATIONS_PENDING` | 0 |

## Live restrained verification

Host: `https://jetpakistan.pk` only. No AbhiPay credentials configured. No commercial booking/payment/PNR actions.

| Gate | Result |
|------|--------|
| `IntegrationHubService::overview()` | PASS (HTTP 200 equivalent; no hub fatal) |
| `INTEGRATIONS_SERVER_ERROR` | 0 |
| `IntegrationRegistry` provider count | 13 |
| Sabre / AbhiPay registry entries | present |
| Legacy API Connections route | HTTP 200 (consolidated) |
| Legacy Settings integrations route | HTTP 200 (consolidated) |
| `CONFIGURATION_AUTHORITY` | PASS |
| `LEGACY_API_CONNECTIONS_CONSOLIDATED` | PASS |
| AbhiPay configured | NO (expected) |
| AbhiPay checkout available | NO (expected) |
| Public smoke `/` `/login` `/faq` | PASS |
| `CURRENT_HOMEPAGE_CONTENT_PRESERVED` | YES |
| `PUBLIC_5XX` | 0 |
| `PUBLIC_URL_LEAKS` (redirect/HTML origin audit) | 0 |
| Bundle substring grep (reject validators only) | non-origin; established gate uses origin audit |
| `SECRET_EXPOSURE` | 0 |
| `COMMERCIAL_SIDE_EFFECTS` | 0 |
| `INTEGRATIONS_FATAL_RECURRENCE` (`SupplierConnectionStatus::Disabled`) | 0 (hub service healthy post-activate) |

Evidence: `tmp/owner-v3-postdeploy-remediation/deploy-output.txt`, `tmp/v3-url-leak-audit.json`, `tmp/owner-v3-postdeploy-remediation/log-audit-output.txt`

### Owner manual UAT still required

Checkout title/gender/null hydration, exact PKR presentation, Review payment column, CMS Homepage/Pages live UI, and full Integrations browser matrix remain **owner manual UAT** under `OWNER_RETEST_V3=RETEST_REQUIRED`. Predeploy Playwright harness evidence at test SHA `93452d9b` supports expected behavior; production browser UAT not fully automated in this deploy pass.

## Rollback

1. Restore backup `owner-v3-postdeploy-20260823T083313Z` (app + DB + public_html).
2. Re-activate runtime `26ff103287437b995074847a74be1cd227404594`.
3. Rebuild public frontend to rollback build `cwoD2Mw7-UfruNTmM3h3p`.
4. Rebuild dashboard and restart PM2 processes as `pkjetp`.

Helper: `tmp/jetpk-owner-v3-postdeploy-remediation-rollback.sh`

## Next

Owner configures AbhiPay through **Admin → Integrations**, then performs final Owner V3 manual UAT on production.

**Do not mark OWNER_RETEST_V3 PASS.**
