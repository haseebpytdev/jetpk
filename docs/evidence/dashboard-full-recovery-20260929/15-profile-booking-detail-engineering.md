# PR #57 — Profile + Booking Detail engineering slice

**Branch:** `work/jetpk-next-dashboard-recovery-20260929`  
**START_HEAD:** `179672f02edddc6111b66ce1e45b6d1939aea8d4`  
**ENGINEERING_HEAD:** `cde8d271` (includes UTF-8 homepage CMS label fix)  
**Captured:** 2026-09-29

## Gates (engineering)

| Gate | Status | Evidence |
|------|--------|----------|
| ADMIN_PROFILE_NEXT | PASS (code) | `dashboard/app/[portal]/dashboard/profile/page.tsx` + `features/profile/profile-page-content.tsx` |
| BOOKING_DETAIL_NEXT | PASS (code) | `dashboard/app/[portal]/dashboard/bookings/[id]/page.tsx` + `booking-detail-page-content.tsx` |
| Profile JSON non-customer | PASS | `ProfileController::jsonProfilePayload`; `ProfileJsonContractTest` |
| CUSTOMER_PROFILE_REGRESSION | PASS (PHPUnit contract) | Nested `user`/`profile` preserved + additive `account` |
| RETURN_DATE_SOURCE | `meta.search_criteria.return_date\|returnDate` | `DashboardBookingResource::returnDateFromMeta`; detail itinerary reuses summary |
| DASHBOARD_BUILD | PASS | `npm run lint`, `npm run typecheck`, `next build` exit 0 after UTF-8 homepage panel fix |
| Booking mutations on detail page | OMITTED | Read-only panels; `showOperationalActions={false}` |

## Tests run

```text
phpunit --filter ProfileJsonContractTest|DashboardBookingDetailJsonTest|ProfileTest|CompanyProfileJsonTest|BackOfficeSessionContractTest
→ passed tests=33 assertions=167

dashboard: npm run lint → No ESLint warnings or errors
dashboard: npm run typecheck → ok
dashboard: npm run build → ok (routes include /profile and /bookings/[id])
```

## Not closed (overall FINAL_STATUS remains PARTIAL)

```text
ADMIN_AUTH_E2E / STAFF / AGENT / CUSTOMER Playwright
ADMIN_SAFE_WRITE_RELOAD
RBAC_MATRIX / IDOR browser matrix
PUBLIC_GOLDEN_REGRESSIONS
MERGE / DEPLOY / LIVE AUTH
```

Local `php artisan serve` against copied `.env` hit homepage 500 / login timeouts (APP_URL/domain mismatch); API contracts are covered by PHPUnit with RefreshDatabase. Authenticated browser certification requires stable local proxy or production QA credentials + post-deploy live suite.

## Commercial safety

```text
REAL_TICKETS_ISSUED=0
REAL_PAYMENTS_TRIGGERED=0
REAL_SUPPLIER_BOOKINGS_CREATED=0
REAL_PNRS_MUTATED=0
REAL_CANCELLATIONS=0
REAL_REFUNDS=0
PRODUCTION_BALANCE_MUTATIONS=0
```
