# Deployment report — JP-UX-PORTAL-PERF-01-R3

## Final activate

| Field | Value |
|-------|-------|
| AUTHORIZED_SHA | `61362c21907b4e69ac7f399d38943dca2aa2aef4` |
| PREVIOUS_RUNTIME_SHA | `d71e065b861657697dc5a58d0d7dc4702f71d373` |
| NEW_RUNTIME_SHA | `61362c21907b4e69ac7f399d38943dca2aa2aef4` |
| BACKUP_ID | `jp-ux-portal-perf-01-20260828T183110Z` |
| TIMESTAMP | `20260828T182825Z` |
| EXPECTED_MANIFEST_COUNT | 11 |
| PUBLIC_BUILD_ID | `lhTb3ywP3iwYsjR3lQqZn` |
| LIVE_SOURCE_DRIFT | 0 |
| OLS_HASH | PASS (expected `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| ACTIVATE | PASS |
| PUBLIC_PM2 | online (pkjetp) |
| OWNERSHIP | preserved via protected scripts |
| Migrations run | 0 |

## Commercial gates / side effects

| Gate | Value |
|------|-------|
| ALHAIDER_BOOKING_ENABLED | false |
| NEW_ALHAIDER_CREATE_CALLS | 0 |
| NEW_ALHAIDER_API_CANCEL_CALLS | 0 |
| ALHAIDER_TOKEN_GENERATION_CALLS | 0 |
| SABRE_PNR_CREATED | NO |
| PIA_ORDER_CREATED | NO |
| IATI_BOOKING_CREATED | NO |
| ONEAPI_BOOKING_CREATED | NO |
| PAYMENT_EXECUTED | NO |
| TICKET_ISSUED | NO |
| LIVE_SUPPLIER_SYNTHETIC_PASSENGER_DATA | 0 |
| SUPPLIER_MUTATION_CALLS | 0 |

Protected scripts only. No recursive chown, chmod 777, git clean, or reset --hard.
