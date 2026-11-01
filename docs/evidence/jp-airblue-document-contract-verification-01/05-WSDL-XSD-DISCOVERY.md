# Phase 6 — Static WSDL/XSD discovery

## Local (no client cert)

```
STATIC_WSDL_DISCOVERY_ATTEMPTED=YES
STATIC_WSDL_HTTP_RESULT=FAIL_TLS_MTLS_REQUIRED
```

`https://otatest4.zapways.com/v3.0/OTAAPI.asmx?WSDL` HEAD/GET from the workstation closed without HTTP status (mTLS expected). No SOAP POST attempted. No credentials placed in URL.

## Server mTLS static GET (allowed metadata path)

```
STATIC_WSDL_HTTP_RESULT=200
STATIC_WSDL_BYTES=294519
STATIC_WSDL_SHA256=9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128
STATIC_WSDL_FOUND=YES
STATIC_XSD_FOUND=NO
TARGET_NS=http://zapways.com/air/ota/3.0
```

Separate XSD files: **not** published beside the ASMX endpoint in this discovery. Document schema is **inline** inside the WSDL.

## Schema facts (sanitized)

From Zapways OTA v3 `DocumentType` / AirTraveler Document:

- `DocType` attribute: `type="s:string"` — **no enumeration**
- `DocID` attribute: `type="s:string"`
- `DocIssueCountry`, `DocHolderNationality`: optional strings
- `ExpireDate`: optional `s:date`
- AirTraveler child `Document`: `minOccurs="0"` (schema-optional at AirBook)
- Tokens `NATIONAL_ID` / `CNIC`: **absent** from WSDL
- `DOCTYPE_ENUM_COUNT=0`

ASMX AirBook help page shows placeholder `<Document xsi:nil="true" />` / TicketDocument scaffolding — **no** national-ID DocType sample values.
