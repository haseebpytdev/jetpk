# JetPakistan Wave-9 + JP-INT-01 Integration Hub Deployment

**Date:** 2026-08-22  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`  
**Deployment status:** `DEPLOYED` (protected scripts; live restrained verification complete)

## Prior / final SHAs

| Role | SHA |
|---|---|
| Prior production runtime | `8cf657d7d35cc97848318f56184825ac49af6225` |
| Wave-9 engineering SHA | `67417d225fcd70e8e8cb1a1b535ec0ed8eee0877` |
| JP-INT-01 / combined deployed runtime | `26ff103287437b995074847a74be1cd227404594` |
| Pre-deploy docs SHA | `57fbafae5209543861fa4e54c7f3a07a12827f3d` |
| Final docs SHA | _(this production-evidence commit)_ |

## Scope activated (exact Git object)

`AUTHORIZED_SHA=26ff103287437b995074847a74be1cd227404594`  
`BASE_SHA=8cf657d7d35cc97848318f56184825ac49af6225`  
**Deploy runtime file count: 51** (tests/docs/tmp excluded per gate rules).

> Manifest reconciliation: `docs/phases/JP-INT-01-RUNTIME-MANIFEST.md` recorded **53** because it included two `frontend/tests/*.spec.ts` paths. Production staging excluded all test paths (`tests/**`, `frontend/tests/**`, `dashboard/tests/**`) → **51** runtime files. `UNEXPECTED_RUNTIME_SUBSYSTEMS=NONE`.

### Runtime areas

- Wave-9 Review/payment public frontend
- Integration registry + management services
- Integration authorization/RBAC
- Integration health model + migrations
- AbhiPay management/diagnostic services
- Dashboard Integrations UI + nav/API/access-control
- Laravel admin routes/controllers
- Payment gateway shared runtime

Full path list: `tmp/_jp_int01_manifest_filtered.txt` (generated at staging).

## Protected production deployment (executed)

### Predeploy checkpoint

| Gate | Result |
|---|---|
| SSH_AUTH | PASS |
| HOMEPAGE / LOGIN / ABOUT / FAQ | PASS |
| Public PM2 `jetpk-public-frontend` | online (`pkjetp`) |
| Dashboard PM2 `jetpk-dashboard` | online |
| Old public build | `H5Lgd0EQ6sVIiknlFwJh2` |
| OLS SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` (PASS) |
| `OTA_CLIENT_REQUIRE_LOGIN_OTP` | `false` preserved |
| OTP demo keys | `4` present |
| Sabre cancel safety keys | SET |
| Pre-deploy `MIGRATIONS_PENDING` | `0` (new migration files not yet on server) |
| `NODE_MODULES_NON_PKJETP` / `NEXT_NON_PKJETP` | `0` / `0` |

### Backup

| Field | Value |
|---|---|
| `BACKUP` | PASS |
| `BACKUP_ID` | `wave9-20260822T191404Z` |
| `BACKUP_INTEGRITY` | PASS |
| Rollback runtime | `8cf657d7d35cc97848318f56184825ac49af6225` |
| Rollback public build | `H5Lgd0EQ6sVIiknlFwJh2` |

### Staging / activation

| Field | Value |
|---|---|
| Release | `/home/pkjetp/releases/jetpk-wave9-jp-int01-20260822T191243Z` |
| `STAGED_SOURCE_PARITY` | PASS |
| `STAGED_RUNTIME_FILES` | 51 |
| Build user | `pkjetp` |
| Public `npm ci` / build | PASS |
| Dashboard `npm ci` / build | PASS |
| Old public build | `H5Lgd0EQ6sVIiknlFwJh2` |
| New public build | `cwoD2Mw7-UfruNTmM3h3p` |
| Public PM2 | online (`pkjetp`) |
| Dashboard PM2 | online (`pkjetp`) |
| `OLS_HASH` | PASS |
| `OWNERSHIP_DRIFT` | 0 |
| `LIVE_SOURCE_DRIFT` | 0 |
| Post-activate cache clear | PASS |
| `ROLLBACK_USED` | NO |

### Database migrations

| Migration | Result |
|---|---|
| `2026_08_22_220000_create_integration_health_checks_table` | APPLIED |
| `2026_08_22_220100_add_purpose_to_payment_transactions_table` | APPLIED |
| `MIGRATIONS_APPLIED` | 2 |
| `MIGRATIONS_PENDING` | 0 |
| `integration_health_checks` table | YES |
| `payment_transactions.purpose` column | YES |

## Live restrained verification

Host: `https://jetpakistan.pk` only. No AbhiPay credentials configured. No commercial booking/payment actions.

| Gate | Result |
|---|---|
| Integration admin routes registered | PASS (10 routes) |
| `IntegrationRegistry` provider count | 13 |
| Dashboard `/admin/dashboard/integrations` route in build | PASS |
| Unauthenticated hub access | access-denied (RBAC enforced) |
| Legacy `admin/settings/payments` route | PASS (exists; auth-gated redirect) |
| AbhiPay configured | NO (expected) |
| AbhiPay checkout available | NO (expected; Pay by Card hidden until Owner config) |
| Public smoke `/` `/login` `/about` `/faq` | PASS |
| `PUBLIC_URL_LEAKS` | 0 |
| `SECRET_PUBLIC_EXPOSURE` | 0 |
| `COMMERCIAL_SIDE_EFFECTS` | 0 |

## Rollback

1. Restore backup `wave9-20260822T191404Z` (app + DB + public_html).
2. Re-activate runtime `8cf657d7d35cc97848318f56184825ac49af6225`.
3. Rebuild public frontend to rollback build `H5Lgd0EQ6sVIiknlFwJh2`.
4. Restart PM2 processes as `pkjetp`.

## Next

Owner configures AbhiPay securely through **Admin → Integrations**, runs Test Connection in Dashboard, then performs final Wave-9 / JP-INT-01 Owner UAT.

**Do not mark OWNER_RETEST_V3 PASS.**
