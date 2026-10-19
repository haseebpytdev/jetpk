# JP-AIRBLUE-ZAPWAYS-PROD-DEPLOY-01-AFTER-MERGE

**Date (UTC):** 2026-10-19  
**Task:** Production deploy of squash-merged PR #104 (AirBlue Zapways offline finalization)  
**PR:** #104 (squash-merged)  
**Reviewed head:** `1d0455fab8150e51e296fc0276321d72b34cebdf`  
**Protected main:** `ff148b1f75d4ac80b9a45fcddd70c2717b102f75`  
**Release guards:** PASS (`39248505003`)

## Status

```text
OPS_DEF_0028_STATUS=READY_AWAITING_LIVE_CERTIFICATION
PRODUCTION_MUTATED=YES (scoped runtime files + dashboard rebuild only)
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
```

## Squash-merge / blob parity

| Check | Result |
|-------|--------|
| `PR_104_MERGED` | YES |
| Ancestry of reviewed HEAD on main | NOT REQUIRED (squash merge) |
| `REVIEWED_RUNTIME_BLOB_PARITY` (20 runtime paths) | **PASS** (`BLOB_FAIL_COUNT=0`) |
| `DATABASE_MIGRATIONS` in PR #104 scope | **0** |
| `MIGRATION_RUN` | **NO** |

## Pre-deploy baseline

| Field | Value |
|-------|--------|
| `CANONICAL_APP_ROOT` | `/home/pkjetp/jetpk_app` |
| `HTTP_UP_PRE` | 200 |
| `HTTP_HOME_PRE` | 503 (PM2 public frontend was down pre-deploy; restored without public rebuild) |
| `HTTP_LOGIN_PRE` | 503 |
| `HTTP_LOOKUP_PRE` | 503 |
| `HTTP_AGENT_PRE` | 503 |
| `HTTP_ADMIN_PRE` | 503 |
| `PUBLIC_BUILD_ID_PRE` | `HNDspC9CoB4Bat_x0DwZ6` |
| `DASHBOARD_BUILD_ID_PRE` | `7-iZf4RbaJmGpuDjO1tsh` |
| `FAILED_JOBS_PRE` | 8 (not retried/deleted) |
| `PENDING_DEFAULT_PRE` | 0 |
| `AIRBLUE_EXTERNAL_CALLS_ENV_PRESENT` | NO |
| `AIRBLUE_EXTERNAL_CALLS_ENV_IS_TRUE` | NO |
| `AIRBLUE_CLI_LIVE_ENABLED` | absent |
| `AIRBLUE_OTA_V3_LIVE_ENABLED` | absent |
| `CONFIG_CACHE_ACTIVE` | NO |
| `ROUTE_CACHE_ACTIVE` | NO |
| `OPCACHE_ENABLED` | YES |
| `OPCACHE_VALIDATE_TIMESTAMPS` | 1 |
| `PREVIOUS_PRODUCTION_PATCH_MARKER` | `922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2…+ops-def-0011-legacy-telemetry@9c5a0e8a…` |

## Deploy scope (from PR #104 vs `19b7607e`)

### LARAVEL_RUNTIME (18)

- `app/Console/Commands/AirBlueCancelCommitCommand.php`
- `app/Console/Commands/AirBlueRetrieveCommand.php`
- `app/Console/Commands/AirBlueTestBookingCommand.php`
- `app/Console/Commands/AirBlueTestSearchCommand.php`
- `app/Console/Commands/AirBlueTestTicketingCommand.php`
- `app/Console/Commands/Concerns/GuardsAirBlueLiveNetwork.php` (new on prod)
- `app/Services/FlightSearch/FlightSearchService.php`
- `app/Services/Suppliers/AirBlue/AirBlueBookingService.php`
- `app/Services/Suppliers/AirBlue/AirBlueClient.php`
- `app/Services/Suppliers/AirBlue/AirBlueOtaResponseNormalizer.php`
- `app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php`
- `app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php`
- `app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php`
- `app/Services/Suppliers/AirBlue/AirBlueRetrieveService.php`
- `app/Services/Suppliers/AirBlue/AirBlueTicketingService.php`
- `app/Services/Suppliers/AirBlue/AirBlueXmlParser.php`
- `app/Services/Suppliers/SupplierBookingService.php`
- `app/Support/Bookings/SupplierBookingPreflightGuard.php`

