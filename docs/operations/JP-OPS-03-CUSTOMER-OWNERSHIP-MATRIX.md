# JP-OPS-03 Customer Ownership Matrix

| Resource | Scope | Enforcement |
|----------|-------|-------------|
| Bookings | `customer_id = auth user` | Scoped queries + `BookingPolicy::view` + explicit abort |
| Dashboard metrics | same scope | `CustomerPortalDashboardPresenter` |
| Invoices/documents | booking.customer_id | `invoiceQuery` + `BookingDocumentPolicy` |
| Cancellation | booking.customer_id | `BookingCancellationPolicy::request` + duplicate check |
| Saved travelers | `user_id = auth user` | `SavedTravelerPolicy` |
| Support tickets | `created_by_user_id` | `SupportTicketPolicy` |
| Profile | auth user only | `ProfileUpdateRequest`; no role change |

Cross-customer access returns 403/404. No `customer_id`/`user_id` from request payload is trusted.
