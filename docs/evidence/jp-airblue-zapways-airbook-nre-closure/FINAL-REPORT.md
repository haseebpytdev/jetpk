# JP-AIRBLUE-ZAPWAYS-AIRBOOK-NRE-CLOSURE — Final Report

**Date (UTC):** 2026-10-22  
**MAIN_SHA:** `1f37e0197f296398e4b0c7d687e9d60b2dfea3c2` (PR #114)  
**PRODUCTION_RUNTIME:** JetPakistan production (`/home/pkjetp/jetpk_app`, `https://jetpakistan.pk`)  
**AIRBLUE_CONNECTION_ENVIRONMENT:** sandbox  
**AIRBLUE_CREDENTIAL_CLASS:** TEST (Zapways)  
**AIRBLUE_ENDPOINT_HOST_ONLY:** `otatest4.zapways.com`  
**AIRBLUE_CONNECTION_ID:** 22  
**PROTOCOL:** Zapways OTA 2.0  
**REAL_AIRBLUE_CREDENTIALS_CONFIGURED:** NO  
**PUBLIC_AIRBLUE_ELIGIBILITY:** pending_certification (`search_certified=NO`, `certification_status=pending`)  
**GLOBAL_AIRBLUE_KILL_SWITCHES:** REMOVED

## Executive result

| Gate | Result |
|------|--------|
| PR #114 scoped deploy (5 files) | **PASS** (SHA256 parity) |
| Obsolete env kill-switch keys | **ABSENT** (count=0) |
| HTTP `/up` `/login` `/lookup-booking` | **200** |
| Public uncertified AirBlue excluded | **YES** |
| TLS / mTLS (conn 22) | **PASS** (`CERT_KEY_MATCH=YES`) |
| Auth + Search | **PASS** (fresh ISB→KHI, 6 offers) |
| WSDL AirBook metadata | **YES** (HTTP 200; types/SoapAction present) |
| Dry-run AirBook vs contract | **NO hard defects** |
| Passenger type adult→ADT | **PASS** |
| One AirBook (post contract PASS) | **FAIL** — `FAULT_CLASS=provider_nre` HTTP 500 |
| Retrieve / Cancel / Ticket | **NOT_REACHED** |
| Void | **NOT_SUPPORTED_CURRENT_OTA_CONTRACT** |

## Classification (Phase 15)

```
JETPK_AIRBOOK_CONTRACT_CHECKS=PASS_NO_HARD_MISMATCH_FOUND
JETPK_AIRBOOK_WIRE=PASS
ZAPWAYS_TEST_AIRBOOK=PROVIDER_NRE
AIRBOOK_CALLS=1
NO_SECOND_AIRBOOK=YES
```

Sanitized fault:

`Server was unable to process request. ---> Object reference not set to an instance of an object.`

Wire fingerprint (no PII): Gender=Male, NamePrefix=MR, PTC=ADT, FareBasis/RBD=U, OperatingAirline=PA, Target=Test, Version=1.04, route ISB-KHI flight 201, total 26000 PKR.

Soft findings (not treated as hard contract defects): `CabinClass` / `FareType` attributes absent on FlightSegment (search inventory also omits them). FareBasisCodes nested inside PassengerFare matches Zapways search RS shape and OTA common order checks passed.

## Deploy parity

```
PR114_DEPLOYED=YES
AUTHORIZED_SHA=1f37e0197f296398e4b0c7d687e9d60b2dfea3c2
GUARD_PARITY=PASS
BOOKING_SERVICE_PARITY=PASS
CLIENT_PARITY=PASS
PASSENGER_BUILDER_PARITY=PASS
SUPPLIERS_CONFIG_PARITY=PASS
BACKUP=/home/pkjetp/backups/airblue-pr114-pre-deploy-20261022T131604Z
```

Git-object digests extracted via `git archive 1f37e019…` (local staging before SCP) matched post-deploy APP digests:

| File | SHA256 |
|------|--------|
| GuardsAirBlueLiveNetwork.php | `1f4b20cd662855e30459b8d53b9f07307153abf440aa9a0f8cb9d11b0617e186` |
| AirBlueBookingService.php | `b7d642f3cf96270b8dd3f2f8fd640aadcf6a96b55aded10acb960f0c236d599f` |
| AirBlueClient.php | `9c92ffe58e8873a853d4101c7804c2b0a9edd9aa05ea2df856a598dbdfc13ba8` |
| AirBluePassengerPayloadBuilder.php | `5b6ab77b4c8833694338ff5cf5e5363103fd5c052e751f7bc158b1f0a9d68750` |
| config/suppliers.php | `f29983d017726e9da88ba17076a18967d175c023fffa45c7cd7897d8efce51a7` |

## TLS note

Phase 4 scripted `openssl pkey -modulus` comparison initially reported `CERT_KEY_MATCH=NO` (tool mismatch). Immediate re-check with `openssl rsa -modulus` on the same canonical paths reported `CERT_KEY_MATCH=YES`. Search + AirBook mTLS to `otatest4` succeeded, confirming runtime-usable pair.

## Architecture note

There is **no** global AirBlue kill switch after PR #114 for search/booking client paths.

Public/customer routing safety is:

- SupplierConnection state
- `AirBlueConnectionSearchPolicy`
- `certification_status` / `search_certified`
- operation-specific feature flags where applicable

CLI `--allow-live` is a manual command acknowledgement only.

Obsolete keys `AIRBLUE_EXTERNAL_CALLS_ENABLED` and `AIRBLUE_CLI_LIVE_ENABLED` were **not present** in production `.env` (nothing to remove). `config/suppliers.php` no longer defines `external_calls_enabled`.

Subsequent repository cleanup: PR #115 removes the final stale `AirBlueTicketingService` read of the retired global flag and adds a regression guard against reintroduction. That cleanup is a separate post-certification runtime change and requires its own scoped production deployment evidence.

## Supplier call counts (this loop)

| Op | Count |
|----|------:|
| Search | 1 |
| AirBook | 1 |
| Retrieve | 0 |
| Cancel | 0 |
| Ticketing | 0 |
| Void | 0 |
| Production-live AirBlue endpoint | 0 |

## Certification labels

```
AIRBLUE_ZAPWAYS_SANDBOX_SEARCH=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_BOOKING=BLOCKED_PROVIDER_NRE
AIRBLUE_ZAPWAYS_SANDBOX_RETRIEVE=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_CANCEL=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_TICKETING=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_VOID=NOT_SUPPORTED_CURRENT_OTA_CONTRACT
FINAL_DECISION=PARTIAL_CONTRACT_CHECKED_PROVIDER_NRE
```

## Escalation pack (sanitized)

- timestamp UTC: 2026-10-22 (phase11-13 run)
- TEST hostname: `otatest4.zapways.com`
- protocol: 2.0
- operation: AirBook
- SOAPAction: `http://zapways.com/air/ota/2.0/AirBook`
- HTTP status: 500
- fault class: `provider_nre`
- QA booking id hash: `71ee45a3c0db`
- request structural digest: `811efc339d7d`
- offer id hash: `375fa1c45e1d`

No passwords, client keys, certificates, private keys, or full passenger details included.

## Next

1. Escalate Zapways TEST AirBook NRE with the pack above (provider NRE persists after JetPK WSDL/contract structural checks and wire checks).  
2. Do **not** resume random payload probing.  
3. Do **not** enable public AirBlue or configure real AirBlue production credentials until booking cert clears.
