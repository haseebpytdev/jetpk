# Phase 3 — Historical git search

```
HISTORICAL_CONTRACT_SOURCE_FOUND=YES
SOURCE_COMMIT=evidence retained via server artifact + prior phase scripts (not a deleted repo WSDL)
SOURCE_PATH=/tmp/airblue-v3-wsdl-20261024T201600Z/OTAAPI.wsdl (server) + docs/evidence/jp-airblue-zapways-v3-contract/*
SOURCE_TYPE=Zapways OTA v3 WSDL metadata / prior certification evidence
```

## Git history results

- `git log --diff-filter=A -- '*.wsdl' '*.xsd'`: **no WSDL/XSD ever committed** to this repository.
- `git log --diff-filter=D -- '*.wsdl' '*.xsd'`: **none**.
- DocType-focused commits: prior stop commit `0076b91e` (`docs(airblue): stop ticket-rejection document fix — DocType unproven`).
- Zapways-related history is rich for SOAPAction/Read/ticketing/AirBook NRE, but **no committed DocType enumeration** for national ID / CNIC.

## Prior in-repo Zapways evidence (sanitized)

| Path | Relevance |
|---|---|
| `docs/evidence/jp-airblue-zapways-v3-contract/_raw-v3-wsdl-fetch.txt` | Prior mTLS `?WSDL` HTTP 200, 294519 bytes |
| `docs/evidence/jp-airblue-zapways-v3-contract/_wsdl-parse.json` | SOAPAction inventory only; no Document DocType enum |
| `docs/evidence/jp-airblue-zapways-airbook-doc-contract/DOCUMENT-AUDIT.md` | Structure audit; Document present; no national-ID DocType |
| `docs/evidence/jp-airblue-ticket-rejection-fix-01/02-DOCUMENT-CONTRACT.md` | Explicitly UNPROVEN national-ID DocType |

No historical AirBook sample in git proves a national-ID passenger Document with a concrete OTA DocType value.
