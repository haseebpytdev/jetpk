# JP-AIRBLUE-ZAPWAYS-AIRBOOK-DOC-CONTRACT — Final Report

**MAIN_SHA (base):** `d1675678b0d3d485b23d4bcbfbd4a848fe34c3f8`  
**Branch:** `fix/airblue-zapways-airbook-doc-contract`  
**Conn 22:** Zapways TEST `otatest4.zapways.com` v2.0  

## Result

```
AIRBOOK=PASS
AIRBOOK_HTTP_STATUS=200
PNR_PRESENT=YES
INSTANCE_PRESENT=YES
PNR_HASH=f08f2faf6b61
RETRIEVE=PASS
WINNING_PROTOCOL=2.0
WINNING_VARIANT=V2_DOC_FAITHFUL
DOCUMENT_CONTRACT_FIX_APPLIED=YES
AUTHORIZED_TEST_SEARCH_CALLS=1
AUTHORIZED_TEST_AIRBOOK_CALLS=1
RETRIEVE_CALLS=1
```

## Key search facts (live)

```
SEARCH_RBD=U
ACTUAL_PROVIDER_FARE_BASIS=EFTAOWU
FARE_BASIS_EQUALS_RBD=NO
FARE_BASIS_SOURCE=fare_info_attr
EXACT_ODO_PRESERVED=YES
EXACT_PTC_FARE_BREAKDOWN_PRESERVED=YES
```

Prior NRE attempts used reconstructed itinerary/pricing and often treated fare basis as RBD (`U`). Live search returns distinct `FareInfo/@FareBasisCode=EFTAOWU`.

## Root cause

```
ROOT_CAUSE_IDENTIFIED=PARTIAL
ROOT_CAUSE_CANDIDATES=
- preserve exact OriginDestinationOption from LowFareSearch
- preserve exact PTC_FareBreakdown / pricing subtree (incl. FareInfo/@FareBasisCode)
- NameTitle instead of NamePrefix
- PassengerTypeQuantity child instead of PassengerTypeCode attribute
- Telephone PhoneLocationType + CountryAccessCode + PhoneNumber
```

Multiple documented corrections shipped together; offline diffs + one successful AirBook. No extra supplier calls to isolate a single field.

## Offline tests

```
OFFLINE_TESTS=PASS
tests=20
assertions=105
XML_STRUCTURE=PASS
FARE_BASIS=PASS
PTC_FARE_BREAKDOWN=PASS
TRAVELER_STRUCTURE=PASS
TELEPHONE_STRUCTURE=PASS
SECRET_REDACTION=PASS (existing client redaction suite not re-run this loop)
```

## Isolation

```
NON_CONN22_CONNECTION_ROWS_CHANGED=0
TEST_INITIATED_NON_AIRBLUE_CALLS=0
SABRE_CONNECTION_CHANGED=NO
PIA_NDC_CONNECTION_CHANGED=NO
```

## Production deploy (experimental for the single authorized call)

```
BACKUP=/home/pkjetp/backups/airblue-doc-contract-pre-20261022T174209Z
FILES=AirBlueOtaXmlParser.php,AirBlueOtaXmlBuilder.php
```

Do **not** cancel or ticket this PNR until PR review completes.

## Next

1. Runtime PR review (do not merge from agent).
2. After merge/deploy: Booking A cancel lifecycle + Booking B ticketing.
3. Post-ticket reversal via AirBookModify ModificationType per v2.06 (not “void”).