### CONFIG_RUNTIME (1)

- `config/suppliers.php` (`external_calls_enabled` default false)

### DASHBOARD_RUNTIME (1 + rebuild)

- `dashboard/features/api-connections/lib/airblue-zapways-contract.ts`
- Dashboard `npm ci` + `npm run build` + PM2 restart `jetpk-dashboard`

### Not deployed

- `tests/`, `docs/evidence/`, fixtures, phpunit artifacts, public frontend source rebuild, `.env`, `vendor`, `node_modules`, credentials, certification flags

## Backup / rollback

| Field | Value |
|-------|--------|
| `BACKUP_PATH` | `/home/pkjetp/backups/airblue-zapways-finalization-pre-deploy-20261019T124320Z` |
| `ROLLBACK_READY` | YES |
| `DASHBOARD_ROLLBACK_READY` | YES (pre `.next` tarball + BUILD_ID preserved) |

Note: `config/` and `app/Support/Bookings/` were root-owned directories; remaining three files finished via root with `install -o pkjetp -g pkjetp`, then directory ownership normalized to `pkjetp:pkjetp`.

## Post-deploy gates

| Gate | Result |
|------|--------|
| `UNEXPECTED_RUNTIME_FILES_DEPLOYED` | 0 |
| `PHP_CONFIG_RUNTIME_PARITY` | PASS (20/20 SHA256) |
| `PHP_SYNTAX_FAIL` | 0 |
| `LARAVEL_BOOTSTRAP` | PASS |
| `AIRBLUE_EXTERNAL_CALLS_ENV_PRESENT` | NO |
| `AIRBLUE_EXTERNAL_CALLS_ENV_VALUE_SAFE` | YES |
| `AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED` | NO |
| `AIRBLUE_CLIENT_GATE_PRESENT` | YES |
| `AIRBLUE_CLIENT_GATE_BEFORE_HTTP` | YES |
| `DASHBOARD_BUILD` | PASS |
| `PUBLIC_BUILD_ID_POST` | `HNDspC9CoB4Bat_x0DwZ6` (= PRE) |
| `DASHBOARD_BUILD_ID_POST` | `3z0ZMmB40wEnJ1kRq0f1M` |
| `HTTP_UP_POST` | 200 |
| `HTTP_HOME_POST` | 200 |
| `HTTP_LOGIN_POST` | 200 |
| `HTTP_LOOKUP_POST` | 200 |
| `HTTP_AGENT_POST` | 200 |
| `HTTP_ADMIN_POST` | 200 |

## Production AirBlue config (read-only; no credential values)

| Field | Value |
|-------|--------|
| `AIRBLUE_CONNECTION_PRESENT` | YES |
| `AIRBLUE_CONNECTION_ENVIRONMENT` | sandbox |
| `AIRBLUE_CONNECTION_ACTIVE` | true |
| `AIRBLUE_CERTIFICATION_STATUS` | pending |
| `AIRBLUE_SEARCH_CERTIFIED` | null (absent → policy uses certification_status) |
| `AIRBLUE_PROTOCOL_VERSION` | 2.0 |
| `AIRBLUE_BOOKING_FEATURE_ENABLED` | false |
| `AIRBLUE_TICKETING_FEATURE_ENABLED` | false |
| `AIRBLUE_CANCEL_FEATURE_ENABLED` | false |
| `PENDING_AIRBLUE_VISIBLE_TO_PUBLIC_SEARCH` | NO (`AirBlueConnectionSearchPolicy::isEligibleForPublicSearch`) |
| `AIRBLUE_PUBLIC_SEARCH_PROVIDER_CALLS` | 0 |

Credentials / certification / ticketing / cancel settings were **not modified**.

## Booking / uncertain / ticketing safety (deployed source)

