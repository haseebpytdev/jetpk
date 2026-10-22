# JP-AIRBLUE-PR115-PROD-DEPLOY — Final Report

**Date (UTC):** 2026-10-22  
**CURRENT_MAIN_SHA:** `8d3a42e04200ffc10cd78838af3b05b1bc8d4c96`  
**PR115_MERGE_SHA:** `ecfea62888444df8f3d6c971fd4fc8eba55787e8`  
**PRODUCTION_ROOT:** `/home/pkjetp/jetpk_app`  
**DEPLOYED_FILE_ONLY:** `app/Services/Suppliers/AirBlue/AirBlueTicketingService.php`  
**SUPPLIER_MUTATIONS_THIS_LOOP:** none

## Phase results

```
REMOTE_MAIN_SHA=8d3a42e04200ffc10cd78838af3b05b1bc8d4c96
MAIN_MATCH=YES
PR115_DEPLOYED=YES
BACKUP_PATH=/home/pkjetp/backups/airblue-pr115-pre-deploy-20261022T135959Z
PREDEPLOY_TICKETING_SHA256=55cc920ee76ddeaae9a28c2f7a8b44b5428a9c83af1414f9cedfcb08a431ee11
MAIN_TICKETING_SHA256=da7a030939723b849aa5a3b34536a7072aed13c806d1b508a309fb3e45642a98
POSTDEPLOY_TICKETING_SHA256=da7a030939723b849aa5a3b34536a7072aed13c806d1b508a309fb3e45642a98
TICKETING_PARITY=PASS
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
HTTP_UP=200
HTTP_LOGIN=200
HTTP_LOOKUP_BOOKING=200
RUNTIME_GLOBAL_KILL_SWITCH_REFERENCES=0
FINAL_RUNTIME_KILL_SWITCH_STATUS=REMOVED
```

Caches were **not** cleared (`optimize:clear` not run). No config change in PR #115.

## Legitimate connection / operation safety (retained)

```
PUBLIC_SEARCH_POLICY_PRESENT=YES
CONNECTION_CERTIFICATION_POLICY_PRESENT=YES
TICKETING_FEATURE_FLAG_PRESENT=YES
DUPLICATE_BOOKING_GUARD_PRESENT=YES
DUPLICATE_TICKETING_GUARD_PRESENT=YES
TICKETING_HAS_EXTERNAL_CALLS_GATE=NO
```

These are supplier lifecycle / eligibility rules, not global network kill switches.

## Connection 22 public routing

```
AIRBLUE_CERTIFICATION_STATUS=pending
AIRBLUE_SEARCH_CERTIFIED=NO
CONN22_PUBLIC_SEARCH_ELIGIBLE=NO
PUBLIC_UNCERTIFIED_AIRBLUE_EXCLUDED=YES
AIRBLUE_ENVIRONMENT=sandbox
AIRBLUE_ENDPOINT_HOST_ONLY=otatest4.zapways.com
```

No public search request was issued.

## Supplier call counts (this loop)

```
AIRBLUE_SEARCH_CALLS=0
AIRBLUE_AIRBOOK_CALLS=0
AIRBLUE_RETRIEVE_CALLS=0
AIRBLUE_CANCEL_CALLS=0
AIRBLUE_TICKETING_CALLS=0
AIRBLUE_VOID_CALLS=0
```

Zapways TEST connection was not altered. No AirBook / Retrieve / Cancel / Ticketing.

`AIRBLUE_*_CALLS=0` values are **script-declared** (deploy loop issued no Zapways HTTP); they are not log-derived counters. Raw deploy stdout + independent post-deploy SSH reconfirm: `_raw-deploy-verify.txt`.

## NRE evidence on main (read-only verify)

Paths present on `jetpk/main` @ `8d3a42e0`:

- `docs/evidence/jp-airblue-zapways-airbook-nre-closure/FINAL-REPORT.md`
- `docs/evidence/jp-airblue-zapways-airbook-nre-closure/SUPPLIER-ESCALATION-PACK.md`

Classification on main:

```
JETPK_AIRBOOK_CONTRACT_CHECKS=PASS_NO_HARD_MISMATCH_FOUND
JETPK_AIRBOOK_WIRE=PASS
ZAPWAYS_TEST_AIRBOOK=PROVIDER_NRE
FINAL_DECISION=PARTIAL_CONTRACT_CHECKED_PROVIDER_NRE
```

Not claimed as full XSD `SCHEMA_VALID`.

## Escalation readiness

```
ESCALATION_PACK_PRESENT=YES
ESCALATION_PACK_SANITIZED=YES
ESCALATION_PACK_READY_TO_SEND=YES
```

Pack contents (main): TEST hostname, protocol, operation, SOAPAction, HTTP 500, fault class `provider_nre`, sanitized fault snippet, contract-check result (structural / not full XSD), wire summary, route/flight/RBD/PTC/currency/total, PNR absent, instance absent, single-call count. No ClientKey, AgentPassword, client certificate, private key, SOAP auth block, or real passenger details.

Do **not** auto-email Zapways from this loop.

## Local tests (optional)

```
LOCAL_AIRBLUE_TESTS=PASS
tests=20
assertions=84
classes=AirBlueNoGlobalKillSwitchTest,AirBlueClientRedactionTest,AirBlueClientSoapActionTest,AirBlueOtaXmlBuilderTest
```

No supplier HTTP from local tests. Main-tree sources temporarily checked out for the kill-switch unit assertion; local working tree restored afterward.

## Prior release guards (reference)

- PR #115 exact-head guard: `39788672418` PASS  
- PR #116 rebased exact-head guard: `39789329507` PASS  

## Rollback

Restore from backup:

```
/home/pkjetp/backups/airblue-pr115-pre-deploy-20261022T135959Z/app/Services/Suppliers/AirBlue/AirBlueTicketingService.php
```

to

```
/home/pkjetp/jetpk_app/app/Services/Suppliers/AirBlue/AirBlueTicketingService.php
```

Pre-deploy SHA256: `55cc920ee76ddeaae9a28c2f7a8b44b5428a9c83af1414f9cedfcb08a431ee11`

## Final status

```
PR115_DEPLOYED=YES
TICKETING_PARITY=PASS
RUNTIME_GLOBAL_KILL_SWITCH_REFERENCES=0
FINAL_RUNTIME_KILL_SWITCH_STATUS=REMOVED
SUPPLIER_CALLS=0
```
