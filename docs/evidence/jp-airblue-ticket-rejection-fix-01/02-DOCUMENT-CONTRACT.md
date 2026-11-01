# Phase 3 — Certified Zapways OTA Document contract

## Schema sources inspected

| Source | Finding |
|---|---|
| `AirBlueOtaXmlBuilder::travelerDocumentXml` | Attributes: DocID, DocType, DocIssueCountry?, ExpireDate?, DocHolderNationality? |
| Default DocType in code | `'2'` (passport path only) |
| `tests/Fixtures/airblue/**` | **Zero** `ota:Document` / DocType samples |
| Zapways OTA XSD/WSDL DocType enum in repo | **ABSENT** |
| `docs/evidence/jp-airblue-zapways-*` | No DocType enum for national ID |
| `docs/providers/airblue-ndc-samples` | NDC `IdentityDocTypeCode=NATIONAL_ID` / `PASSPORT` — **different protocol (NDC), not Zapways OTA DocType** |

## Determined (passport only)

```
DOCUMENT_SCHEMA_SOURCE=AirBlueOtaXmlBuilder::travelerDocumentXml + default DocType 2
PASSPORT_DOCUMENT_MAPPING_SUPPORTED=YES
DOCUMENT_ID_ATTRIBUTE=DocID
DOCUMENT_EXPIRY_REQUIRED=NO
DOCUMENT_COUNTRY_REQUIRED=NO
```

Expiry and country are optional attributes (omitted when blank) — never fabricate.

## National ID DocType

```
NATIONAL_ID_DOCUMENT_MAPPING_SUPPORTED=NO
NATIONAL_ID_DOCUMENT_TYPE_VALUE=UNPROVEN
DOCUMENT_CONTRACT_INSUFFICIENT=YES
```

Mission forbids guessing DocType. NDC `NATIONAL_ID` string must **not** be used as Zapways OTA `DocType`.

## Stop

```
NEXT_PHASE=JP-AIRBLUE-DOCUMENT-CONTRACT-VERIFICATION-01
```

No national_id→Document mapping implemented in this phase.
