# Invoice contract (JP-FE-09)

## Endpoint

`GET /booking/invoice?format=json` — requires `ota_public_booking_id` session.

## Fields

Authoritative from Laravel booking + `CheckoutFareBreakdownPresenter`:

- `invoice_number` (when generated document exists)
- `booking_reference`
- `issue_date`
- `customer` (contact summary)
- `itinerary_summary`
- `passenger_count`
- `line_items` / `pricing`
- `payment_status`, `booking_status`
- `company` (JetPakistan branding via `client_branding` + `PublicAgencyContactResolver`)

## PDF

`pdf_available` true only when a generated invoice document exists in `booking_documents`. No fake PDF links.

## Ownership

Cross-booking access rejected when session booking id does not match.
