# Passenger Document Requirements Contract (JP-FE-08)

Requirements are authored in Laravel (`StoreBookingPassengersRequest`, `InternationalRouteDetector`) and exposed via `StandardBookingJsonPresenter`.

## Document type

- International routes: passport only (`document_type` forced to `passport`)
- PK domestic eligible routes: `passport` or `national_id`

## Passport fields (when required)

| Field | Laravel key |
|-------|-------------|
| Passport number | `passengers.*.passport_number` |
| Issuing country | `passengers.*.passport_issuing_country` |
| Expiry | `passengers.*.passport_expiry_date` (after today) |
| Issue date | `passengers.*.passport_issue_date` (today or past) |
| Nationality | `passengers.*.nationality` |

No blanket six-month validity rule is enforced unless Laravel adds it.

## National ID (PK domestic only)

| Field | Laravel key |
|-------|-------------|
| CNIC/NICOP | `passengers.*.national_id_number` |

## Next.js rendering

Fields shown based on `document_requirements` from JSON. No client-side rules engine.
