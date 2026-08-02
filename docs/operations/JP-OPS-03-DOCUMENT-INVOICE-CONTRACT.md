# JP-OPS-03 Document & Invoice Contract

## Invoices

- List/detail via `CustomerPortalInvoicesPresenter`
- `pdf_available` + `download_url` only when file exists on disk
- Missing PDF: honest unavailable copy in Next UI

## Downloads

- `GET /customer/documents/{bookingDocument}/download`
- `BookingDocumentPolicy::view` + `customer_id` re-check
- Missing file → 404

Frontend uses direct Laravel GET links for downloads (session cookie), not Next allowlist navigation.
