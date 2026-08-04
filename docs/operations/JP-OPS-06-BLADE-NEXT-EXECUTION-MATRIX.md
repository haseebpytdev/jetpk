# JP-OPS-06 Blade Next Execution Matrix

| Classification | Count |
|----------------|------:|
| CONNECTED (payment/deposit + execution) | **12** |
| BACKEND_WITHOUT_NEXT_BINDING (cancel/refund review) | **8** |
| DEFERRED / BLADE_FALLBACK_RETAINED | **139** |
| **Total** | **159** |

Six execution mutations closed in JP-OPS-06 (CONNECTED):

- `admin|staff.bookings.cancellations.process`
- `admin|staff.bookings.refunds.mark-paid`
- `admin|staff.bookings.issue-ticket`

Blade redirect behavior preserved for non-JSON requests.
