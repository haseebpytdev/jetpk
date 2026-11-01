# Phase 7 — Authoritative source standard

## Schema / attribute contract (proven)

```
AUTHORITATIVE_SOURCE_FOUND=YES
AUTHORITATIVE_SOURCE_CLASS=A
AUTHORITATIVE_SOURCE_NAME=Zapways otatest4 v3.0 OTAAPI.wsdl DocumentType (inline schema)
AUTHORITATIVE_SOURCE_HASH=9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128
AUTHORITATIVE_SOURCE_PROVENANCE=PASS
```

Proven via class **A** (Zapways-provided OTA WSDL). mTLS `?WSDL` HTTP 200 on 2026-11-01; hash matches prior 2026-10-24 fetch.

## National-ID DocType **value** (not proven)

Accepted classes A–E searched. Result for `NATIONAL_ID_DOCUMENT_TYPE_VALUE`:

| Class | Result |
|---|---|
| A WSDL/XSD enum | DocType is unconstrained `string`; **no enum values** |
| B Official OTA integration guide | **Not found** on server/repo |
| C Certified OTA AirBook sample with national-ID Document | **Not found** |
| D Historical JetPakistan successful national-ID AirBook with visible DocType | **Not found** in trustworthy evidence |
| E Retained vendor support docs | **Not found** defining CNIC/national-ID DocType |

Rejected non-sources (explicitly not used): NDC `NATIONAL_ID`, Sabre codes, generic OpenTravel assumptions, Stack Overflow, model inference, “passport=2 therefore national-id=?”.

```
NATIONAL_ID_DOCUMENT_TYPE_PROVEN=NO
NATIONAL_ID_DOCUMENT_TYPE_VALUE=UNPROVEN
```
