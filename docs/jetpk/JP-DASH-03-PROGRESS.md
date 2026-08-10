# JP-DASH-03 — Operational Back Office Progress

## PHASE

`JP-DASH-03` — Operational Admin/Staff back office (V2 master closure loop)

## CURRENT_STATUS

`JP_DASH_03=FAIL_NOT_OPERATIONALLY_CLOSED`

Engineering checkpoint 6 applied for human-confirmed production defects A–C (private URL leak, preview residue, settings preview UI). Full module/action matrix closure remains incomplete.

## LAST_UPDATED_UTC

2026-08-10T06:35:00Z

## CURRENT_COMMIT

pending push — checkpoint 7 currency contract + customer hardening

## CURRENCY_PRESENTATION_INTEGRITY

`FAIL_UNKNOWN_CURRENCY_FALLBACK` → **fix in checkpoint 7** — unresolved currency shows "Amount unavailable" / "Currency not recorded"; never bare numeric amount.

## CHECKPOINT 7 — MONEY CONTRACT

- `DashboardMoneyPresenter::presentMinorUnits()` returns `currencyStatus: resolved|unresolved`
- Unresolved: `displayLabel=Amount unavailable`, `currencyLabel=Currency not recorded`, `needsReview=true`
- API: `totalMoney`, `currencyStatus`, `currencySource` on booking/payment resources
- UI: `MoneyDisplay`, `formatMoneyDisplay`, `formatMoneyDetail` — no bare amount fallback
- Tests: `DashboardMoneyPresenterTest` (6 assertions including no-bare-amount)

## CHECKPOINT 7 — CUSTOMERS

- `DashboardCustomersReadService::detail()` loads profile + booking aggregates
- Defensive `transformCustomersPage` when customers array missing
- Live-mode empty state removes synthetic preview wording

## PRODUCTION_DEPLOYED

- Checkpoint 4–5: `DASH_BUILD=h1Jr2GjL650X1FFsFvm8C`, `FE_BUILD=3yYuvbzDaBFt1Lj2ONCB0`
- Checkpoint 6: **not deployed yet** — local build pass; deploy after push

## ADMIN_PRODUCTION_BROWSER_ACCEPTANCE

`PENDING_HUMAN_SESSION` — do not use `admin@ota.demo`. Verify Staff/API Settings hrefs, drawer banners, settings redirect on production browser.

## REMOTE_PHASE_PROGRESS

`PASS` — `jetpk` → `https://github.com/haseebpytdev/jetpk.git`

## ACCEPTANCE GATES (V2 authoritative)

| Gate | Status |
|------|--------|
| `JP_DASH_03` | `FAIL_NOT_OPERATIONALLY_CLOSED` |
| `FULL_BACKOFFICE_ACCEPTANCE` | `FAIL` |
| `PRIVATE_LARAVEL_BROWSER_EXPOSURE` | `FAIL` → **fix staged** (relative Laravel nav paths + client sanitize) |
| `PREVIEW_RESIDUE_PRODUCTION` | `FAIL` → **fix staged** (live-mode drawer banners removed) |
| `SETTINGS_OPERATIONAL_STATE` | `FAIL` → **fix staged** (admin live redirect to Laravel settings) |
| `CURRENCY_PRESENTATION_INTEGRITY` | `UNVERIFIED` → partial (authoritative booking/payment currency chain; reconciliation pending) |
| `BACKOFFICE_PAGE_MATRIX` | `FAIL` |
| `BACKOFFICE_ACTION_MATRIX` | `FAIL` |
| `BOOKING_MANAGEMENT` | `FAIL` |
| `ADMIN_PRODUCTION_BROWSER_ACCEPTANCE` | `FAIL` (human session required) |
| `JP_DASH_03_SOURCE_PARITY` | Not run |

## CHECKPOINT 6 — V2 DEFECT REPAIRS

### Private Laravel origin (defects A, B partial)

- **Root cause:** `BackOfficeCapabilitiesPresenter::laravelNav()` used `route()` → `APP_URL` loopback in session API `navigation[].href`
- **Fix:** `BackOfficeLaravelRoutePaths` maps route names to relative `/admin/...` paths
- **Defense:** `sanitizePublicHref()` on sidebar Laravel links + support CTA
- **Test:** `DashboardNavigationOperationalTest::test_laravel_navigation_hrefs_are_public_relative_paths`

### Preview residue (defects C, D)

- **Root cause:** `PreviewDataBanner` hardcoded in all detail drawers regardless of live mode
- **Fix:** `DetailDrawerSourceNotice` — banner only in preview mode; supplier notes preview text gated
- **Modules touched:** bookings, suppliers, payments, pnrs, tickets, customers, agents, users, roles, permissions, audit drawers

### Settings preview UI (defect E)

- **Fix:** `SettingsLiveGate` — admin live mode redirects to `admin.settings.index`; staff shows unavailable state (no preview editors)

### Currency provenance (defect H — partial)

- **Fix:** `DashboardMoneyPresenter` resolves booking currency from `booking.currency` → `fareBreakdown.currency` → meta fields (no PKR default)
- **UI:** `formatCurrency` uses ISO code via `en-US` Intl; omits false PKR when currency unknown
- **Overview recent bookings:** amount label uses resolved currency
- **Test:** `DashboardMoneyPresenterTest`

## PAGE MATRIX (snapshot — not closed)

| Module | Route | Owner | LIVE_DATA | PREVIEW | NAV | STATUS |
|--------|-------|-------|-----------|---------|-----|--------|
| Dashboard | `/` | Next | yes | no | pass | UNTESTED browser |
| Bookings | `/bookings` | Next API | yes | gated | pass | UNTESTED |
| Staff | `/admin/staff` | Laravel | yes | no | **fix staged** | UNTESTED browser |
| API Settings | `/admin/api-settings` | Laravel | yes | no | **fix staged** | UNTESTED browser |
| Settings | `/settings` | Laravel handoff | yes | gated | **fix staged** | UNTESTED browser |
| Customers | `/customers` | Next API | yes | gated | unknown | FAIL (error boundary reported) |
| Suppliers | `/suppliers` | Next API | yes | gated | pass | UNTESTED |

## TEST_RESULTS (checkpoint 6)

- Closure Laravel suite: **33 passed** (navigation, overview, money presenter, session contract, operational closure)
- `dashboard npm run typecheck`: pass
- `dashboard npm run build`: pass

## OLS BASELINES (must not drift)

| Scope | SHA256 |
|-------|--------|
| GLOBAL | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| VHOST | `8da510a8f911d8d711658abd8a110b04309d6295cf513f9f7dce4efdd794a42a` |

## REMAINING BLOCKERS

1. Production deploy checkpoint 6 + source parity SHA256
2. Human browser verification (Staff, API Settings, drawers, settings redirect)
3. Customers module error boundary root cause
4. Per-module operational matrix (filters, pagination, actions)
5. Playwright production crawler (127.0.0.1 / preview text fail gates)
6. Cross-module currency reconciliation on representative bookings
7. Full OTA regression `UNEXPECTED_FAILURES=0`

## FINAL_STATUS

`JP_DASH_03=FAIL_NOT_OPERATIONALLY_CLOSED` — do not declare PASS until all V2 gates close.
