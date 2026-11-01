# Phase 5 — Composer / SDK / package sources

```
PACKAGE_SCHEMA_FOUND=NO
DOCTYPE_ENUM_FOUND=NO
DOCTYPE_ENUM_VALUES_SAFE=ABSENT
```

## Inspection

- `composer.lock`: no Zapways / AirBlue / OpenTravel schema package dependency.
- Local `vendor/`: no matching WSDL/XSD containing passenger Document DocType enum for Zapways OTA v3 AirBook.
- Runtime builders construct SOAP XML manually (`AirBlueOtaXmlBuilder`); no generated SOAP client enum for Document.DocType.

Conclusion: package ecosystem does **not** prove `NATIONAL_ID_DOCUMENT_TYPE_VALUE`.
