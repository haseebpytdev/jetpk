# OTA Admin Functional UI Audit

**Phase:** OTA-ADMIN-UX-FUNCTIONAL-1-COMPACT-BOOKING-DETAIL-AND-ACTION-INTEGRATION-AUDIT  
**Generated:** 2026-06-22  
**Scope:** Admin dashboard UI alignment, booking detail IA, action-button audit/repair. Local-only — do not upload to live.

---

## Executive summary

| Area | Verdict |
|------|---------|
| Booking detail layout | **Fixed** — single-column; supplier summary default; advanced diagnostics collapsed |
| Assign staff | **Fixed** — Staff-only dropdown; header inline form; list AJAX deep-links repaired |
| Staff booking show 500 | **Fixed** — `sabreCompactDiagnostic` passed from Staff controller |
| Route health audit | **Extended** — 9 additional admin GET pages + Blade route-name scan |
| Ticketing / cancellation / auto-PNR | **Unchanged** — remain disabled/gated |

---

## Assign staff — root cause

| Issue | Cause | Fix |
|-------|-------|-----|
| Appears broken on submit | Dropdown included **AgencyAdmin**; service requires **`isStaff()`** | `assignableUsersForAgency(..., staffOnly: true)` on booking show |
| Buried UX | Form only in Communication tab | Inline assign control in booking header action row |
| List Assign after AJAX | JS used `#assignment` (nonexistent) | Correct `?tab=communication#assign-staff-panel` URLs |
| Staff portal 500 | Missing `$sabreCompactDiagnostic` in Staff `BookingController::show` | Pass `compactStatusPanel()` like admin |

Schema: `assigned_staff_id`, `assigned_at` — no migration required. Policy: platform admin only.

---

## Page audit matrix (primary GET pages)

| Page | Route | Status | Actions verified |
|------|-------|--------|------------------|
| Dashboard | `admin.dashboard` | OK | KPI links, queue cards |
| Bookings list | `admin.bookings` | OK | Open, Assign, Payment — **AJAX links fixed** |
| Booking show | `admin.bookings.show` | OK | Assign, payment, PNR actions gated |
| Reports | `admin.reports` | OK | Export, supplier diagnostics link |
| Supplier diagnostics | `admin.reports.supplier-diagnostics` | OK | Read-only |
| Support tickets | `admin.support.tickets.index` | OK | View → show |
| Support ticket show | `admin.support.tickets.show` | OK | assign, forward, status, reply PATCH |
| Agent applications | `admin.agent-applications.index` | OK | Open review (module gate) |
| Agent application show | `admin.agent-applications.show` | OK | approve/reject/needs-info PATCH |
| Agents | `admin.agents` | OK | Open, export |
| Agencies | `admin.agencies.index` | OK | Show |
| Agency show | `admin.agencies.show` | OK | prefix, role PATCH |
| Users | `admin.users.index` | OK | CRUD links |
| API settings | `admin.api-settings` | OK | toggle/test/edit (module gate) |
| Markups | `admin.markups` | OK | CRUD (module gate) |
| Settings hub | `admin.settings.index` | OK | Section links |
| Branding | `admin.settings.branding.edit` | OK | PATCH save |
| Communications | `admin.settings.communications.index` | OK | PATCH update |
| Group ticketing hub | `admin.group-ticketing.index` | OK | tiles/inventory/categories links |
| Group ticketing tiles | `admin.group-ticketing.tiles.index` | OK | batch-upsert POST |
| Group ticketing inventory | `admin.group-ticketing.inventory.index` | OK | sync POST |
| Group ticketing categories | `admin.group-ticketing.categories.index` | OK | Read-only (by design) |
| Staff bookings show | `staff.bookings.show` | OK | Shared view; no assign; **500 fixed** |

---

## Button / action inventory

| Action | Verdict |
|--------|---------|
| Assign staff (admin booking) | **Fixed** — working PATCH |
| Bookings list Assign/Payment AJAX | **Fixed** |
| Create / Retry PNR | Verified gated via `$supplierActions` |
| Sync PNR itinerary | Verified gated |
| Issue ticket | Verified disabled |
| Cancel / Refund | Verified gated |
| Support ticket assign/reply/status | Verified routes exist |
| Agent application approve/reject | Verified PATCH |
| API settings toggle/test | Verified PATCH |
| Group ticketing sync / batch tiles | Verified POST |
| Passenger edit/validate (planned) | **Disabled** in `<details>` — not wired |

---

## Booking detail UI changes

- Removed `col-lg-4` supplier sidebar; full-width `col-12 col-xl-10` content column
- Supplier tab: compact summary KV + collapsed **Advanced supplier diagnostics**
- Overview tab: trimmed duplicate status “meaning” rows
- Header: primary action row with Assign staff inline select
- Tabs: “Notes / Activity”, “Communications” labels aligned
- CSS: `ota-admin-console.css?v=6` booking-detail tokens

---

## Out of scope (confirmed)

- RBAC audit not started
- No Bento / Admin v2 / Staff v2
- No Sabre retry gate changes
- No ticketing / live cancellation / public or checkout auto-PNR enablement
