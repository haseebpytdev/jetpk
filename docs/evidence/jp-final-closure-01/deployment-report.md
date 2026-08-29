# JP-FINAL-CLOSURE-01-R4 — Deployment report

## Deployed object

- AUTHORIZED_SHA=`6e3ea4e69bbd2d463aaabfe2f53d93388e29b3f9`
- MANIFEST_COUNT=`16` (`tmp/jp-final-closure-01-r4/runtime-manifest-r4.txt`)
- BACKUP_ID=`jp-final-closure-01-r2-20260829T191040Z`
- EXPECTED_OLD_BUILD=`4KN41ZZvPsqgb3xu8D7Ju`
- NEW_PUBLIC_BUILD_ID=`OUwL6VdIoWW07Xli8W_KB`
- RELEASE=`/home/pkjetp/releases/jetpk-jp-final-closure-01-r4-20260829T190500Z`

## Gates (from `tmp/jp-final-closure-01-r4/deploy.out`)

| Gate | Result |
|---|---|
| BACKUP | PASS |
| PHP_SYNTAX | PASS |
| LARAVEL_BOOT | PASS |
| MIGRATIONS | 0 |
| PUBLIC_BUILD (pkjetp, PUBLIC_ONLY=1) | PASS |
| PRE_PROXY_GATE | PASS |
| OLS_HASH (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| FULL_GIT_OBJECT_PARITY | PASS |
| PUBLIC_PM2 | online |
| DASHBOARD_PM2 | online |
| SMOKE `/` `/groups` `/login` `/verify-email` | 200 |
| **ACTIVATE** | **PASS** |

## Scope copied

Laravel email/communication + frontend flight-results / flight-details / standard-booking traveler timing. Dashboard rebuild skipped (unchanged).

## Safety

- No supplier booking/PNR/payment/ticket mutation in deploy path
- ALHAIDER_BOOKING_ENABLED remains false (preserved)
- LIVE_SUPPLIER_SYNTHETIC_PASSENGER_DATA=0 (commercial-safety evidence)

## Rollback

Restore staged paths from backup `jp-final-closure-01-r2-20260829T191040Z` via protected rollback helper; rebuild public Next if frontend rolled back.
