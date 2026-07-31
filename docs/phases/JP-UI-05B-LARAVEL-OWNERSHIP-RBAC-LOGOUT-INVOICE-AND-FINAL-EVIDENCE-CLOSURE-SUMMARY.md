# JP-UI-05B — Laravel Ownership, RBAC, Logout, Invoice and Final Evidence Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-05B-LARAVEL-OWNERSHIP-RBAC-LOGOUT-INVOICE-AND-FINAL-EVIDENCE-CLOSURE |
| Branch | `phase/jetpk-ui-05b-ownership-rbac-logout-closure` |
| Baseline | `d6698b3` |
| Objective | Close remaining Laravel-authoritative ownership/RBAC/logout evidence gaps from JP-UI-05A without rerunning the 132-scenario visual matrix |

## Included scope

- Laravel customer booking ownership tests
- Laravel customer invoice ownership tests
- Laravel agent agency isolation tests (bookings, wallet, ledger, deposits)
- Laravel portal permission boundary tests (agent owner-only, agent staff, platform staff)
- Frontend logout and stale-session Playwright evidence
- Hydration documentation correction (root `<html>` theme suppression exception)
- JP-UI-05A QA doc cross-references

## Excluded scope

- UI redesign or dashboard visual changes
- 132-scenario visual matrix rerun
- Full Laravel or Playwright suite
- Supplier/booking/payment live operations
- Production deploy or DNS changes
- JP-UI-06 work

## Investigation findings

### Routes and policies audited

| Area | Routes | Policy / middleware |
|------|--------|---------------------|
| Customer bookings | `customer.bookings.index`, `customer.bookings.show` | `BookingPolicy::view`, `BookingPolicy::viewAny`, `ensureCustomerOwnsBooking` |
| Customer invoices | `customer.invoices.index`, `customer.invoices.show` | `BookingPolicy::view`, `CustomerPortalInvoicesPresenter::invoiceQuery` |
| Agent bookings | `agent.bookings.index`, `agent.bookings.show` | `BookingPolicy::view`, agent_id scope |
| Agent wallet/ledger/deposits | `agent.wallet.show`, `agent.ledger.index`, `agent.deposits.index` | `AgentPermission::WalletView`, `AgentPermission::LedgerView` |
| Agent owner-only | `agent.commissions.index` | `agent.admin` (`EnsureAgentAdmin`) |
| Platform staff | `staff.bookings.index`, `/admin/page-settings/home` | `StaffPermission::BookingsView`, `StaffPermission::PageSettingsManage` gate |
| Logout | `logout` (POST), frontend `auth-service.logout`, `AccountMenu` | CSRF via `laravelJsonFetch`, session fixture clearing |

### Root causes addressed

JP-UI-05A provided visual and fixture-backed frontend evidence but lacked dedicated Laravel test files under `tests/Feature/Jetpk/` documenting authoritative ownership contracts. JP-UI-05B adds those contracts without changing runtime authorization behavior.

## Files changed

### Laravel tests (new)

- `tests/Feature/Jetpk/CustomerBookingOwnershipTest.php` — 3 methods
- `tests/Feature/Jetpk/CustomerInvoiceOwnershipTest.php` — 3 methods
- `tests/Feature/Jetpk/AgentAgencyIsolationTest.php` — 5 methods
- `tests/Feature/Jetpk/PortalPermissionBoundaryTest.php` — 5 methods

### Frontend tests (new)

- `frontend/tests/jp-ui-05b-logout-session-closure.spec.ts` — 4 tests

### Documentation (updated)

