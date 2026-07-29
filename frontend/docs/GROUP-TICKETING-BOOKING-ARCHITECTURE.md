# Group Ticketing Booking Architecture (JP-FE-07)

## Authority

Laravel owns inventory, holds, booking references, manual payment state, and repeat-offender locks. Next.js owns presentation only via `/laravel` proxy with session cookies and CSRF.

## Laravel routes (additive JSON)

| Route | Method | JSON |
|-------|--------|------|
| `/groups/search/data` | GET | Search facets + cards |
| `/groups/search/facets` | GET | Authoritative search sector/category options (JP-FE-07A) |
| `/groups/search/results` | GET | Cards + legacy HTML |
| `/groups/facets` | GET | Legacy facets (Blade/homepage) |
| `/groups/package/{inventory}` | GET | Package details (`Accept: application/json`) |
| `/groups/{inventory}/passengers` | GET/POST | Passenger context / draft booking |
| `/groups/booking/{ref}/review` | GET/POST | Review / confirm hold |
| `/groups/booking/{ref}/payment` | GET/POST | Manual payment |
| `/groups/booking/{ref}/confirmation` | GET | Confirmation |
| `/groups/booking/{ref}/status` | GET | Hold status polling |

Route binding resolves `{inventory}` by `public_id`, `supplier_package_id`, or numeric id. `{groupBooking}` resolves by `reference` or numeric id.

## Search facets (JP-FE-07A)

`GET /groups/search/facets` returns `GroupInventoryFacetService::forPublicSearch()`:

- `sectors[]` — `{ value, label }` from active bookable inventory (`is_active`, available seats > 0)
- `categories[]` — `{ value: slug, label: name }` inventory-derived active categories only
- `date_bounds` — min/max `departure_date` from active inventory when dates exist; otherwise `null`

Next.js `useGroupSearchFacets` loads facets once (in-flight dedupe). No fixture fallback in runtime. Loading disables controls; empty/error blocks submit with retry.

## Search contract

Query fields: `sector`, `date_from`, `category` (omit when All). No passenger/cabin/origin fields at search.

## Hold start point

- **Passenger submit:** creates draft (`pending_passenger_details`), no `expires_at`.
- **Review confirm:** creates reservation (`reserved_awaiting_payment`), sets `expires_at` = now + `config('ota.group_booking_hold_minutes', 25)`.

## Manual payment only

Methods: `bank_transfer`, `office`, `cash`. Optional `payment_proof` upload. No card/AbhiPay/wallet.

## Repeat-offender

Three unpaid timeout releases → lock (`GroupBookingRestrictionService::BLOCK_THRESHOLD`). Laravel-only authority.

## Seat selection

No authoritative seat-map contract. Future boundary: `frontend/features/seat-selection/`.

## Next.js routes

- `/groups/search`
- `/groups/[packageId]`
- `/groups/[packageId]/passengers`
- `/groups/booking/[bookingRef]/review`
- `/groups/booking/[bookingRef]/payment`
- `/groups/booking/[bookingRef]/confirmation`

## Feature layout

`frontend/features/group-ticketing/` — services, components, types, hooks.
