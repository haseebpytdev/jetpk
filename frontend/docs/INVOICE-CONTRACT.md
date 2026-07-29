# Invoice contract (JP-FE-09 / JP-FE-10)

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
- `documents` — safe portal document rows (JP-FE-10)

## PDF

`pdf_available` true only when a generated invoice document exists with a valid download path.

- Authenticated customers: `/customer/documents/{id}/download`
- Session checkout without auth: PDF unavailable (print view only)
- No fake `/booking/invoice/download` path

## Ownership

Cross-booking access rejected when session booking id does not match.

## Next.js presentation

`InvoicePage` renders itinerary summary, accessible line-item table, pricing breakdown, print control, and honest PDF-unavailable state.
