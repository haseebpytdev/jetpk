# Phase 12 — Vendor escalation packet (PREPARE ONLY — DO NOT SEND)

```
VENDOR_ESCALATION_PACKET_READY=YES
EXTERNAL_SEND_AUTHORIZED=NO
```

## Sanitized packet body

```
operation=AirBook / passenger Document
protocol=Zapways OTA v3
environment=TEST (otatest4.zapways.com / v3.0 OTAAPI)
question=What is the correct ota:Document/@DocType value for a Pakistani CNIC / national ID passenger when passport is absent?
known_passport_DocType=2
provider_ticket_error_code=111
provider_ticket_error_type=13
sanitized_error_meaning=ID Document is required for passenger
wsdl_observation=Document/@DocType is xs:string with no enumeration in OTAAPI.wsdl (ns http://zapways.com/air/ota/3.0); SHA256 9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128
ask_also=Confirm whether DocIssueCountry / ExpireDate / DocHolderNationality are required for national ID; confirm CNIC formatting (digits vs hyphens); confirm whether Document is mandatory at AirBook vs only before AirDemandTicket; confirm DocType distinctions among National ID / CNIC / NIC / identity card if any.
```

## Explicitly excluded from packet

- Real passenger name
- Real national ID / passport values
- PNR / instance
- Credentials / mTLS material
- Raw booking/response XML
- Full correlation IDs

Do **not** send externally without explicit authorization.
