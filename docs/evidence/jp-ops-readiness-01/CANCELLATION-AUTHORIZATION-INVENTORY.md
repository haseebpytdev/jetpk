# CANCELLATION-AUTHORIZATION-INVENTORY — OPS-DEF-0004

**Date:** 2026-10-15  
**Base SHA:** `6a09855411f2063d1d0db556ee6f1f8689a337b1`

| ACTOR | ROUTE | POLICY | PERMISSION | TENANT_SCOPE | BOOKING_SCOPE | MUTATION | UI_CAPABILITY_SOURCE |
|-------|-------|--------|------------|--------------|---------------|----------|----------------------|
| Agent owner | `POST agent.bookings.cancellations.store` | `BookingCancellationPolicy::request` | Owner implicit (all agent perms) | `current_agency_id` | `booking.agent_id == owner agent` | request only | Blade `@can('request')`; Next actions from presenter |
| Agent staff | same | same | **BookingsView + CancellationsRequest** (after 0004) | same | `booking.agent_id == employer agent` | request only | Presenter Gate `request` (fixed); Blade `@can('request')` |
| Customer | `POST customer.bookings.cancellations.store` | same | n/a (customer own) | n/a | `booking.customer_id` | request only | Customer presenter / Blade `canRequestCancellation` |
| Guest | `POST guest.bookings.cancellations.store` | guest token gate (controller) | token valid | booking agency | token↔booking | request only | Guest presenter capabilities |
| Platform staff | `POST staff.bookings.cancellations.store` | same | `StaffPermission::CancellationsCreate` | agency match | booking in agency | request only | Dashboard operational presenter Gate `request` |
| Platform admin | admin/staff cancel routes | same | admin bypass | any | any | request / approve / reject / process | Admin detail body |
| Platform staff approve | `PATCH …cancellations.approve` | `approve` | `CancellationsApprove` | agency | request agency | approve | unchanged |
| Platform staff process | `PATCH …cancellations.process` | `process` | `CancellationsProcess` | agency | request agency | process (+ optional supplier) | unchanged |

## Vulnerable path (pre-fix)

1. Agent staff with only `agent.bookings.view`
2. Policy required only BookingsView then ownership
3. Presenter used `Gate::allows('view', $booking)` for `request_cancellation`

## Fixed path

1. Staff require `agent.bookings.view` **and** `agent.cancellations.request`
2. Presenter uses `Gate::allows('request', [BookingCancellationRequest::class, $booking])`
3. Approve/reject/process and supplier execution gates unchanged
