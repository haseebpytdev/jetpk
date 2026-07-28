# One API booking

- `OneApiBookingService` — paid book with fulfillment; on-hold without fulfillment
- `OneApiSupplierBookingAdapter` + `OneApiBookingRouterService`
- Ambiguous SOAP outcomes set attempt `ambiguous` and **block automatic retry** until reconcile
- Tickets on paid book: `OneApiSupplierTicketingAdapter` returns `not_required`
