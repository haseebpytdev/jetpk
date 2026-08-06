# JP-FULLSTACK-01F — Agent, Agent Staff, RBAC and Travelers Closure

| Field | Value |
|-------|-------|
| Phase | JP-FULLSTACK-01F |
| Branch | `phase/jetpk-fullstack-01f-agent-agent-staff-rbac-travelers` |
| Baseline | `94fc86a33d0f091aae1cd9166a49b81b47c1c9bd` |
| Status | READY FOR COMMIT REVIEW (not committed) |

## Gaps closed

| Gap ID | Original classification | Final classification | Production change |
|--------|-------------------------|----------------------|-------------------|
| JP-FS01-GAP-005 | BACKEND_EXISTS_FRONTEND_DISCONNECTED | CONNECTED_AND_VERIFIED | Yes — travelers JSON + Next CRUD |
| JP-FS01-GAP-006 | BACKEND_EXISTS_FRONTEND_DISCONNECTED | CONNECTED_AND_VERIFIED | Yes — finance statement + accounting ledger read-only JSON and Next |
| JP-FS01-GAP-011 | CONNECTED_NOT_VERIFIED | CONNECTED_AND_VERIFIED | Verification only |
| JP-FS01-GAP-016 | CONNECTED_NOT_VERIFIED | CONNECTED_AND_VERIFIED | Verification only |

## GAP-005 — Agent travelers

### Laravel contract

| Item | Value |
|------|--------|
| List | `GET agent.travelers.index?format=json` → `SavedTravelerController@index` |
| Create form | `GET agent.travelers.create?format=json` |
| Store | `POST agent.travelers.store?format=json` |
| Edit form | `GET agent.travelers.edit?format=json` |
| Update | `PATCH agent.travelers.update?format=json` |
| Delete | `DELETE agent.travelers.destroy?format=json` |
| Presenter | `AgentPortalTravelersPresenter` |
| Policy | `SavedTravelerPolicy` |
| Permission | `agent.permission:travelers.manage` + `saved_travelers` module |

### Next routes

| Public URL | Component |
|------------|-----------|
| `/agent/travelers` | `AgentTravelersPage` |
| `/agent/travelers/new` | `AgentTravelerFormPage` |
| `/agent/travelers/[id]/edit` | `AgentTravelerFormPage` |

### Ownership / RBAC

- `agency_id` and `user_id` set server-side on create; client values ignored.
- Agency-scoped list via `ownerAgentPortalUserIds()` + `current_agency_id`.
- Document numbers masked in list JSON.
- CSRF on mutations with one bounded 419 retry via `retryCsrfOnce: true`.

### Blade fallback

Retained at `/laravel/agent/travelers*`.

## GAP-006 — Finance surfaces (locked decision: implement)

### Finance statement

| Item | Value |
|------|--------|
| URI | `GET /agent/finance/statement?format=json` |
| Controller | `FinanceStatementController@show` |
| Policy | `FinanceStatementPolicy` |
| Export | `GET agent.finance.statement.export` — Laravel CSV only |
| Next route | `/agent/finance/statement` → `AgentFinanceStatementPage` |
| Export allowlist | Next renders export link only when URL starts with `/laravel/agent/finance/statement/export` (`finance-export-allowlist.ts`) |

### Accounting ledger

| Item | Value |
|------|--------|
| URI | `GET /agent/accounting/ledger?format=json` |
| Controller | `AccountingLedgerController@index` |
| Gate | `AccountingLedgerPolicy` + `agent.permission:ledger.view` |
| Next route | `/agent/accounting/ledger` → `AgentAccountingLedgerPage` |

### Authority

- All balances, movements and reconciliation from Laravel presenters/services.
- No client-side financial recalculation or mutations.
- Agency isolation preserved server-side.

### Blade fallback

Retained for both finance statement and accounting ledger HTML views.

## GAP-011 — Payments / invoices

- Route-specific Playwright coverage in `jp-ops-04-agent-operational.spec.ts` for `/agent/payments` and `/agent/invoices`: populated, empty, server error, expired session, staff denied, staff allowed, same-agency rows only.
- `AgentPaymentsInvoicesJsonTest` for Laravel JSON ownership and denial.
- No payments/invoices production code changes.

## GAP-016 — Agent Staff RBAC