- `frontend/docs/visual/JP-UI-05A-CUSTOMER-OWNERSHIP-AND-PRIVATE-ROUTE-QA.md`
- `frontend/docs/visual/JP-UI-05A-AGENT-AGENT-STAFF-AGENCY-ISOLATION-AND-RBAC-QA.md`
- `frontend/docs/visual/JP-UI-05A-ADMIN-PLATFORM-STAFF-FEATURE-PAGE-AND-PERMISSION-QA.md`
- `frontend/docs/visual/JP-UI-05A-DASHBOARD-HYDRATION-ROOT-CAUSE-AND-FIX.md`
- `frontend/docs/visual/JP-UI-IMPLEMENTATION-ROADMAP.md`
- `docs/phases/JP-UI-05B-LARAVEL-OWNERSHIP-RBAC-LOGOUT-INVOICE-AND-FINAL-EVIDENCE-CLOSURE-SUMMARY.md`

## Database / backend / frontend runtime changes

- **Database:** none
- **Backend runtime:** none (tests only)
- **Frontend runtime:** none (tests and documentation only)

## Tests executed

### Laravel (exact files only)

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan test tests/Feature/Jetpk/CustomerBookingOwnershipTest.php tests/Feature/Jetpk/CustomerInvoiceOwnershipTest.php tests/Feature/Jetpk/AgentAgencyIsolationTest.php tests/Feature/Jetpk/PortalPermissionBoundaryTest.php
```

**Result:** 16 passed, 58 assertions, ~72s

### Playwright (exact file only)

```powershell
cd frontend
npx playwright test tests/jp-ui-05b-logout-session-closure.spec.ts -c playwright.config.ts
```

**Result:** 4 passed, ~37s

### Conditional checks (not required)

- `npm run typecheck` — not run (test/documentation-only frontend changes)
- `npm run lint` — not run
- `npm run build` — not run
- Dashboard build/lint — not run

## Acceptance criteria

| Criterion | Result |
|-----------|--------|
| Customer owner access | PASS |
| Cross-customer booking denied | PASS |
| Customer list ownership-scoped | PASS |
| Customer invoice owner-only | PASS |
| Missing invoice unavailable in index | PASS |
| Agent records agency-scoped | PASS |
| Cross-agency booking denied | PASS |
| Wallet/ledger/deposits agency-scoped | PASS |
| Agent Staff direct owner-route denied | PASS |
| Platform Staff direct unauthorized route denied | PASS |
| Denied responses expose no protected data | PASS |
| Customer logout redirects safely | PASS |
| Agent logout redirects safely | PASS |
| CSRF-protected logout authoritative | PASS |
| Protected routes blocked after logout | PASS |
| Browser back/reload stale-state closure | PASS |
| Profile menu keyboard accessible | PASS |
| noindex on private routes (in logout tests) | PASS |
| Hydration documentation accurate | PASS |

## Hydration documentation correction

Updated `JP-UI-05A-DASHBOARD-HYDRATION-ROOT-CAUSE-AND-FIX.md` to state:

- Application hydration-error filtering was removed in JP-UI-05A
- React #418 is no longer ignored
- Full unfiltered JP-UI-05 matrix passed with zero hydration warnings
- `suppressHydrationWarning` remains only on root `<html>` for pre-hydration theme attribute
- Must not be used on dashboard content subtrees
- Console/pageerror/React #418 gates remain active

## Known limitations

- Playwright logout tests use session fixtures and mocked Laravel endpoints (no live Laravel process on port 8000 during smoke server)
- Invoice `show` route returns metadata with `pdf_available: false` when no document exists; index exclusion is the primary unavailable signal

## Risks

- Low: tests-only phase; no runtime authorization changes

## Rollback

```powershell
git revert <merge-commit-sha>
git push jetpk main
```

## Git SHAs

| Item | SHA |
|------|-----|
| Baseline | `d6698b3` |
| Test (authz) commit | _pending_ |
| Test (frontend logout) commit | _pending_ |
| Docs commit | _pending_ |
| Merge commit | _pending_ |
| Final docs SHA | _pending_ |
| Final main SHA | _pending_ |

## Final status

**READY** for JP-UI-06-CANONICAL-MOCKUP-BLUEPRINT-IMPLEMENTATION-OVERLAY-DIFF-AND-CROSS-PAGE-VISUAL-PARITY

Production untouched. Backup Safe untouched.
