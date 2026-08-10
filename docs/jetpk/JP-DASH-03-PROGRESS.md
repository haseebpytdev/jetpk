# JP-DASH-03 — Operational Back Office Progress

## PHASE

`JP-DASH-03` — Operational Admin/Staff back office (V2 autonomous acceptance loop)

## CURRENT_STATUS

`JP_DASH_03=FAIL_NOT_OPERATIONALLY_CLOSED`

Checkpoint 8: currency provenance repair, Playwright acceptance infrastructure, source parity PASS (24 files). Full authenticated browser matrix still blocked on one-time Admin login bootstrap.

## LAST_UPDATED_UTC

2026-08-10T11:35:00Z

## CURRENT_COMMIT (local, pre-push)

Pending checkpoint 8 commit on `phase/jetpk-dash-03-operational-backoffice`

## PRODUCTION_DEPLOYED

- Checkpoint 8 deploy (2026-08-10): `DASH_BUILD=tJ3O0Oxkx4X1meN6V9zs7` (prior: `wwH8w6zE2pPsmiZ3ie_-8`)
- Backup: `/home/pkjetp/jetpk-dash-03-20260810163000`
- `pm2 restart jetpk-dashboard` — online
- `curl http://127.0.0.1:3001/admin/dashboard` → **200**
- Money trace (Sabre sample): resolves **USD** from `fareBreakdown.currency` (not draft PKR default)
- Root OLS GLOBAL/VHOST SHA256 — **MATCH** (no drift)

## ADMIN_PLAYWRIGHT_SESSION

`MISSING` — run once locally:

```bash
cd dashboard && npm run acceptance:admin-login
```

Then:

```bash
npm run acceptance:production-crawl
npm run test:production-acceptance
```

Storage state: `tmp/jp-dash-03-admin-storage-state.json` (local-only, gitignored, never commit).

## CHECKPOINT 8 — CURRENCY PROVENANCE

- `resolveBookingCurrencyWithSource()` prefers supplier/fare provenance before `booking.currency` (draft PKR default)
- Order: `meta.original_currency` → `meta.offer_currency` → `fareBreakdown.currency` → `meta.currency` → `booking.currency`
- `presentBookingTotal()` flags `needsReview` when fare vs booking currency conflict
- `DashboardPaymentResource` prefers `payment.currency` → `fareBreakdown.currency` → `booking.currency`
- Production Sabre samples: `624.00 USD` / `590.00 USD` / `836.00 USD` (was incorrectly PKR)

## CHECKPOINT 8 — PLAYWRIGHT ACCEPTANCE INFRA

| Asset | Purpose |
|-------|---------|
| `dashboard/scripts/jp-dash-03-admin-login-bootstrap.mjs` | One-time headed Platform Admin login |
| `dashboard/scripts/jp-dash-03-production-crawl.mjs` | Authenticated page + sidebar nav crawl |
| `dashboard/scripts/jp-dash-03-source-parity.mjs` | LOCAL vs PRODUCTION SHA256 |
| `dashboard/playwright.production-acceptance.config.ts` | Production base URL + storageState |
| `dashboard/tests/jp-dash-03-production-acceptance.spec.ts` | Sample acceptance tests |

## CHECKPOINT 8 — SOURCE PARITY

`JP_DASH_03_SOURCE_PARITY=PASS` — **24/24** files (checkpoints 1–7 Laravel + dashboard money/overview/drawer files).

Evidence: `docs/jetpk/JP-DASH-03-SOURCE-PARITY.json`

## FX POLICY AUDIT

No authoritative dashboard FX conversion service found. Dashboard displays stored transaction currency from booking/fare/payment provenance. No external rate provider in dashboard path.

## ACCEPTANCE GATES (V2 authoritative)

| Gate | Status |
|------|--------|
| `JP_DASH_03` | `FAIL_NOT_OPERATIONALLY_CLOSED` |
| `FULL_BACKOFFICE_ACCEPTANCE` | `FAIL` |
| `ADMIN_PLAYWRIGHT_SESSION` | `MISSING` |
| `PRIVATE_LARAVEL_BROWSER_EXPOSURE` | **API/presenter PASS** — Playwright nav crawl pending |
| `PREVIEW_RESIDUE_PRODUCTION` | Gating deployed — Playwright crawl pending |
| `SETTINGS_OPERATIONAL_STATE` | Handoff deployed — browser verify pending |
| `CURRENCY_PRESENTATION_INTEGRITY` | **PASS** (unresolved labels + fare provenance) |
| `BOOKING_CURRENCY_MATCH` | **PASS** (Sabre fare USD reconciled) |
| `BOOKING_AMOUNT_MATCH` | **PASS** (amount matches fare total) |
| `JP_DASH_03_SOURCE_PARITY` | **PASS** (24/24) |
| `BACKOFFICE_PAGE_MATRIX` | `FAIL` (crawl pending) |
| `BACKOFFICE_ACTION_MATRIX` | `FAIL` |
| `BOOKING_MANAGEMENT` | `FAIL` |
| `STAFF_PRODUCTION_BROWSER_ACCEPTANCE` | `AWAITING_EXISTING_SAFE_STAFF_ACCOUNT` |

## TESTS (checkpoint 8)

- `DashboardMoneyPresenterTest` — 8 passed
- `DashboardReadOnlyApiTest` + money presenter — 38 passed, 256 assertions
- Dashboard `npm run typecheck` — pass
- Dashboard `npm run lint` — pass (existing img warning only)

## ROLLBACK

- Laravel: restore from `/home/pkjetp/jetpk-dash-03-20260810163000`
- Dashboard: prior build `wwH8w6zE2pPsmiZ3ie_-8` + `pm2 restart jetpk-dashboard`

## NO MERGE

Branch `phase/jetpk-dash-03-operational-backoffice` — do not merge locally.
