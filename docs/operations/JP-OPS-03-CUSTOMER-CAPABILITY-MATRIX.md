# JP-OPS-03 Customer Capability Matrix

| Surface | Read | Mutate | State |
|---------|------|--------|-------|
| Dashboard | `GET /customer?format=json` | — | CONNECTED |
| Bookings list | `GET /customer/bookings?format=json` | — | CONNECTED |
| Booking detail | `GET /customer/bookings/{ref}?format=json` | — | CONNECTED |
| Cancellation request | capabilities on detail | `POST /customer/bookings/{id}/cancellations?format=json` | CONNECTED |
| Refund request | read-only on detail | — | INTENTIONALLY_UNAVAILABLE |
| Payments | `GET /customer/payments?format=json` | — | CONNECTED |
| Invoices list | `GET /customer/invoices?format=json` | — | CONNECTED |
| Invoice detail | `GET /customer/invoices/{ref}?format=json` | — | CONNECTED |
| Documents | capabilities.download_urls | `GET /customer/documents/{id}/download` | CONNECTED |
| Saved travelers | `GET /customer/travelers?format=json` | POST/PATCH/DELETE | CONNECTED |
| Profile | `GET /customer/profile?format=json` | `PATCH /profile` | CONNECTED |
| Security | — | `PUT /password` | CONNECTED |
| Support | tickets JSON | create/reply/close | CONNECTED |
| Notifications | stub JSON | mark-read 501 | INTENTIONALLY_UNAVAILABLE |

Explicit `capabilities` on booking detail drive UI actions; frontend does not infer from status alone.
