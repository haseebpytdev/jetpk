# JP-OPS-01 Full-Stack Route Inventory (JP-OPS-01A reconciled)

**JSON:** [`JP-OPS-01-FULL-STACK-ROUTE-INVENTORY.json`](JP-OPS-01-FULL-STACK-ROUTE-INVENTORY.json)

## Counting models

| Model | Denominator |
|-------|------------:|
| Laravel route records | 584 |
| Next page records | 96 |
| Unique operational contracts | 31 |

## Exact page record counts

| Bucket | Count | Notes |
|--------|------:|-------|
| Public/B2C Next pages | 39 | Excludes customer, agent portal, agent auth |
| Customer Next pages | 11 | Includes `customer/page.tsx` redirect |
| Agent portal Next pages | 15 | Under `frontend/app/agent/` only |
| Agent auth/registration | 2 | `(auth)/agent/register`, `(auth)/agent/register/submitted` |
| Dashboard Next pages | 29 | Under `dashboard/app/[portal]/dashboard/` |
| Frontend total | 67 | 39+11+15+2 |
| Next total | 96 | 67+29 |

### Agent registration placement

| Path | Page file | Bucket |
|------|-----------|--------|
| `/agent/register` | `frontend/app/(auth)/agent/register/page.tsx` | agent_auth_registration |
| `/agent/register/submitted` | `frontend/app/(auth)/agent/register/submitted/page.tsx` | agent_auth_registration |

Not counted in Agent portal (15) or public/B2C (39).

## Laravel route records (584)

| ota:audit-routes bucket | Count |
|-------------------------|------:|
| Public | 79 |
| Auth | 41 |
| Admin | 242 |
| Staff | 43 |
| Dev CP | 43 |
| API | 51 |
| Health | 1 |
| Customer/Agent/other (remainder) | 84 |
| **Total** | **584** |

## Classification — Laravel (sum 584)

| Classification | Count | Notes |
|----------------|------:|-------|
| OPERATIONAL_CONNECTED | 244 | Public, customer, agent, API, auth |
| PARTIALLY_CONNECTED | 285 | Admin/Staff Blade operational; Next mutation pending |
| TEST_ONLY | 55 | dev/cp, UI preview, client slug preview |
| All others | 0 | |

## Classification — Next pages (sum 96)

| Classification | Count |
|----------------|------:|
| OPERATIONAL_CONNECTED | 66 |
| PARTIALLY_CONNECTED | 28 |
| INTENTIONALLY_UNAVAILABLE | 1 |
| TEST_ONLY | 1 |

## Blade vs Next dashboard

| Metric | Count |
|--------|------:|
| Blade-operational mutations (admin+staff) | 159 |
| Next-dashboard connected reads | 38 |
| Next-dashboard connected mutations | 0 |
| Next-dashboard missing mutations | 157 |

Admin/Staff Blade routes include `current_ui_owner: blade_admin_staff`, `blade_binding_status: operational`, `next_binding_status: mutation_missing`.

## Unique operational contracts (31)

See JSON `operational_contracts` array. Classifications sum to 31.

## Public B2C journey (CODE_CONNECTED_AND_LOCALLY_VERIFIED)

Home → Search → Results → Return Options → Fare → Passengers → Review → Payment → Confirmation → Lookup → Invoice — all Next pages present with Laravel contracts OC-001 through OC-010.

Return pairing: supplier-validated (Sabre); not universal (GAP-011).

Seats: INTENTIONALLY_UNAVAILABLE (`seat_map_available=false`).
