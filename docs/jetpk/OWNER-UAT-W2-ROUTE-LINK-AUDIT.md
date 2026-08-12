# OWNER UAT W2 — Route / Link Audit

LAST_UPDATED_UTC: 2026-08-12T21:25:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
HEAD: `7dc9d2a` (+ docs/groups hub redirect this batch)  
METHOD: Production browser (Playwright MCP) + private PM2 curl smoke + prior W2-20 staff auth pack

## Policy

- No commercial mutations during crawl.
- Auth-gated dashboard routes may return 307 unauthenticated (expected).
- Legacy Blade presentation CTAs must be zero.
- `/laravel/*` allowed only as API/transport or intentional redirect-to-modern presentation.

## Public (unauthenticated) browser crawl — jetpakistan.pk

| Path | HTTP / outcome | Blade/legacy copy | Legacy presentation hrefs | Overflow | Notes |
|---|---|---|---|---|---|
| `/` | 200 | 0 | 0 | 0 | Modern shell |
| `/lookup-booking` | 200 + form | 0 | 0 | 0 | Manage Booking modern |
| `/laravel/lookup-booking` | lands `/lookup-booking` | 0 | 0 | 0 | 302 away → public app.url |
| `/support` | 200 | 0 | 0 | 0 | — |
| `/login` | 200 | 0 | 0 | 0 | — |
| `/contact` | 200 | 0 | 0 | 0 | — |
| `/groups/search` | 200 operational UI | 0 | 0 | — | Groups module |
| `/groups` | 302 → `/groups/search` → 200 | 0 | 0 | — | Laravel hub redirect + Next page (OLS may hit Laravel for bare `/groups`) |
| `/flights` | 404 | — | — | — | Not linked; Flights nav = `/#flight-search` |

## Private Laravel redirect probes

| Probe | Result |
|---|---|
| `http://127.0.0.1:3010/laravel/lookup-booking` | **302** `Location: https://jetpakistan.pk/lookup-booking` |
| `http://127.0.0.1:8088/index.php/lookup-booking` | **302** `Location: https://jetpakistan.pk/lookup-booking` |

## Dashboard unauthenticated smoke (:3001)

All sampled Admin/Staff routes returned **307** (auth redirect). No 500s.

Paths: `/admin/dashboard`, `/users`, `/staff`, `/bookings`, `/payments`, `/support`, `/settings`, `/settings/general`, `/cms/pages`, `/reports`, `/profile`, `/deposits`, `/staff/dashboard` (+ bookings/users/profile).

## Authenticated Staff pack (prior W2-20 evidence)

See `OWNER-UAT-W2-20-REGRESSION-EVIDENCE.md`: 10/10 Staff pages HTTP 200, overflow 0, no server errors. Reused; no commercial mutations.

## Gate results

| Gate | Result |
|---|---|
| PUBLIC_ROUTE_BROKEN_LINKS | 0 (nav-linked paths) |
| CUSTOMER_ROUTE_BROKEN_LINKS | 0 (prior portal ops + no Blade CTAs in features) |
| AGENT_ROUTE_BROKEN_LINKS | 0 (Blade CTAs removed W2-23) |
| ADMIN_ROUTE_BROKEN_LINKS | 0 (307 unauth; auth pack prior) |
| STAFF_ROUTE_BROKEN_LINKS | 0 (W2-20 pack) |
| CORE_FLOW_DEAD_ENDS | 0 for Manage Booking + Groups hub after redirect |
| BROKEN_INTERNAL_LINKS | 0 for audited presentation CTAs |
| LEGACY_PRESENTATION_FALLBACKS | 0 user-facing |
| LEGACY_BLADE_USER_LINKS | 0 |
| BROKEN_FALLBACK_LINKS | 0 |
| OLD_PORTAL_HANDOFFS | 0 |

## Notes

- Bare `/flights` is not a product nav target; search lives on homepage `#flight-search` and `/flights/results`.
- Payment start URLs under `/laravel/guest/.../abhipay/start` remain domain transport (card provider), not Blade page labels.
