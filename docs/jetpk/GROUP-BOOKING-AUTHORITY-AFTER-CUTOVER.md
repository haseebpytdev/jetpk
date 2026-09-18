# Group booking authority after CORRECTION-08 OLS cutover

## UI ownership (GET/HEAD)

| Route | Owner |
|-------|--------|
| `/groups/{inventory}/passengers` | Public Next (OLS proxy) |
| `/groups/booking/{ref}/review` | Public Next (OLS proxy) |
| `/groups/booking/{ref}/payment` | Public Next (OLS proxy) |
| `/groups/booking/{ref}/confirmation` | Public Next (OLS proxy) |

Note: `/groups/booking/{ref}/passengers` is **not** a Laravel named route.
Canonical passengers path remains `/groups/{inventory}/passengers`.

## Business authority (unchanged — Laravel)

| Concern | Authority |
|---------|-----------|
| Booking authorization / session gates | Laravel |
| Hold ownership / seat inventory | Laravel |
| Payment submission (POST) | Laravel |
| Release / expiry | Laravel |
| Supplier operations | Laravel |
| JSON APIs under `/laravel/*` | Laravel |

Next renders shells and calls same-origin Laravel APIs. No duplicate Group
business rules in Next.

See also: `docs/jetpk/OLS-GROUP-BOOKING-NEXT-PROXY.md`
