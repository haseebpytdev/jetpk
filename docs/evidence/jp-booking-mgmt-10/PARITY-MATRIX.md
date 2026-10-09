# JP-BOOKING-MGMT-10 — Blade vs Next parity matrix

| Area | Legacy (`admin/bookings/show`) | Next (`booking-detail-page-content`) | Status |
|------|-------------------------------|--------------------------------------|--------|
| Overview / status badges | Yes | Yes | PASS |
| Itinerary | Yes | Yes | PASS |
| Passengers | Yes | Yes (honest empty) | PASS |
| Fare | Yes | Yes | PASS |
| Payment summary | Yes | Yes | PASS |
| Payment history + record | Yes | Yes (form + history) | PASS |
| PNR / supplier | Yes | Yes | PASS |
| Ticketing readiness | Yes | Yes | PASS |
| Documents + download | Yes | Yes | PASS |
| Cancellation requests | Yes | Yes (typed form) | PASS |
| Refund requests | Yes | Yes (amount/method form) | PASS |
| Communication log | Yes | Yes (send gated) | GATED |
| Internal notes | Yes | Yes | PASS |
| Staff assignment | Admin only | Admin only | PASS |
| Audit / activity | Yes | Timeline subset | PASS |
| Supplier sync / ticket issue | Yes | Not on Next page (intentional) | OUT_OF_SCOPE |

**API:** `DashboardBookingDetailResource` + `DashboardBookingOperationalPresenter` supply authoritative read-model fields for the Next UI.
