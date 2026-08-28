# JP-UX-PORTAL-PERF-01 deployment report

## Summary

| Field | Value |
|-------|-------|
| AUTHORIZED_ENGINEERING_SHA | `9f5b70f45228ae333495afd0d941467676fd488f` |
| PREVIOUS_RUNTIME_SHA | `9979330c35141bc85cd5db7941f4a9c274e89a52` |
| NEW_RUNTIME_SHA | `9f5b70f45228ae333495afd0d941467676fd488f` |
| BACKUP_ID | `jp-ux-portal-perf-01-20260828T110332Z` |
| PUBLIC_BUILD_ID_OLD | `e4VIZqNpoHKKio-vCpzSp` |
| PUBLIC_BUILD_ID_NEW | `Nr53B3pSZ0L2kKgascNwz` |
| MIGRATION_COUNT | `0` |
| ACTIVATE_STATUS | `PASS` |
| LIVE_SOURCE_DRIFT | `0` |
| OLS_HASH_STATUS | `PASS` (`612aa838…2c4c`) |
| OWNERSHIP_STATUS | `pkjetp` / `FRONTEND_NON_PKJETP=0` |
| PM2_STATUS | public `online`, dashboard `online` (dashboard PID unchanged) |

## Commercial gates

| Gate | Value |
|------|-------|
| ALHAIDER_BOOKING_ENABLED | `false` |
| ALHAIDER_CANCEL_ENABLED | `UNSET` |
| NEW_ALHAIDER_CREATE_CALLS | `0` |
| SABRE_PNR_CREATED | `NO` |
| PAYMENT_EXECUTED | `NO` |
| TICKET_ISSUED | `NO` |

## Rollback

Rollback package prepared under `/home/pkjetp/releases/jetpk-rollback-jp-ux-portal-perf-01-20260828T110332Z-jp-ux-portal-perf-01` with prior public build `e4VIZqNpoHKKio-vCpzSp` and runtime SHA `9979330c…`.

## Manifest

Exact 22 runtime paths from `tmp/jp-ux-portal-perf-01/runtime-manifest.txt` staged from Git objects at `9f5b70f4`.
