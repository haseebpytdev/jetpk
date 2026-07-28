# JetPakistan Route Ownership

## `frontend/` — public and customer-facing Next.js app

Owns:

- public marketing website
- authentication presentation layer
- flight search and results UI
- booking flow presentation
- customer dashboard
- shared Agent and Agent Staff dashboard UI

Does **not** own authoritative business logic, supplier calls, fare validity, booking state, payment state, or authorization decisions.

## `dashboard/` — internal operations Next.js app

Owns:

- Admin dashboard
- Platform Staff dashboard

## Laravel — authoritative backend

Owns:

- authentication and session
- CSRF protection
- RBAC and agency scoping
- supplier APIs and fare logic
- booking, PNR, payment, ticketing
- queues, email, and webhooks
- all write operations and security boundaries

## Agent and Agent Staff rule

Agent and Agent Staff do **not** receive separate dashboards.

Both roles use the same `/agent` route family and the same frontend components inside `frontend/`. Existing Laravel RBAC determines which modules, records, and actions each role may access.

Frontend visibility is a presentation concern only. Laravel authorization remains the security boundary.

## Planned frontend route families

| Route family | Owner | Notes |
| --- | --- | --- |
| `/` | `frontend/` | Public marketing and homepage |
| `/about`, `/support`, `/legal/*` | `frontend/` | CMS-driven public content |
| `/flights`, `/hotels`, `/groups`, `/offers` | `frontend/` | Search and discovery presentation |
| `/booking/*` | `frontend/` | Checkout and confirmation presentation |
| `/account/*` | `frontend/` | Customer dashboard |
| `/agent/*` | `frontend/` | Shared Agent + Agent Staff dashboard |
| `/admin/*` | `dashboard/` | Admin operations |
| `/staff/*` or portal-specific admin paths | `dashboard/` | Platform Staff operations |

Laravel continues to serve legacy Blade routes until each area is intentionally cut over.
