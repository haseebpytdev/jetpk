# JP-AIRBLUE-ZAPWAYS-08 — START GATE

**Date (UTC):** 2026-10-09  
**Branch:** `work/jp-airblue-zapways-08-test-v2-cert`  
**Base `jetpk/main` SHA:** `cfd0fd0dcb96c4f0f209040feb2f42c1d6718dfb`

## Git gate

| Check | Result |
|---|---|
| `git fetch jetpk` | OK |
| `git rev-parse jetpk/main` | `cfd0fd0dcb96c4f0f209040feb2f42c1d6718dfb` (matches required protected main) |
| Working branch | `work/jp-airblue-zapways-08-test-v2-cert` (from `jetpk/main`) |

## Existing implementation (inspection only)

| Area | Location |
|---|---|
| Zapways OTA client (SOAP HTTP + mTLS) | `app/Services/Suppliers/AirBlue/AirBlueClient.php` |
| Config / v2 endpoint / TLS paths | `app/Services/Suppliers/AirBlue/AirBlueConfigResolver.php`, `config/suppliers.php` |
| SOAP envelope + AirLowFareSearch | `app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php` |
| Search orchestration | `app/Services/Suppliers/AirBlue/AirBlueFlightSearchService.php` |
| Response parse/normalize | `app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php`, `AirBlueOtaResponseNormalizer.php` |
| Connection store + Zapways normalizer | `app/Support/Suppliers/AirBlueSupplierConnectionNormalizer.php`, admin supplier connection APIs |
| Dashboard minimal fields | `dashboard/features/api-connections/lib/airblue-zapways-contract.ts`, `add-api-connection-modal.tsx` |
| Config validation (not connectivity) | `SupplierConnectionService::testConnection()` |
| Health (config presence) | `AirBlueDiagnosticService`, `airblue:health` |
| v2/v3 selection | `AirBlueZapwaysProtocolVersion`, credentials `protocol_version` (default **2.0**; v3 gated) |
| Certification search policy | `AirBlueConnectionSearchPolicy.php` |
| Tests | `tests/Unit/Services/Suppliers/AirBlue/*`, `tests/Feature/Admin/AirBlueZapwaysConnectionStoreTest.php` |

No architecture redesign performed in this loop.
