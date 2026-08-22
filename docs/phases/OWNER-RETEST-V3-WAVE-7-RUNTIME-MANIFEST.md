# Wave-7 Runtime Manifest (PRE-DEPLOY — DO NOT APPLY YET)

## Status
STOP BEFORE PRODUCTION DEPLOYMENT. Independent ChatGPT/owner review required.

`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED` (do not mark PASS)

## Current deployed runtime (live)
- Source SHA: `9653d5ab488ec6ba971ff76324894057ca8c3ffb`
- Public build: `JK8nDb8vrOeyjOA4Ue1Jg`

## Wave-7 engineering tip (ready after review)
- Branch: `feat/jetpk-flight-results-booking-flow-20260819`
- Remote: `jetpk`
- Engineering SHA: 1b0df8d464b07073aeafd6c5ac65090e762de105

## Delta intent
Engineering remediation only from deployed `9653d5ab…` through Wave-7 tip:
- Selected branded fare persistence through Travelers
- Authoritative multipax Fare Details (FX-normalized PTC rows)
- Local passport OCR reliability + title rules
- Flight Summary / Change flight (pre-hold) / Terms consent

## Deploy actor
Only the established protected JetPakistan deployment scripts after explicit owner authorization.

## Forbidden during this gate
- Ad-hoc SSH/SFTP/SCP outside protected scripts
- Live supplier commercial mutations (PNR/hold/ticket/payment/wallet)
