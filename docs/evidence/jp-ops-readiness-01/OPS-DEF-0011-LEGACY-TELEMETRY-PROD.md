# OPS-DEF-0011 — Legacy telemetry production deploy (observation active)

**Date (UTC):** 2026-10-17  
**Task:** `JP-OPS-READINESS-01-OPS-DEF-0011-LEGACY-TELEMETRY-PROD`  
**PR:** #102 (squash-merged to protected main)  
**Reviewed head:** `8dd4dae5a25f36e049ebe98814b3623e22bc4157`  
**Protected main (post-merge):** `9c5a0e8ac974883033cffa81aca9581f3c768939`  
**Release guards:** PASS (`39029269965`)

## Status (not closed)

```text
OPS_DEF_0011_STATUS=SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE
SUNSET_OBSERVATION_ACTIVE=YES
```

Phase-2 hard remove remains blocked until an approved observation window shows zero `outcome=success` (or owner waiver).

## Merge / blob parity proof

| Check | Result |
|-------|--------|
| `PR_102_MERGED` | YES |
| `git merge-base --is-ancestor 8dd4dae5… jetpk/main` | FAIL (expected for squash merge) |
| `REVIEWED_RUNTIME_BLOB_PARITY` vs `jetpk/main` (4 files) | **PASS** |
| Release guards | PASS (`39029269965`) |

Git blob IDs (reviewed == `jetpk/main`):

| Path | Blob |
|------|------|
| `app/Http/Middleware/LogLegacyGuestTokenUsage.php` | `1368302bc0d1247f04694e19866a5367840aeea7` |
| `app/Support/GuestBooking/LegacyGuestTokenUsageTelemetry.php` | `b0f87457ad7e3246c4186160cb85a8966bf69c15` |
| `bootstrap/app.php` | `0ad1919e61c006e0b499d5594ac969cce4a6807b` |
| `routes/web.php` | `1ceab92e45b959d4908c171dd72f5d1f2ce23314` |

## Production base (unchanged full-tree SHA)

| Field | Value |
|-------|--------|
| `PRODUCTION_RUNTIME_BASE_SHA` | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| `CANONICAL_APP_ROOT` | `/home/pkjetp/jetpk_app` |
| `BACKUP_PATH` | `/home/pkjetp/backups/ops-def-0011-legacy-telemetry-pre-deploy-20261017T120725Z` |
| `NEW_PRODUCTION_PATCH_MARKER` | `922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2…+ops-def-0011-legacy-telemetry@9c5a0e8a…` |
| `PUBLIC_BUILD_ID` (pre/post) | `HNDspC9CoB4Bat_x0DwZ6` (unchanged) |
| `DASHBOARD_BUILD_ID` (pre/post) | `7-iZf4RbaJmGpuDjO1tsh` (unchanged) |
| Frontend rebuild | NO |

## Runtime files deployed (exactly 4)

1. `app/Http/Middleware/LogLegacyGuestTokenUsage.php` — SHA256 `ee92eb343a7ff44a572da0af4c17c4c969ff01baab6574658023b8f94c838ac7`
2. `app/Support/GuestBooking/LegacyGuestTokenUsageTelemetry.php` — SHA256 `f006b15ad3fd151388cb9e4584dea0bfa852e3ba0a5fd8307ac594bedad3b4f5`
3. `bootstrap/app.php` — SHA256 `d734b9c908a09321011be5de2306968fde8ab8d9ecc1160699bef3c3a0c29750`
4. `routes/web.php` — SHA256 `21acb375366362b08c6b08fd024c7fdf9ba7bf763e5377af4299fa0a1a17bc36`

Not deployed: tests, evidence, frontend, dashboard, migrations, `.env`, `vendor`, `node_modules`.

| Gate | Result |
|------|--------|
| `PHP_RUNTIME_PARITY` | PASS (4/4) |
| `UNEXPECTED_RUNTIME_FILES_DEPLOYED` | 0 |
| `PHP_SYNTAX_FAIL` | 0 |
| `LARAVEL_BOOTSTRAP` | PASS |
| `MIDDLEWARE_ALIAS_RESOLVES` | YES → `App\Http\Middleware\LogLegacyGuestTokenUsage` (Http Kernel `middlewareAliases`) |

## Route / privacy / flood gates

| Check | Result |
|-------|--------|
| Legacy exchange middleware | YES |
| Legacy payment-proof middleware | YES |
| Legacy promo apply/remove middleware | YES |
| Legacy AbhiPay start middleware | YES |
| Legacy cancellation middleware | YES |
| `CLEAN_GUEST_TELEMETRY_ATTACHED` | NO |
| `TELEMETRY_FIELDS` | `event,route_category,method,outcome` |
| Accepts Request / token / booking_id / URI | NO / NO / NO / NO |
| `TELEMETRY_RATE_LIMIT` | 30 per category per UTC minute |
| `FLOOD_GATE_KEY_PRIVACY` | PASS (`guest_legacy_token_telemetry:`) |

## Access log (OPS-RISK-0017 unchanged)

```text
logFormat "%h %l %u %t \"%m\" %>s %b %T \"%{User-Agent}i\""
ACCESS_LOG_RAW_REQUEST_TARGET=NO
ACCESS_LOG_QUERY_STRING=NO
ACCESS_LOG_GUEST_TOKEN=NO
```

## Post-deploy health

| Probe | Result |
|-------|--------|
| HTTP `/up` | 200 |
| HTTP `/` | 200 |
| HTTP `/login` | 200 |
| HTTP `/lookup-booking` | 200 |
| HTTP `/agent` | 200 |
| HTTP `/admin` | 302 |

```text
LEGACY_TELEMETRY_MEASUREMENT_START=20261017T120725Z
INITIAL_SUCCESSFUL_LEGACY_USAGE_COUNT=0
SAFE_DENIAL_PROBE=SKIPPED
```

## Mutations (policy)

```text
SABRE_PNR_CREATE_CALLS=0
AIRBLUE_LIVE_BOOKING_CALLS=0
SUPPLIER_BOOKING_CALLS=0
TICKETING_CALLS=0
LIVE_PAYMENT_CALLS=0
GUEST_TOKEN_CREATE_OR_ROTATE=0
PROMO_APPLY_REMOVE=0
CANCELLATION_SUBMIT=0
ABHIPAY_ACTIVATE_OR_CALL=0
DB_MUTATIONS=0
```

## Rollback

Restore the four files from:

`/home/pkjetp/backups/ops-def-0011-legacy-telemetry-pre-deploy-20261017T120725Z`

Then clear route/config caches if active and re-verify health.

## Next

```text
NEXT_MAJOR_WORKSTREAM=AIRBLUE_ZAPWAYS_FINALIZATION_NON_LIVE
```

Observe `guest.legacy_token.usage` with `outcome=success` before any Phase-2 hard remove of legacy routes.
