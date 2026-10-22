# JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT — Post-PR113 Continuation

**Date (UTC):** 2026-10-22  
**MAIN_SHA:** `c8dbaedbb0d2ba3f8f66337a427d6c706f376d8e` (PR #113 merged)  
**Endpoint:** `otatest4.zapways.com`  
**Environment:** `sandbox`  
**Protocol:** Zapways OTA `2.0`  
**PRODUCTION_AIRBLUE_CONNECTION_USED:** NO

## Executive result

| Area | Status |
|------|--------|
| PR #113 scoped deploy (2 builders) | **PASS** (SHA256 parity) |
| HTTP `/up` `/login` `/lookup-booking` | **200** |
| TLS / mTLS / connectivity | **CERTIFIED** |
| Auth + Search (+ offers) | **CERTIFIED** (ISB→KHI, 6 offers) |
| Search contract invariants | **PASS** |
| Booking A (book/retrieve/cancel) | **BLOCKED** — post-PR113 still `PROVIDER_NRE` |
| Booking B / Ticketing | **NOT ATTEMPTED** |
| Void | **NOT_SUPPORTED_CURRENT_OTA_CONTRACT** |
| Kill switches after loop | **OFF** |
| Production live activation | **NOT_STARTED** |

## Deploy evidence

```
AUTHORIZED_SHA=c8dbaedbb0d2ba3f8f66337a427d6c706f376d8e
BACKUP_PATH=/home/pkjetp/backups/airblue-pr113-pre-deploy-20261022T105656Z
PREDEPLOY_XML=0dbf837c256b06f0642909810984effb7d0238c1b1651e95c1051f682defe36f
PREDEPLOY_PAX=a9da02483244d4d847aa00e1277aec949daaf59e63e7a949cd9f6f2e64cdcef1
POSTDEPLOY_XML=a2a4c11be130df687368a1264cea9321589511fe25498a343226422f927387dc
POSTDEPLOY_PAX=433a143f62022499fb8b1f6b632709cbbfba0ce73421129d0622e91201410a11
XML_BUILDER_PARITY=PASS
PASSENGER_BUILDER_PARITY=PASS
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
PR113_DEPLOYED=YES
```

Test file was **not** deployed (per scope).

## Connection / TLS

- `AIRBLUE_CONNECTION_ID=22` (count=1, active)
- `ENVIRONMENT=sandbox` / `PROTOCOL=2.0` / `API_CHANNEL=zapways_ota`
- `HOST=otatest4.zapways.com`
- Credential fields present (values not recorded)
- mTLS cert/key present, PEM valid, key match, runtime-readable as `pkjetp`
- `ZAPWAYS_TLS=PASS` / `ZAPWAYS_MTLS=PASS` / `ZAPWAYS_CONNECTIVITY=PASS`

## Search

- `NEW_SEARCH_CALLS=2` (phase5 offer discovery + phase13 fresh B fixture refresh; both ISB-KHI inventory)
- `AUTH_ACCEPTED=YES` / offer-rich / PKR / invariants PASS
- Wire dry-run after PR113: `Gender=Male`, `NamePrefix=MR`, `FareBasis=U`, `OperatingAirline=PA`

## Previous AirBook (pre-PR113)

```
PREVIOUS_AIRBOOK_ERROR_CLASS=SOAP_CLIENT_SCHEMA_REJECTION
PREVIOUS_AIRBOOK_SOAP_CLIENT_VALIDATION=YES
PREVIOUS_AIRBOOK_PNR_RETURNED=NO
PREVIOUS_AIRBOOK_INSTANCE_RETURNED=NO
PREVIOUS_AIRBOOK_UNCERTAIN=NO
```

Not retried.

## Booking A (post-PR113)

- QA fixture role `A_CANCEL` / `local_qa` / synthetic passenger
- `BOOKING_A_AIRBOOK_CALLS=1` → `supplier_http_error`
- Diagnostic capture (no local booking mutation): HTTP **500**, fault class **PROVIDER_NRE**  
  `Object reference not set to an instance of an object.`
- One NRE variant probe (RPH/cabin/equipment/farebasis sibling) still NRE — stopped further probing
- Retrieve/cancel not reached (no PNR)
- `CUSTOMER_EMAIL_SENT=0`

## Ticketing / Void

- Ticketing not attempted (`BLOCKED` on booking)
- Void provider calls: **0** (`void_unsupported`)

## Restore

```
AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED_AFTER=NO
AIRBLUE_CLI_LIVE_ENABLED_AFTER=NO
AIRBLUE_PUBLIC_SEARCH_ENABLED_AFTER=NO
ticketing_enabled=false
cancel_enabled=false
booking_enabled=true
ENV=sandbox
HOST=otatest4.zapways.com
AIRBLUE_PRODUCTION_LIVE_CREDENTIALS_CONFIGURED=NO
AIRBLUE_PRODUCTION_LIVE_ACTIVATION=NOT_STARTED
```

## Follow-up runtime branch (not merged)

`fix/airblue-zapways-airbook-nre-diagnostics` @ `bb461f5c032e8b1ed365132381e4a255f58926ca`

- Surfaces sanitized `fault_class` / `fault_snippet` on provider failures
- Ensures `storage/logs/airblue.log` exists
- Maps `passenger_type` adult/child/infant → ADT/CHD/INF
- Does **not** claim an NRE payload fix

## Certification labels

```
AIRBLUE_ZAPWAYS_SANDBOX_TLS=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_AUTH=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_SEARCH=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_PRICE_NORMALIZATION=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_BOOKING=BLOCKED_PROVIDER_NRE
AIRBLUE_ZAPWAYS_SANDBOX_RETRIEVE=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_CANCEL=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_TICKETING=BLOCKED_BOOKING_NOT_CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_VOID=NOT_SUPPORTED_CURRENT_OTA_CONTRACT
FINAL_SANDBOX_CERT_DECISION=PARTIAL_BOOKING_BLOCKED_PROVIDER_NRE
```
