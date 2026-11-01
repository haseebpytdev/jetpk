# Phase 8–9 — Document contract determination

## Attribute contract (from Zapways OTA v3 WSDL)

```
DOCUMENT_ID_ATTRIBUTE=DocID
DOCUMENT_TYPE_ATTRIBUTE=DocType
DOCUMENT_ISSUE_COUNTRY_REQUIRED=NO
DOCUMENT_EXPIRY_REQUIRED=NO
DOCUMENT_HOLDER_NATIONALITY_REQUIRED=NO
```

All of the above are schema attributes/elements without `use="required"`. AirTraveler `Document` itself is `minOccurs="0"`.

## National ID / CNIC

```
NATIONAL_ID_DOCUMENT_MAPPING_SUPPORTED=NO
NATIONAL_ID_DOCUMENT_TYPE_PROVEN=NO
NATIONAL_ID_DOCUMENT_TYPE_VALUE=UNPROVEN
NATIONAL_ID_FORMAT_RULE=UNPROVEN
```

WSDL does **not** distinguish National ID vs CNIC vs NIC vs identity card vs resident card as DocType enumerations. Do not conflate them in code until vendor clarifies.

Do **not** normalize CNIC digits/hyphens unless a later authoritative source requires a format.

## Requirement timing

```
DOCUMENT_REQUIRED_AT_AIRBOOK=OPTIONAL_IN_SCHEMA
DOCUMENT_REQUIRED_BEFORE_TICKETING=UNPROVEN_FROM_CONTRACT
DOCUMENT_REQUIREMENT_SCOPE=UNPROVEN
```

Schema allows omitting Document at AirBook. Business mandate for ticketing / route / nationality scope is **not** stated in the WSDL or retained Zapways guide. Booking 57 provider rejection (code 111 / type 13) is operational evidence only and is **not** treated as the contract definition of scope.
