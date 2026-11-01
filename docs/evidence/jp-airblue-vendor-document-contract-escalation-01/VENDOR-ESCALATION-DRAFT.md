# Vendor escalation draft — Zapways OTA v3 passenger Document / national ID

Status: **DRAFT ONLY — DO NOT SEND**

```
EXTERNAL_SEND_AUTHORIZED=NO
VENDOR_CONTACT_CONFIDENCE=NONE
```

Delivery channel is unset until an owner-authorized, JetPakistan-verified Zapways/AirBlue technical support path is supplied. Do not send to guessed addresses.

---

Subject: Zapways OTA v3 — request for Pakistani national ID / CNIC Document contract (DocType / DocID / timing)

Hello Zapways / AirBlue API support,

We integrate AirBlue via **Zapways OTA v3** (`OTAAPI` v3, TEST environment). We need the authoritative passenger-document business contract for Pakistani national ID / CNIC when no passport is supplied.

### Safe technical context

- Protocol: Zapways OTA v3
- Environment: TEST
- Endpoint family: OTAAPI v3
- Operation that originally creates the passenger: AirBook
- Observed provider ticketing rejection (sanitized): code `111`, type `13` — meaning “ID Document is required for passenger”
- WSDL observation: `Document/@DocType` is an unconstrained `xs:string` with no enumeration; `AirTraveler/Document` is schema-optional
- Authoritative WSDL SHA256: `9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128`
- Passport `DocType` currently used successfully by our integration: `2` (this is **not** claimed as a WSDL-enumerated value)

### Questions (please answer all)

1. What exact `ota:Document/@DocType` value must be used for a Pakistani CNIC / national ID when no passport is supplied?

2. Is that expected `DocType` value numeric, textual, or another vendor-defined code?

3. What exact format should `ota:Document/@DocID` use for Pakistani CNIC? Specifically: are hyphens required, forbidden, digits-only required, or should the value be sent exactly as entered?

4. For national ID, does AirBlue business validation require any of `DocIssueCountry`, `ExpireDate`, or `DocHolderNationality` (WSDL marks these optional)?

5. At what lifecycle point must `Document` be supplied? Please answer separately for: AirBook creation; before AirDemandTicket; on the AirDemandTicket request; or another update step.

6. Does this document requirement apply to all AirBlue bookings, only domestic Pakistan bookings, only Pakistani nationals, only specific passenger types, only TEST, or another defined scope?

7. Is there a supported Zapways OTA v3 operation to add/update a passenger `Document` after AirBook? If yes, please provide the operation name, `ModificationType` if applicable, and the required request structure.

8. Please provide one sanitized example AirBook passenger snippet showing `Document` with `DocID` placeholder, `DocType`, and any required optional identity attributes for a national-ID / CNIC passenger. Do not include real passenger data.

Thank you — we will not implement a national-ID `DocType` mapping until these answers are confirmed.

---

## Exclusions checklist (outbound)

No passenger name, CNIC, passport number, PNR, instance, ticket number, booking reference, credentials, mTLS material, raw SOAP XML, correlation IDs, pricing, or supplier confidential amounts.