- `AgentStaffPermissionTest`, `AgentPortalPermissionMatrixFinalTest`, `AgentPortalDataScopingTest` and Playwright ops regressions green.
- Explicit client-escalation tests in `jp-fullstack-01f-agent-travelers-finance.spec.ts` (forged nav + direct route → Laravel 403).
- Capabilities-driven navigation presentational; Laravel middleware authoritative.
- No RBAC definition changes.

## Navigation

`AgentPortalCapabilitiesPresenter` adds travelers, finance statement and accounting ledger items when module + permission allow.

## Tests executed

### Laravel

```bash
php artisan test tests/Feature/Agent/AgentTravelersJsonTest.php tests/Feature/Agent/AgentFinanceStatementJsonTest.php tests/Feature/Agent/AgentAccountingLedgerJsonTest.php tests/Feature/Agent/AgentPaymentsInvoicesJsonTest.php tests/Feature/Agent/AgentStaffPermissionTest.php tests/Feature/SavedTravelerTest.php tests/Feature/Agent/AgentPortalPermissionMatrixFinalTest.php tests/Feature/Agent/AgentPortalDataScopingTest.php tests/Feature/Auth/PublicSessionBootstrapTest.php
```

| File | Passed | Assertions |
|------|-------:|-----------:|
| `AgentTravelersJsonTest.php` | 12 | 31 |
| `AgentFinanceStatementJsonTest.php` | 6 | 21 |
| `AgentAccountingLedgerJsonTest.php` | 5 | 13 |
| `AgentPaymentsInvoicesJsonTest.php` | 8 | 19 |
| `AgentStaffPermissionTest.php` | 15 | 54 |
| `SavedTravelerTest.php` | 12 | 41 |
| `AgentPortalPermissionMatrixFinalTest.php` | 12 | 65 |
| `AgentPortalDataScopingTest.php` | 8 | 21 |
| `PublicSessionBootstrapTest.php` | 15 | 70 |
| **Grand total** | **93** | **338** |

Exit code: **0**

Legacy Blade regressions (`SavedTravelerTest`, `AgentPortalPermissionMatrixFinalTest`) required assertion updates for current portal nav markup (`/agent/travelers`, `/agent/bookings/create`); no production change.

### Playwright

