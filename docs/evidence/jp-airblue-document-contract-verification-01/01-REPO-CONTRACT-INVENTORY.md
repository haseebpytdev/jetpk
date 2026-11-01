# Phase 2 — Repo-wide contract inventory

DATE: 2026-11-01  
BASE: `5d75d9c7b8c523be9c0c5cdb8c93ad691402c46f`

## Search terms

`DocType`, `DocID`, `Document`, `travelerDocumentXml`, `IdentityDocTypeCode`, `NATIONAL_ID`, `PASSPORT`, `CNIC`, `NationalID`, `NationalId`, identity/travel document.

## Counts

```
REPO_DOCUMENT_REFERENCES_COUNT=613
OTA_DOCUMENT_REFERENCE_FILES=26
NDC_IDENTITY_FILES=6
WSDL_FILES_FOUND=0
XSD_FILES_FOUND=0
ZAPWAYS_NAMED_UNDER_DOCS=17
ZAPWAYS_MD_FILES=71
```

## OTA (Zapways) findings

| Location | Finding |
|---|---|
| `AirBlueOtaXmlBuilder::travelerDocumentXml` | Emits `ota:Document` with `DocID`, `DocType`, optional `DocIssueCountry`/`ExpireDate`/`DocHolderNationality`; default DocType `'2'` |
| `AirBluePassengerPayloadBuilder` | Maps `passport_number` only; `national_id_number` not mapped |
| `tests/Fixtures/airblue/**` | No `ota:Document` / national-ID DocType samples |
| Repo WSDL/XSD | **ABSENT** |
| Prior FIX-01 evidence | Passport DocType `2` repo-proven; national-ID DocType **UNPROVEN** |

## NDC findings (non-authoritative for OTA DocType)

| Location | Finding |
|---|---|
| `docs/providers/airblue-ndc-samples/**` | `IdentityDocTypeCode=NATIONAL_ID` / `PASSPORT` |
| PIA NDC fixtures/samples | Same NDC identity codes |

**Do not** treat NDC `NATIONAL_ID` as Zapways OTA `DocType`.

## Package / Composer

No Zapways/AirBlue SOAP schema package in `composer.lock`. No vendor WSDL/XSD for Document DocType enum.
