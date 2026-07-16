# JetPK Final Gap Summary

**Date:** 2026-07-17  
**Branch state:** working tree (JETPK-STANDALONE-PORTAL closure)  
**Purpose:** Reproducible scan results closing Customer / Agent / Agent Staff portal parity.

---

## 1. Reproducible scans

Run from repository root:

```bash
# A. Portal theme inventory
(Get-ChildItem -Recurse -Filter "*.blade.php" resources/views/themes/customer/jetpakistan).Count
(Get-ChildItem -Recurse -Filter "*.blade.php" resources/views/themes/agent/jetpakistan).Count
(Get-ChildItem -Recurse -Filter "*.blade.php" resources/views/themes/frontend/jetpakistan/components/portal/finance).Count

# B. Legacy body leak check (expect: no output)
rg "@include\('dashboard\." resources/views/themes/customer/jetpakistan resources/views/themes/agent/jetpakistan

# C. Standalone fallback footprint (app + config)
rg -l "Parwaaz|haseeb-master|YoursDomain|YD Travel|ota\.haseebasif\.com|haseebasif" app config

# D. Route export
php artisan route:list --name=customer. --json > storage/app/customer-routes.json
php artisan route:list --name=agent. --json > storage/app/agent-routes.json

# E. Automated closure tests
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
```

---

## 2. Scan results (2026-07-17)

| Scan | Result | Pass criteria |
|------|--------|---------------|
| Customer `jetpakistan` blades | **11** files | ≥ 10 portal pages + layout |
| Agent `jetpakistan` blades | **27** files | Finance + ops pages present |
| Finance portal components | **8** files | All finance partials on disk |
| `dashboard.*` body `@include` in portal themes | **0** matches | Must be 0 |
| `app/` + `config/` legacy-term files | **40** files | Classified in `JETPK_STANDALONE_FALLBACK_AUDIT.md` (audit-only / mitigated) |
| `client_view` GET keys vs theme files | **33 / 33** | See route matrix |
| `JetpkStandalonePortalClosureTest` | **6 passed, 19 assertions** | All green |

---

## 3. Closure status by area

| Area | Status | Evidence |
|------|--------|----------|
| Customer dashboard / bookings | **Closed** | Themed blades; mobile shells in `ota-mobile.php` |
| Customer travelers | **Closed** | Themed blades + **new** `mobile/customer/travelers/*` |
| Customer support | **Closed** | Themed blades; `support-thread` component |
| Agent finance (statement, accounting ledger, reports) | **Closed** | Themed blades + finance components; feature test HTTP 200 |
| Agent wallet / deposits / ledger / commissions | **Closed** | Themed blades exist; financial contracts in view headers |
| Agent staff / agency / support / travelers | **Closed** | Themed blades + shared portal components |
| Profile (customer + agent) | **Closed** | `themes/*/jetpakistan/profile/edit.blade.php` |
| Standalone strict view resolver | **Enforced** | `config/client.php` + `RuntimeViewResolver::requiresStrictThemedView()` |
| Agent Staff separate theme | **N/A (by design)** | Shares `agent` area + permissions |

---

## 4. Remaining non-blocking items

| ID | Item | Severity | Notes |
|----|------|----------|-------|
| G-1 | `<x-dashboard.breadcrumbs>` / `<x-dashboard.status-badge>` on B-class pages | Low | Tenant-neutral primitives; not Parwaaz branding. `ota-dashboard-foundation.css` loaded in portal layout. |
| G-2 | `support@haseebasif.com` in some **view** fallbacks (outside `app/`) | Low | `resources/views/layouts/auth.blade.php`, error shells — not portal routes. Email resolver remaps outbound. |
| G-3 | `config/ota_client.php` default `public_webroot_path` → `ota.haseebasif.com` | Low | Diagnostic default; override with `OTA_PUBLIC_WEBROOT_PATH` on dedicated host. |
| G-4 | CLI audit commands default `--client=haseeb-master` | None | Audit-only; not invoked in production request path. |
| G-5 | `agent.finance.statement.show` has no `agent.permission` middleware | Info | Pre-existing; agency-scoped data in controller — RBAC review deferred. |

**No D-class gaps** (missing themed views for portal GET routes).

---

## 5. Related documents

| Document | Contents |
|----------|----------|
| `JETPK_PORTAL_FINAL_ROUTE_MATRIX.md` | Per-route role, URL, controller, middleware, `client_view` key, mobile key, classification |
| `JETPK_PORTAL_VIEW_CONTRACT_MATRIX.md` | Finance + integrated view data contracts |
| `JETPK_STANDALONE_FALLBACK_AUDIT.md` | Legacy-term classification in `app/` + `config/` |
| `JETPK_DEPLOYMENT_MANIFEST.md` | Dual-root SFTP paths for this phase |

---

## 6. Pre-deploy gate

```bash
php artisan optimize:clear
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
php artisan ota:route-page-health-audit --all
```

Do not upload when `fail>0` or closure tests fail.
