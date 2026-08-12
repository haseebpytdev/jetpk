# OWNER UAT W2-24 — Module / API Operational Matrix

LAST_UPDATED_UTC: 2026-08-12T21:12:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
REFERENCE: JetPakistan `ota-jetpk` + read-only mature OTA `C:\Users\khadi\ota` (not modified)

## Operational definition

A module is operational when route + modern presentation + authoritative data source + RBAC + error/empty/loading states pass, and **no legacy Blade presentation handoff** is required for the happy path.

Write/mutation commercial paths may be proven by tests when production money QA is prohibited.

## Matrix (Wave-2 closure snapshot)

| MODULE | ACTORS | NEXT | LARAVEL DOMAIN | READ API | WRITE API | LEGACY FALLBACK | STATUS | GAP / ACTION |
|---|---|---|---|---|---|---|---|---|
| Public homepage / search | Public | `/` | Flight search services | `/laravel/flights/results/*` | Search handoff | None user-facing | OPERATIONAL | — |
| Manage Booking / Lookup | Public | `/lookup-booking` | GuestBookingAccessService | POST `/laravel/lookup-booking` | Token create | Retired (W2-23) | OPERATIONAL | Deploy + prod verify |
| Guest booking detail | Public | `/guest/bookings/...` | GuestBookingDetailPresenter | `?format=json` | Proof/cancel JSON | HTML→Next redirect | OPERATIONAL | Card pay still Laravel start URL (labeled modern) |
| Auth login/OTP/password | All | `/login` etc. | Auth controllers | session/csrf JSON | login POST | `/laravel/*` transport only | OPERATIONAL | OTP temp disabled Owner-UAT |
| Customer dashboard | Customer | `/customer/*` | Customer APIs | `format=json` | Scoped mutations | None in UI | OPERATIONAL | — |
| Agent dashboard | Agent | `/agent/*` | Agent APIs | `format=json` | Scoped mutations | Traveler/finance Blade CTAs removed | OPERATIONAL | — |
| Admin/Staff dashboard | Admin/Staff | `/admin|/staff/dashboard/*` | Dashboard API | dashboard JSON | Limited | None | OPERATIONAL | — |
| Users directory | Admin/Staff | `/users` | DashboardUsersReadService | users JSON | N/A RO | None | OPERATIONAL | W2-03/04 |
| Staff directory | Admin/Staff | `/staff` | scope=staff | users JSON | N/A RO | None | OPERATIONAL | W2-03 |
| Bookings ops | Admin/Staff | `/bookings` | Booking resources | bookings JSON | Contact PATCH policy | None | OPERATIONAL | W2-05/06 |
| Payments | Admin/Staff | `/payments` | Payments | payments JSON | RO money safety | None | OPERATIONAL | Compact filters |
| Tickets/Documents | Admin/Staff | `/tickets` | Tickets | tickets JSON | RO | None | OPERATIONAL | Compact filters |
| Reports | Admin/Staff | `/reports` | Reports | reports JSON | Export where allowed | Preview copy fixed | OPERATIONAL | Live-mode |
| Settings | Admin/Staff | `/settings/*` | Settings resources | settings JSON | RO metadata | None | OPERATIONAL | OWNER_INPUT_REQUIRED |
| CMS Pages | Admin/Staff | `/cms/pages` | CmsPageController JSON | pages | create/edit/archive | None | OPERATIONAL | Baseline only |
| CMS Banners/Notices/Assets | Admin/Staff | `/cms/*` | Read listings | GET | No write domain | None | PARTIAL | Documented; no migration |
| Support | Admin/Staff | `/support` | Support | tickets JSON | replies | None | OPERATIONAL | Default 10/page |
| Deposits | Admin/Staff | `/deposits` | Deposits | RO + guard | No prod money QA | None | PARTIAL | Architecture guard |
| Markup | Admin/Staff | markup nav | Markup settings | RO | No prod mutation | None | PARTIAL | Discoverable; no prod mutate |
| Notifications / failed | Admin/Staff | notifications | Notification ops | list + classify | — | None | OPERATIONAL | QA SMTP classified |
| Profile | Admin/Staff | `/profile` | Session/profile | profile JSON | safe profile | None | OPERATIONAL | W2-08 |
| Public shell typography | Public | all | — | — | — | — | OPERATIONAL | W2-21/22 PASS |

## New APIs this batch

**None.** Reused existing lookup POST + guest JSON; presentation redirects only.

## Gates

| Gate | Result |
|---|---|
| OWNER_W2_MODULE_API_MATRIX | PASS (documented) |
| OWNER_W2_CORE_MODULES_OPERATIONAL | PASS for lookup/guest/auth/portals/admin core; PARTIAL CMS non-pages + deposits/markup by safety |
| OWNER_W2_API_CONTRACTS | PASS for lookup/guest JSON path |
| OWNER_W2_CMS_LEGACY_HANDOFFS | 0 |

## Next

Continue route/link crawl evidence and production browser proof for Manage Booking after deploy.
