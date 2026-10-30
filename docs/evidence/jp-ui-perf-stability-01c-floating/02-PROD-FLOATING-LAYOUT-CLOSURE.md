# JP-UI-PERF-STABILITY-01C — Production floating layout closure

## Release identity

| Item | Value |
|---|---|
| PR | 144 |
| Canonical main | `0a39e0a324ddbb091008361087f038618274de79` |
| PR release guard | 376 PASS |
| Main release guard | 377 PASS |
| Frontend RC | `4697b228e3e2c19c788cd0996a8d621a46b381d6` |
| Frontend RC parent | `1dece982b1a8d07fb13426e627ed68e752a5c42b` |
| Laravel application (unchanged) | `4e6e3acc2c48d431c4fb622eb9c462cd0588e05c` |

**Not deployed:** full main `0a39e0a3…`

## Predeploy

```text
PREDEPLOY_APPLICATION_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
PREDEPLOY_FRONTEND_SHA=1dece982b1a8d07fb13426e627ed68e752a5c42b
LIVE_PREIMAGE_PARITY=6_OF_6_PASS
DISK_SPACE_GUARD=PASS
RUNTIME_OWNERSHIP_PREFLIGHT=PASS
STAGE_DRY_RUN uploadable=6 deletion=0
```

## Backup / rollback

```text
BACKUP_PATH=/home/pkjetp/backups/jp-ui-perf-01c-floating-20261030T062658Z
ROLLBACK_BUILD_PATH=/home/pkjetp/jetpk_app/frontend/.next.bak-01c-20261030T062658Z
OLD_BUILD_ID=ITH5uzTuo-a7Gi6Il1jAN
NEW_BUILD_ID=RDupyPzXOcemU2XTemQLQ
```

## Activation

Isolated stage build at `/tmp/jp-01c-app-20261030T062658Z` with `FRONTEND_SKIP_RESTART=1`.

Six authorized sources installed; Laravel `deploy-sha.txt` never rewritten.

```text
FRONTEND_SOURCE_FILES_ACTIVATED=6
LIVE_SOURCE_BLOB_PARITY=6_OF_6_PASS
APPLICATION_MARKER_PRESERVED_DURING_STAGE=YES
BUILD_BEFORE_RESTART_GUARD=PASS
PUBLIC_FRONTEND_RESTART=PASS
DASHBOARD_RESTARTED=NO
```

## Final provenance

```text
FINAL_APPLICATION_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
FINAL_PUBLIC_FRONTEND_SHA=4697b228e3e2c19c788cd0996a8d621a46b381d6
FINAL_BUILD_SOURCE_SHA=4697b228e3e2c19c788cd0996a8d621a46b381d6
```

## Live floating geometry

Source: `02-live-verify.json`

| Viewport | Flights dock bottom | Flights Ask bottom | Intersection | Clears ≥14px |
|---|---|---|---|---|
| 320–1023 | ≈108 | ≈178 | 0 | YES |
| 1024 / 1440 | dock hidden | ≈18 standalone | 0 | N/A |

Dock open → Ask FAB hidden. Ask open → dock hidden. Escape returns focus to dock trigger.

Horizontal overflow: `scrollWidth - clientWidth = 0` on `/`, `/login`, `/flights/results` for all tested widths.

## HTTP / health

```text
HOME_HTTP=200
LOGIN_HTTP=200
FLIGHTS_RESULTS_HTTP=200
PUBLIC_FRONTEND_STATUS=online
DASHBOARD_STATUS=online
NEW_NEXT_FATAL_ERRORS=0 (empty recent error tail)
```

## Phase A / B

Phase A derivatives YES; giant original NO.  
Phase B SSR logos correct on home/login/flights; no post-hydration logo swap; config fetch on login = 0.

## Commercial safety

```text
AIRBLUE_OR_SUPPLIER_PROVIDER_CALLS=0
BOOKING_MUTATIONS=0
PAYMENT_MUTATIONS=0
DATABASE_MUTATIONS=0
MEDIA_BACKFILL_RERUN=NO
FLIGHT_CARD_LAYOUT_CHANGED=NO
```

## Status

```text
FINAL_STATUS=JP_UI_PERF_STABILITY_01C_FLOATING_LAYOUT_CLOSED
```
