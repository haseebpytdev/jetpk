# One API — document anomaly ledger

Phase: ONE-API-FLYJINNAH-AIRARABIA-FULL-SUPPLIER-INTEGRATION-1

This ledger records supplier-document issues and how the JetPakistan implementation accounts for them.

| Anomaly | Source | Implementation handling |
|---------|--------|-------------------------|
| Smart/curly quote in JSON airport code | `ONE_API_BUNDLE.docx` search sample | Request builders use validated IATA codes only; no curly quotes in emitted JSON |
| Stray `s` after metadata opening brace | `Correct_Search_Requests.odt` return sample (`"metaData": {s`) | Correct structure in `OneApiSearchRequestBuilder`; not copied from malformed sample |
| Return examples reuse departure date for inbound leg | Multiple booking-flow docx | Return `searchOnds[1]` uses actual return date from OTA search request |
| Malformed Password XML tag in payment-modification sample | `Modification_For_Payment.txt` | `OneApiSoapSecurityBuilder` emits valid `wsse:Password` PasswordText elements only |
| Old dates, PNRs, transaction IDs, currencies in examples | All samples | Fixtures use `PNR_FIXTURE_*`, `TID_FIXTURE_*`, deterministic dates; never sample runtime values |
| Currency varies (AED, INR, EGP, EUR, AMD) | EquiBaseFare, bundle, hold-pay samples | `OneApiMoney` preserves native/equiv/pay currencies separately; settlement from latest price RS |
| Response XML splits segments into multiple `OriginDestinationOption` inconsistently | Connection price RS vs RQ | O&D grouping preserved from **search offer** / signed token, not inferred from price RS layout |
| Wire-contract misspellings | Vendor XML/JSON | Preserved exactly when required: `bunldedServiceId`, `OutBoundBunldedServiceId`, `InBoundBunldedServiceId`, `includedServies`, `LoadAirItinery`, `LoadFullFilment` |
| `SeatCharacteristics` carries seat price in examples | Seat map samples | Raw field retained; numeric value exposed as parsed price when applicable |
| Same `mealCode` with different prices (incl. zero bundle) | Ancillary samples | Meal identity includes charge + scope; no dedupe by code alone |
| `NOT_AVAILABLE` with empty `cabinPrices` | Search RS | Filtered out in `OneApiSearchResponseParser` |
| Sample credentials, JWTs, PII | AccessSteps, all docx | Never committed; fixtures use `ONE_API_TEST_*` placeholders |
| `isReturn` vs `"return"` field name | Booking-flow docx vs Correct_Search | Canonical: `isReturn` per `Correct_Search_Requests.odt` |
| `salesChannel` OTA vs TravelAgent | Mixed docs | Default `OTA`; configurable via connection `sales_channel` |
| `SKIP_OND_MERGE` true vs false | Mixed docs | Default `false` per corrected search doc unless connection override |
| SOAP HTTP endpoint URL | All supplied files | **Not documented** — mandatory `soap_url` on connection; live SOAP blocked until set |
| Refresh-token endpoint | Auth samples only show tokenPair | **Not invented** — re-authenticate on expiry or 401/403 |
| `JSESSIONID` in Excel only | API TEST CASE LIST | Cookie jar maintained on SOAP transport; evidence masked in logs |

## Source-of-truth priority (when samples conflict)

1. `Correct_Search_Requests.odt` — search request shape  
2. `ONE_API_BUNDLE.docx` — bundle + ancillary lifecycle  
3. `ONEAPI_BASIC_WITH_ANCI_SAMPLE.docx` — ancillary lifecycle  
4. Connection-specific ZIP samples — O&D grouping  
5. `OneAPIBookingFlow_OnHOLD.docx` — hold booking  
6. `EquiBaseFare_Sample.docx` — settlement/pay currency  
7. `Modification_For_Payment.txt` — read-PNR and hold payment  
8. `API TEST CASE LIST.xlsx` — acceptance coverage  
