# OneAPI Auth + Search (A1/A4)

DATE: 2026-11-02

## Credential contract (A1)

Credentials MUST come from encrypted `SupplierConnection` fields only:

- username / password / agent_code
- agent_preferred_currency / pos_country / pos_station / sales_channel
- rest_auth_url / rest_search_url / soap_url

Vendor UAT endpoint shapes (not secrets):

- REST AUTH: `https://aero-suite-stage4-airarabia.isaaviation.net/api/auth/authenticate`
- REST SEARCH: `https://aero-search-api-service-stage4-airarabia.isaaviation.net/aerosearch.FlightService/findOndWiseFlightCombinations`
- Mandatory headers: `X-AERO-SALES-CHANNEL`, `X-AERO-JOURNEY-TYPE`, `X-AERO-USERID`, `X-AERO-AGENT-CODE`

## Local credential gate

| Check | Result |
|-------|--------|
| `.env` ONE_API_* lines | 0 |
| `supplier_connections` provider=`one_api` | 0 |
| Encrypted UAT connection ready | NO |

ONEAPI_LIVE_CREDENTIAL_GATE=`TEMPORARILY_BLOCKED`

## Offline auth/search proof

PHPUnit OneAPI filter (post date-skew fix + session cert tests): see `11-TESTS.md`.

Fixture search routes used in offline fixtures include SHJ/KHI style OD; live probes will use vendor-approved UAT routes only once credentials exist:

- G9: SHJ-COK, COK-SHJ, AMM-SHJ, SHJ-AMM, MCT-SHJ
- E5: CAI-MCT, HBE-BGY
- 3L: BAH-AUH, AUH-BAH, AUH-MCT, AUH-BOM
- 3O: SAW-CMN, CMN-SAW

## Live search status

ONEAPI_AUTH=`NOT_ATTEMPTED_LIVE` (credential gate)  
ONEAPI_SEARCH=`NOT_ATTEMPTED_LIVE` (credential gate)  
Offline fixture auth/search coverage=`PASS` (suite)
