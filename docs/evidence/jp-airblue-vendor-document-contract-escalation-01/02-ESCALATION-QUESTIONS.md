# Phase 3 — Exact vendor questions

Status: **PREPARE ONLY — DO NOT SEND** without explicit owner authorization.

Ask Zapways / AirBlue to confirm **all** of the following for Zapways OTA v3.

## Q1 — National ID / CNIC DocType value

What exact `ota:Document/@DocType` value must be used for a Pakistani CNIC / national ID when no passport is supplied?

```
QUESTION_DOCTYPE_INCLUDED=YES
```

## Q2 — DocType value form

Is the expected `DocType` value numeric, textual, or another vendor-defined code?

Do **not** suggest guessed alternatives in the outbound message.

```
QUESTION_DOCTYPE_FORM_INCLUDED=YES
```

## Q3 — CNIC DocID format

What exact format should `ota:Document/@DocID` use for Pakistani CNIC?

Confirm whether:

- hyphens are required
- hyphens are forbidden
- digits-only is required
- the value should be sent exactly as entered

```
QUESTION_CNID_FORMAT_INCLUDED=YES
```

## Q4 — Optional identity attributes (business layer)

For national ID, are any of these required by the AirBlue business validation layer (WSDL marks them optional):

- `DocIssueCountry`
- `ExpireDate`
- `DocHolderNationality`

```
QUESTION_OPTIONAL_ATTRIBUTES_INCLUDED=YES
```

## Q5 — Lifecycle timing

At what lifecycle point must `Document` be supplied?

Ask separately about:

- AirBook creation
- before AirDemandTicket
- AirDemandTicket request
- or another update step

```
QUESTION_REQUIREMENT_TIMING_INCLUDED=YES
```

## Q6 — Requirement scope

Does this requirement apply to:

- all AirBlue bookings
- only domestic Pakistan bookings
- only Pakistani nationals
- only specific passenger types
- only TEST
- or another defined scope

```
QUESTION_REQUIREMENT_SCOPE_INCLUDED=YES
```

## Q7 — Existing PNR document update

Is there a supported Zapways OTA v3 operation to add/update a passenger `Document` after AirBook?

If yes, provide:

- operation name
- `ModificationType` if applicable
- required request structure

```
QUESTION_EXISTING_PNR_UPDATE_INCLUDED=YES
```

## Q8 — Sanitized sample snippet

Request one sanitized / example AirBook passenger snippet showing:

- `Document`
- `DocID` placeholder
- `DocType`
- optional identity attributes

for a national-ID / CNIC passenger.

Do **not** request real passenger data.

```
QUESTION_SAMPLE_REQUEST_INCLUDED=YES
```