| Gate | Result |
|------|--------|
| `AIRBLUE_SINGLE_PERSISTENCE_OWNER` | SupplierBookingService |
| `AIRBLUE_BOOKING_CAN_REACH_HTTP_WITH_GATE_OFF` | NO |
| `AIRBLUE_AUTO_RETRY_UNKNOWN_OUTCOME` | NO |
| `AIRBLUE_UNCERTAIN_STATE_HANDLER_PRESENT` | YES |
| `AIRBLUE_NOTIFICATION_POST_COMMIT` | YES (`DB::afterCommit`) |
| `AIRBLUE_NOTIFICATION_FAILURE_CAN_ROLLBACK_UNCERTAIN_STATE` | NO |
| `AIRBLUE_CONCURRENT_BOOKING_GUARD` | PASS |
| `AIRBLUE_RETRY_AFTER_UNCERTAIN` | DENY |
| `AIRBLUE_TICKETING_CURRENTLY_LIVE_ENABLED` | NO |
| `AIRBLUE_AUTO_TICKETING` | NO |
| `AIRBLUE_TICKETING_CAN_REACH_HTTP` | NO |
| `AIRBLUE_CANCEL_CURRENTLY_LIVE_ENABLED` | NO |
| `AIRBLUE_CANCEL_CAN_REACH_HTTP` | NO |

## Zero mutation certification

```text
PRODUCTION_BOOKINGS_MUTATED=0
PRODUCTION_SUPPLIER_BOOKINGS_CREATED=0
PRODUCTION_SUPPLIER_ATTEMPTS_CREATED=0
PAYMENT_MUTATIONS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
AIRBLUE_LIVE_AUTH_CALLS=0
AIRBLUE_LIVE_SEARCH_CALLS=0
AIRBLUE_LIVE_OFFER_PRICE_CALLS=0
AIRBLUE_LIVE_BOOKING_CALLS=0
AIRBLUE_LIVE_RETRIEVE_CALLS=0
AIRBLUE_LIVE_CANCEL_CALLS=0
AIRBLUE_LIVE_TICKETING_CALLS=0
AIRBLUE_LIVE_VOID_CALLS=0
SABRE_PNR_CREATE_CALLS=0
OTHER_SUPPLIER_MUTATIONS=0
LIVE_PAYMENT_CALLS=0
```

No AirBlue live-capable Artisan commands were run. No Zapways contact.

## Patch marker

| Field | Value |
|-------|--------|
| `PRODUCTION_RUNTIME_BASE_SHA` | `922f15774ece177d8b3cc23207e98379b8a2755b` (scoped overlays; not a full-tree checkout of main) |
| `NEW_PRODUCTION_PATCH_MARKER` | `922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2…+ops-def-0011-legacy-telemetry@9c5a0e8a…+airblue-zapways-finalization@ff148b1f…` |

## Readiness

```text
OPS_DEF_0028_STATUS=READY_AWAITING_LIVE_CERTIFICATION
AIRBLUE_CODE_READINESS=PASS
AIRBLUE_OFFLINE_CONTRACT_READINESS=PASS
AIRBLUE_UI_READINESS=PASS
AIRBLUE_BOOKING_LOGIC_READINESS=PASS
AIRBLUE_SECURITY_READINESS=PASS
AIRBLUE_PRODUCTION_CONFIG_READINESS=PASS_WITH_EXTERNAL_CALLS_DISABLED
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_OVERALL_READINESS=READY_AWAITING_LIVE_CERTIFICATION
REMAINING_PROVIDER_SPEC_GAPS=infant_wire_association_attribute;fare_rules_payload;void_ticket_contract
```

## Notes

1. Pre-deploy edge 503s were caused by absent PM2 Next processes (not Laravel). Public was restored from the existing `.next` without rebuild so `PUBLIC_BUILD_ID` stayed constant.
2. Do **not** reuse branch `ops/airblue-zapways-prod-deploy` @ `b108b39b` (stopped/no-deploy evidence). This closure uses `ops/airblue-zapways-prod-deploy-closure` from `ff148b1f`.
