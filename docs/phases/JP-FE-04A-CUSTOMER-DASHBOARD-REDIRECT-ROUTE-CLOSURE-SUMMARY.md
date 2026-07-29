# JP-FE-04A — Customer Dashboard Redirect Route Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-04A-CUSTOMER-DASHBOARD-REDIRECT-ROUTE-CLOSURE |
| Branch | `phase/jetpk-fe-04a-customer-route-closure` |
| Parent | JP-FE-04 (`0343409`) |
| Objective | Close route gap between Laravel customer destination `/customer/bookings` and Next.js ownership |
| Final status | **PASS** |
| Production touched | **No** |

## Issue

Laravel `ClientRedirectResolver::dashboardPathForUser()` returns `/customer/bookings` for customers (`customer.bookings.index`). JP-FE-04 added only `/customer`, so post-login and bootstrap redirects could target an unowned Next.js route.

## Audit findings

- **Laravel:** `dashboardPathForUser()` prefers `customer.bookings.index` → `/customer/bookings`
- **Session bootstrap:** `PublicSessionBootstrapService` uses same resolver; customers receive `dashboard_url: "/customer/bookings"`
- **Allowlists:** `PublicAuthRedirectAllowlist` and `dashboard-allowlist.ts` already accept `/customer/bookings` via `/customer/` prefix — **no Laravel allowlist change required**

## Implementation

1. **`/customer/bookings`** — guarded placeholder (`requireCustomerPortalAccess`) reusing `CustomerPortalPlaceholder`
2. **`/customer`** — server redirect to `/customer/bookings` (no auth loop)
3. **`requireCustomerPortalAccess`** — Laravel bootstrap authority:
   - unauthenticated → `/login`
   - non-`customer` `account_type` → Laravel `dashboard_url`
4. **AccountMenu** — bookings link now `/customer/bookings` (was Laravel proxy)
5. **Smoke fixture** — `ota_session_fixture` cookie + `OTA_ALLOW_SESSION_FIXTURE=true` for Playwright SSR guards only

## Files changed

- `frontend/app/customer/page.tsx` — redirect to `/customer/bookings`
- `frontend/app/customer/bookings/page.tsx` (new)
- `frontend/features/auth/components/CustomerPortalPlaceholder.tsx` (new)
- `frontend/features/auth/server/customer-portal-access.ts` (new)
- `frontend/features/auth/server/session-fixture.ts` (new)
- `frontend/features/auth/index.ts`
- `frontend/components/navigation/AccountMenu.tsx`
- `frontend/services/session.ts` — fixture `dashboardUrl` → `/customer/bookings`
- `frontend/playwright.config.ts`
- `frontend/tests/customer-portal-routes.spec.ts` (new)
- `frontend/docs/AUTHENTICATION-ARCHITECTURE.md`

## Laravel changes

None.

## Tests executed

```
npm run typecheck  → pass
npm run lint       → pass
npm run build      → pass (20 routes incl. /customer/bookings)
npx playwright test tests/customer-portal-routes.spec.ts → 5 passed
```

Laravel tests: not run (no PHP changes).

## Redirect flow

```
/customer  → 307 /customer/bookings  →  guard  →  placeholder | /login | role dashboard
Login success (customer)  →  /customer/bookings  →  owned placeholder
```

## Known limitations

- Customer portal remains a placeholder; no booking data exposed
- Real customer bookings UI still served by Laravel at `/laravel/customer/bookings` until a future phase ports it

## Commit SHAs

| Commit | SHA |
|--------|-----|
| Feature | `5154824` |
| Merge | `d02a9be` |

## Rollback

Revert merge commit on `main`. No migrations.

## Next phase

JP-FE-05 — flight results Next.js presentation (unchanged).