```bash
cd frontend && npx playwright test tests/jp-fullstack-01f-agent-travelers-finance.spec.ts tests/jp-ops-04-agent-operational.spec.ts tests/jp-ops-02-portal-guards.spec.ts tests/jp-fullstack-01a-force-password.spec.ts tests/jp-ops-03-customer-operational.spec.ts tests/jp-ui-05b-logout-session-closure.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Spec | Passed |
|------|-------:|
| `jp-fullstack-01f-agent-travelers-finance.spec.ts` | 25 |
| `jp-ops-04-agent-operational.spec.ts` | 39 |
| `jp-ops-02-portal-guards.spec.ts` | 13 |
| `jp-fullstack-01a-force-password.spec.ts` | 13 |
| `jp-ops-03-customer-operational.spec.ts` | 11 |
| `jp-ui-05b-logout-session-closure.spec.ts` | 4 |
| **Grand total** | **105** |

Exit code: **0**

### Frontend quality gates

| Command | Exit |
|---------|-----:|
| `npm run typecheck` | 0 |
| `npm run lint` | 0 |
| `npm run build` | 0 |

## Playwright matrix evidence (01F spec)

| Scenario | Evidence |
|----------|----------|
| Traveler edit PATCH without ownership fields | `traveler edit mutation sends PATCH without ownership fields` |
| Traveler 422 validation | `traveler create validation 422 keeps form usable` |
| Traveler bounded 419 retry (success) | `traveler mutation 419 retries exactly once then succeeds` |
| Traveler bounded 419 termination | `traveler mutation second 419 terminates without infinite retry` |
| Finance statement populated/empty/error/session/denied | dedicated tests in 01F spec |
| Accounting ledger populated/empty/error/session/staff allowed/denied | dedicated tests in 01F spec |
| Export allowlist allowed/rejected | `finance statement allowed export handoff` + `finance statement rejects external export URL` |
| Client escalation (travelers + finance) | `forged travelers navigation cannot bypass Laravel denial` + `forged finance statement navigation cannot bypass Laravel denial` |

## Exact changed-path inventory (32)

| Path | Status | Category | Gap |
|------|--------|----------|-----|
| `app/Http/Controllers/Agent/SavedTravelerController.php` | modified | Laravel production | GAP-005 |
| `app/Http/Controllers/Agent/FinanceStatementController.php` | modified | Laravel production | GAP-006 |
| `app/Http/Controllers/Agent/AccountingLedgerController.php` | modified | Laravel production | GAP-006 |
| `app/Support/AgentPortal/AgentPortalCapabilitiesPresenter.php` | modified | Laravel production | GAP-005, GAP-006 |
| `app/Support/AgentPortal/AgentPortalTravelersPresenter.php` | added | Laravel production | GAP-005 |
| `app/Support/AgentPortal/AgentPortalFinanceStatementPresenter.php` | added | Laravel production | GAP-006 |
| `app/Support/AgentPortal/AgentPortalAccountingLedgerPresenter.php` | added | Laravel production | GAP-006 |
| `frontend/app/agent/travelers/page.tsx` | added | Next production | GAP-005 |
| `frontend/app/agent/travelers/new/page.tsx` | added | Next production | GAP-005 |
| `frontend/app/agent/travelers/[id]/edit/page.tsx` | added | Next production | GAP-005 |
| `frontend/app/agent/finance/statement/page.tsx` | added | Next production | GAP-006 |
| `frontend/app/agent/accounting/ledger/page.tsx` | added | Next production | GAP-006 |
| `frontend/features/agent-dashboard/travelers/AgentTravelersPage.tsx` | added | Next production | GAP-005 |
| `frontend/features/agent-dashboard/travelers/AgentTravelerFormPage.tsx` | added | Next production | GAP-005 |
| `frontend/features/agent-dashboard/finance/AgentFinanceStatementPage.tsx` | added | Next production | GAP-006 |
| `frontend/features/agent-dashboard/finance/AgentAccountingLedgerPage.tsx` | added | Next production | GAP-006 |
| `frontend/features/agent-dashboard/utils/finance-export-allowlist.ts` | added | Next production | GAP-006 |
| `frontend/features/agent-dashboard/index.ts` | modified | Next production | GAP-005, GAP-006 |
| `frontend/features/agent-dashboard/services/agent-dashboard-api.ts` | modified | Next production | GAP-005, GAP-006 |
| `frontend/features/agent-dashboard/types/index.ts` | modified | Next production | GAP-005, GAP-006 |
| `tests/Feature/Agent/AgentTravelersJsonTest.php` | added | Laravel test | GAP-005 |
| `tests/Feature/Agent/AgentFinanceStatementJsonTest.php` | added | Laravel test | GAP-006 |
| `tests/Feature/Agent/AgentAccountingLedgerJsonTest.php` | added | Laravel test | GAP-006 |
| `tests/Feature/Agent/AgentPaymentsInvoicesJsonTest.php` | added | Laravel test | GAP-011 |
| `tests/Feature/SavedTravelerTest.php` | modified | Laravel test | legacy Blade regression |
| `tests/Feature/Agent/AgentPortalPermissionMatrixFinalTest.php` | modified | Laravel test | RBAC regression |
| `frontend/tests/jp-fullstack-01f-agent-travelers-finance.spec.ts` | added | Playwright test | GAP-005, GAP-006 |
| `frontend/tests/jp-ops-04-agent-operational.spec.ts` | modified | Playwright test | GAP-011, GAP-016 |
| `docs/operations/JP-FULLSTACK-01-GAP-REGISTER.md` | modified | documentation | all 01F gaps |
| `docs/operations/JP-FULLSTACK-01-GAP-REGISTER.json` | modified | documentation | all 01F gaps |
| `docs/operations/JP-FULLSTACK-01-AUDIT-REPORT.md` | modified | documentation | all 01F gaps |
| `docs/phases/JP-FULLSTACK-01F-AGENT-AGENT-STAFF-RBAC-TRAVELERS-CLOSURE.md` | added | documentation | all 01F gaps |

Counts: Laravel production 7; Next production 13; Laravel tests 6; Playwright tests 2; documentation 4; **total 32**; additions 19; modifications 13; deletions 0.

## Excluded

- Customer/guest portal, checkout, payment providers, suppliers, notifications, CMS, OTP demo, `dashboard/`, RBAC enum expansion.

## Known limitations

- Accounting ledger detail remains Blade-only (`agent.accounting.ledger.show`); index is Next-primary.
- Finance statement detail is period summary only (no per-movement drill-down beyond list).

## Rollback

Revert branch `phase/jetpk-fullstack-01f-agent-agent-staff-rbac-travelers`; Blade fallbacks remain functional without Next.
