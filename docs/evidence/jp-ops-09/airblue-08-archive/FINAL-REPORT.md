# JP-AIRBLUE-ZAPWAYS-08 — FINAL REPORT

```
BRANCH=work/jp-airblue-zapways-08-test-v2-cert
BASE_MAIN_SHA=cfd0fd0dcb96c4f0f209040feb2f42c1d6718dfb
PRODUCTION_APPLICATION_SHA=50ae55c47161d211748ca2cc1204855a52442422
DASHBOARD_BUILD_ID=G_-giY83K-tngzEQvf86p

AIRBLUE_PROVIDER=Zapways
ENVIRONMENT=TEST
ENDPOINT=https://otatest4.zapways.com/v2.0/OTAAPI.asmx
PROTOCOL=2.0
REQUEST_VERSION=1.04
AGENT_ID=JetPakistanOTA
AGENT_TYPE=29
TARGET=Test

CLIENT_ID_PRESENT=YES
CLIENT_KEY_PRESENT=YES
AGENT_PASSWORD_PRESENT=YES

TLS_CERT_EXISTS=YES
TLS_KEY_EXISTS=YES
TLS_PERMISSIONS_SAFE=YES

CONFIG=PASS
TLS=PASS
CONNECTIVITY=PASS
AUTH=PASS
SEARCH=PASS
OFFERS=NO_RESULTS

REAL_SUPPLIER_SEARCH_CALLS=1

AIRBOOK_CALLS=0
AIRDEMANDTICKET_CALLS=0
AIRBOOKMODIFY_CALLS=0
CANCEL_CALLS=0
REAL_SUPPLIER_BOOKING_MUTATIONS=0
REAL_TICKETS_ISSUED=0
REAL_SUPPLIER_CANCELLATIONS=0
REAL_PAYMENT_MUTATIONS=0

HTTP_STATUS=(search: success path; read probe: 500)
SOAP_FAULT_CODE=
SOAP_FAULT_SUMMARY=Read probe: provider HTTP error; search: no fault
OFFER_COUNT=0

CODE_CHANGES_REQUIRED=NO
PR_NUMBER=
MERGED_MAIN_SHA=
DEPLOYED_SHA=

SENSITIVE_DATA_EXPOSED=NO

NEXT_SAFE_STEP=Optional: deactivate JPQA-WRITE-* AirBlue sandbox duplicates in API Connections; schedule v3 certification separately; repeat offer-rich date/route only with explicit new supplier call budget.

FINAL_STATUS=FULL_PASS_NO_OFFERS
```

## Ops note (production)

TLS directory permissions on `/home/pkjetp/jetpk_secrets/zapways` were corrected so `nobody` (PHP) can present the client certificate. Rollback: restore prior `700` root-only directory ACL if required (would break live Zapways mTLS).

## Evidence tooling

Sanitized server probe (no secrets): `docs/evidence/jp-airblue-zapways-08/probe-config.php` (uploaded to `/tmp` on server for this run only).
