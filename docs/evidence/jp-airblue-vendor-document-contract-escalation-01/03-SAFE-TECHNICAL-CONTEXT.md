# Phase 4 — Safe technical context (sanitized)

May be included in the vendor packet.

## Protocol context

```
protocol=Zapways OTA v3
environment=TEST
endpoint_family=OTAAPI v3
operation_originally_creating_passenger=AirBook
```

## Provider ticket error (sanitized)

```
provider_error_code=111
provider_error_type=13
sanitized_meaning=ID Document is required for passenger
```

No booking identifier, PNR, instance, passenger identity, or correlation ID is included.

## WSDL observation

```
WSDL_DOCTYPE_UNCONSTRAINED_STRING=YES
WSDL_DOCUMENT_SCHEMA_OPTIONAL=YES
Document/@DocType=xs:string (no enumeration)
Document/@DocID=xs:string
AirTraveler/Document=schema optional (minOccurs=0)
WSDL_SHA256=9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128
```

## Passport DocType distinction (must preserve)

```
PASSPORT_DOCTYPE_WSDL_ENUM_PROVEN=NO
PASSPORT_DOCTYPE_OPERATIONALLY_SUPPORTED=YES
CURRENT_PASSPORT_DOCTYPE_VALUE=2
```

Phrase in outbound communication as:

> currently used successfully by our integration

**Not** as:

> defined by the WSDL

## Booking 57 distinction (internal only; do not name booking 57 externally)

```
BOOKING57_DOCUMENT_REQUIREMENT_OPERATIONALLY_PROVEN=YES
GLOBAL_DOCUMENT_REQUIREMENT_SCOPE_PROVEN=NO
```

Provider code 111 / type 13 proves a business rule applied to that booking. It does **not** prove global schema/business scope for all routes/passengers.
