# Phase 2 — Authoritative passenger identity flow

## Persistence (`booking_passengers`)

Supported identity-related columns (migrations / model fillable):

```
PASSENGER_IDENTITY_SOURCE_FIELDS=
title,first_name,last_name,date_of_birth,nationality,gender,
passport_number,passport_issuing_country,passport_expiry_date,passport_issue_date,
document_type,national_id_number,country_of_residence,place_of_birth
```

No `national_id_expiry` column exists in current schema — do not invent one.

## AirBlue payload builder (current)

`AirBluePassengerPayloadBuilder::buildPassengersFromBooking` maps:

```
AIRBLUE_BUILDER_CURRENT_ID_FIELDS=
nationality,
document_number ← passport_number ONLY,
document_issuing_country ← passport_issuing_country,
document_expiry ← passport_expiry_date
```

`national_id_number` and UI `document_type` are **not** mapped into the AirBlue passenger DTO.

## AirBook XML

`AirBlueOtaXmlBuilder::travelerDocumentXml`:

- Uses `document_number` / `passport_number`
- Defaults `document_type` attribute to Zapways DocType `'2'` when present
- Returns **empty string** (omits `ota:Document`) when DocID blank

## Why national_id was ignored

```
ROOT_CAUSE_CODE_PATH=
AirBluePassengerPayloadBuilder::buildPassengersFromBooking
→ document_number = trim(passport_number) only
→ national_id_number never copied
→ travelerDocumentXml sees blank DocID
→ omits ota:Document from AirBook
```

```
NATIONAL_ID_PERSISTED=YES
NATIONAL_ID_AVAILABLE_AT_AIRBOOK_BUILD=YES
```

(Authoritative DB value exists and is loaded on the BookingPassenger model at AirBook build time; builder simply does not read it.)
