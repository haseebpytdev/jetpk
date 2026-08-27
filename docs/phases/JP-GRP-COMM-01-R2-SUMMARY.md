# JP-GRP-COMM-01-R2 — Supplier release reconciliation + production safety

## Status

**FINAL_STATUS=BLOCKED_ON_OWNER_MANUAL_CANCEL** — engineering hardening complete and committed; no live mutation this R2; deploy deferred until reservation `60175` is confirmed cancelled and seats restored to 5.

## Branch / SHAs

| Field | Value |
|---|---|
| BRANCH | `phase/jp-grp-commercial-seat-sync-01` |
| DOCS_HEAD_BEFORE | `c4101c99f5988af03515b305b1cbab3ccb5cb5f8` |
| SUPERSEDED_ENGINEERING_SHA | `3062567bdc45d9ac9a06bac2a850671cc832584d` (do not deploy as-is) |
| DEPLOYED_RUNTIME_SHA (current prod) | `460cdae0441d0e07c563e636280c0e552481ac92` |
| FINAL_ENGINEERING_SHA | _(set after commit)_ |

## Objective

Harden Al-Haider reservation/release so local seat availability cannot diverge from supplier holds; separate create/cancel gates; fail-closed passenger validation; admin reconciliation visibility.

## Included

- Supplier-first release atomicity (`held_seats` only after cancel success)
- `ALHAIDER_CANCEL_ENABLED` independent of `ALHAIDER_BOOKING_ENABLED`
- Official payload builder without production synthetic passenger/contact fallbacks
- Seat/passenger/infant parity validation
- `SupplierReleaseFailed` admin panel + manual reconcile / retry endpoints
- Regression test matrix (mocked client only)

## Excluded

- Live Al-Haider POST create / PATCH cancel
- Payment / ticketing
- Token generation
- Deploy of `3062567b` or the new SHA until owner confirms manual cancel of `60175`
- Owner UI UAT supplier reservation

## Root causes addressed

1. `releaseUnpaidBooking()` decremented `held_seats` before supplier cancel succeeded → local availability increased while supplier reservation stayed ACTIVE.
2. Cancel used `ALHAIDER_BOOKING_ENABLED`, so create-off also blocked cleanup.
3. Payload builder invented Guest/Passenger/QA passport/DOB/dummy contacts.
4. No durable admin reconciliation panel for `SupplierReleaseFailed`.

## Files changed (engineering)

- `app/Services/GroupTicketing/GroupReservationService.php`
- `app/Services/Suppliers/AlHaider/AlHaiderClient.php`
- `app/Services/Suppliers/AlHaider/AlHaiderGroupBookingPayloadBuilder.php`
- `app/Services/Suppliers/AlHaider/AlHaiderGroupBookingPayloadException.php` (new)
- `app/Models/GroupBooking.php`
- `app/Http/Controllers/Admin/GroupBookingManagementController.php`
- `app/Http/Requests/Frontend/GroupTicketingPassengersRequest.php`
- `app/Support/GroupTicketing/GroupBookingListPresenter.php`
- `resources/views/dashboard/admin/group-bookings/show.blade.php`
- `routes/admin.php`
- `config/suppliers.php`
- `.env.example`
- `tests/Unit/Suppliers/AlHaider/AlHaiderGroupBookingPayloadBuilderTest.php`
- `tests/Feature/GroupTicketing/GroupReservationSupplierReleaseAtomicityTest.php` (new)
- `docs/phases/JP-COMBINED-01-GROUP-SEAT-AUTHORITY.md`
- `docs/phases/JP-GRP-COMM-01-SUMMARY.md`
- `docs/phases/JP-GRP-COMM-01-R2-SUMMARY.md`
- `docs/evidence/jp-grp-comm-01-r2/*`

## Database / routes

- No migrations
- Admin routes added: `retry-supplier-release`, `reconcile-manual-supplier-cancel`

## Tests

```
php artisan test --filter="GroupReservationSupplierReleaseAtomicityTest|AlHaiderGroupBookingPayloadBuilderTest"
→ 17 passed, 73 assertions
```

## Gates (production posture)

| Gate | Value |
|---|---|
| `ALHAIDER_BOOKING_ENABLED` | **false** (keep OFF) |
| `ALHAIDER_CANCEL_ENABLED` | **false** until Al-Haider cancel permission/operator policy resolved |
| `ALHAIDER_TOKEN_GENERATION_ENABLED` | false |

## Owner hard stop

1. Confirm manual cancel of supplier reservation **60175** (group 3348, 1 seat).
2. Confirm supplier seats for group 3348 = **5**.
3. Authorize protected deploy of `FINAL_ENGINEERING_SHA` with create gate OFF.
4. After deploy: reconcile local booking (`supplier_reservation_id=60175`) via admin manual-cancel reconcile (no PATCH).
5. Owner UI UAT may proceed without another supplier reservation unless separately authorized.

## Live mutation this R2

| Metric | Value |
|---|---|
| ALHAIDER_CREATE_CALLS_THIS_R2 | 0 |
| ALHAIDER_CANCEL_API_CALLS_THIS_R2 | 0 |
| TOKEN_GENERATION_CALLS | 0 |
| PAYMENT_EXECUTED | NO |
| TICKET_ISSUED | NO |

## Evidence

`docs/evidence/jp-grp-comm-01-r2/`
