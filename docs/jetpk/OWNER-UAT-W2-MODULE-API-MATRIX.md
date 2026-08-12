# OWNER UAT W2-24 — Module / API Operational Matrix

LAST_UPDATED_UTC: 2026-08-12T21:25:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
REFERENCE: JetPakistan `ota-jetpk` + read-only mature OTA `C:\Users\khadi\ota` (not modified)

## Operational definition

A module is operational when route + modern presentation + authoritative data source + RBAC + error/empty/loading states pass, and **no legacy Blade presentation handoff** is required for the happy path.

Write/mutation commercial paths may be proven by tests when production money QA is prohibited.

## Matrix (Wave-2 closure snapshot)

| MODULE | ACTORS | NEXT | LARAVEL DOMAIN | READ API | WRITE API | RBAC | EMPTY/ERR/LOAD | LEGACY FALLBACK | PROD ROUTE | TESTS | STATUS | GAP / ACTION |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Public homepage / search | Public | `/` | Flight search | `/laravel/flights/results/*` | Search handoff | Public | Present | None | 200 browser | homepage specs | OPERATIONAL | — |
| Groups search | Public | `/groups/search` | Group ticketing | `/laravel/groups/*` | Booking path (no prod QA) | Public | Present | None | 200 browser | group-ticketing.spec | OPERATIONAL | Hub `/groups` → search |
| Manage Booking / Lookup | Public | `/lookup-booking` | GuestBookingAccessService | POST `/laravel/lookup-booking` | Token create | Public+Turnstile | Modern recovery | Retired W2-23 | 200 + redirect proof | lookup turnstile | OPERATIONAL | — |
| Guest booking detail | Public | `/guest/bookings/...` | GuestBookingDetailPresenter | `?format=json` | Proof/cancel JSON | Token | Present | HTML→Next | Deployed | guest-booking-detail | OPERATIONAL | Card pay = Laravel start URL (modern label) |
| Auth login/OTP/password | All | `/login` etc. | Auth controllers | session/csrf | login POST | Actor | Present | Transport only | 200 | auth suites | OPERATIONAL | OTP temp off Owner-UAT |
| Customer dashboard | Customer | `/customer/*` | Customer APIs | `format=json` | Scoped | Customer | Present | None | Prior ops | portal routes | OPERATIONAL | — |
| Agent dashboard | Agent | `/agent/*` | Agent APIs | `format=json` | Scoped | Agent | Present | CTAs removed | Prior ops | jp-ops-04 | OPERATIONAL | — |
| Admin/Staff dashboard | Admin/Staff | `/admin|/staff/dashboard/*` | Dashboard API | dashboard JSON | Limited | RBAC | Present | None | Staff pack 10/10 | W2-20 | OPERATIONAL | — |
| Users directory | Admin/Staff | `/users` | DashboardUsersReadService | users JSON | RO | users.read | Compact filters | None | Deployed | scope tests | OPERATIONAL | W2-03/04 |
| Staff directory | Admin/Staff | `/staff` | scope=staff | users JSON | RO | staff | Compact | None | Deployed | scope tests | OPERATIONAL | W2-03 |
| Bookings ops | Admin/Staff | `/bookings` | Booking resources | bookings JSON | Contact PATCH policy | bookings | Compact | None | Deployed | amendment policy | OPERATIONAL | W2-05/06 |
| Payments | Admin/Staff | `/payments` | Payments | payments JSON | RO money safety | payments | Compact | None | Deployed | filters | OPERATIONAL | — |
| Tickets/Documents | Admin/Staff | `/tickets` | Tickets | tickets JSON | RO | tickets | Compact | None | Deployed | filters | OPERATIONAL | — |
| Reports | Admin/Staff | `/reports` | Reports | reports JSON | Export allowed | reports | Compact | Preview copy fixed | Deployed | filters | OPERATIONAL | Live-mode |
| Settings | Admin/Staff | `/settings/*` | Settings | settings JSON | RO metadata | settings | Live readiness | None | Deployed | settings sync | OPERATIONAL | OWNER_INPUT_REQUIRED badges |
| CMS Pages | Admin/Staff | `/cms/pages` | CmsPageController | pages | create/edit/archive | cms | Present | None | Deployed | cms baseline | OPERATIONAL | Baseline only |
| CMS Banners/Notices/Assets | Admin/Staff | `/cms/*` | Read listings | GET | No write domain | cms | RO labels | None | Deployed | — | PARTIAL | Documented; no migration |
| Support | Admin/Staff | `/support` | Support | tickets JSON | replies | support | Paginate 10 | None | Deployed | W2-12 | OPERATIONAL | — |
| Deposits | Admin/Staff | `/deposits` | Deposits | RO + guard | No prod money QA | deposits | Guard UI | None | Deployed | — | PARTIAL | Architecture; NO_PROD_MONEY |
| Markup | Admin/Staff | markup nav | Markup settings | RO | No prod mutation | markup | Discoverable | None | Deployed | — | PARTIAL | NO_PROD_MUTATION |
| Notifications / failed | Admin/Staff | notifications | Notification ops | list + classify | — | notif | Explained | None | Classified | W2-16 | OPERATIONAL | QA SMTP |
| Profile | Admin/Staff | `/profile` | Session/profile | profile JSON | safe profile | self | Present | None | Deployed | W2-08 | OPERATIONAL | — |
| Public shell typography | Public | all | — | — | — | — | — | — | Prod accept | W2-21/22 | OPERATIONAL | Computed fonts PASS |

## New APIs this Wave-2 batch (W2-23/24)

**None.** Reused existing lookup POST + guest JSON; presentation redirects only.

## Existing endpoints reused

- POST `/laravel/lookup-booking`
- Guest booking `?format=json` + mutation URLs
- Dashboard users/staff/bookings/payments/reports/settings/cms/support JSON
- Agent `format=json` module endpoints

## Gates

| Gate | Result |
|---|---|
| OWNER_W2_MODULE_API_MATRIX | **PASS** (documented) |
| OWNER_W2_CORE_MODULES_OPERATIONAL | **PASS** for core; PARTIAL CMS non-pages + deposits/markup by safety disposition |
| OWNER_W2_API_CONTRACTS | **PASS** for lookup/guest + dashboard read paths used |
| OWNER_W2_CMS_LEGACY_HANDOFFS | **0** |
| OWNER_W2_CORE_FLOW_DEAD_ENDS | **0** (Manage Booking; Groups hub redirect) |
| OWNER_W2_UNHANDLED_UI_API_ERRORS | **0** for audited recovery paths (no Blade handoff) |
