# Admin UI Defects & Mockup Gaps

Phase: **JETPK-DASH-01**

## Known issues (from repo docs & inspection)

| ID | Area | Finding | Severity | Notes |
|----|------|---------|----------|-------|
| UI-01 | Design system | JetPK layout loads `ota-admin-console.css` despite `docs/JETPK_DASHBOARD_DESIGN_SYSTEM.md` forbidding it | warn | [`themes/admin/jetpakistan/layouts/dashboard.blade.php`](../../resources/views/themes/admin/jetpakistan/layouts/dashboard.blade.php) |
| UI-02 | Topbar search | Search input present but **disabled**; no JS wiring in `dashboard.js` | info | Matches safe mock-only search in Next |
| UI-03 | Dual nav | Legacy Tabler sidebar richer than JetPK compact sidebar; fallback IA drift | warn | Audit uses JetPK sidebar as functional map |
| UI-04 | Overview | “System Status” panel includes hardcoded “Wallet Service Active” | info | Next should use fixture health only |
| UI-05 | Functional audit | `docs/audits/OTA_ADMIN_FUNCTIONAL_UI_AUDIT.md` — dashboard OK; booking detail issues | refer | Not overview-specific |

## Approved mockup vs current Blade

| Mockup control | Current admin shell | DASH-01 Next treatment |
|----------------|--------------------|-------------------------|
| Currency PKR selector | Absent | Mock-only UI |
| Notifications inbox (badge) | Absent | Mock-only fixture list |
| Messages inbox | Absent | Mock-only UI |
| Fullscreen | Absent | Client-only fullscreen API |
| Green accent / dark sidebar | Orange JetPK tokens in Blade | **Mockup green** `#10B981` for Next |
| Expandable nav (Payments, Tickets, …) | Flat module groups | Map to audited routes; mark unimplemented as **planned** |
| “Add New Booking” / “Bulk Upload” quick actions | Not on current overview | **Omit or preview-only** — no matching admin create/bulk routes on overview |
| Date range / Export report on overview | Not on current overview | Mock-only chrome (no export) |

## Accessibility targets (Next)

WCAG 2.2 AA where practical: focus-visible, dialog labels, 44px touch targets, no color-only status (icons + text).

## Responsive

JetPK sidebar collapses ~1100px; Next must support 360–1920 widths with intentional mobile drawer layout.
