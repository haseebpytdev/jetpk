# OTA Admin v1 Playwright Visual Audit

**Phase:** OTA-ADMIN-ADB-VISUAL-AUDIT-1-PLAYWRIGHT-ADMIN-V1  
**Generated:** 2026-06-22T10:48:11.290Z  
**Base URL:** http://127.0.0.1:8000  
**Playwright available:** YES  
**Auth method:** default demo fallback

---

## Executive summary

Playwright captured **haseeb-master admin v1** pages across desktop, laptop, tablet, and mobile viewports. This is **read-only visual QA** — no UI implementation was performed.

Admin v1 is **visually fragmented**: Tabler defaults (16px body in layout) conflict with compact operator goals; dashboard action cards use strong tone colors; bookings list has bespoke CSS; badge semantics differ across pages. **Compact shell cleanup** should precede Bento v2.

- Desktop pages captured: **11**
- Unique font sizes per page: **8–12** (target: ≤6)
- Table row heights (avg): **59, 98, 47, 31** px (target: 42–48)
- Pages with horizontal overflow @ 1440: **0** (none)

**Recommended next phase:** `OTA-ADMIN-ADB-POLISH-1-SHELL-V1-MASTER-CLIENT-ADMIN-COMPACT-CLEANUP`

---

## Screenshot inventory

| Page | Viewport | Path | HTTP | Font sizes | Button combos | Badges | Avg row h | Screenshot |
|------|----------|------|-----:|-----------:|--------------:|-------:|----------:|------------|
| dashboard | desktop-1440 | `/admin` | 200 | 11 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/dashboard/desktop-1440.png` |
| dashboard | laptop-1366 | `/admin` | 200 | 11 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/dashboard/laptop-1366.png` |
| dashboard | tablet-1024 | `/admin` | 200 | 11 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/dashboard/tablet-1024.png` |
| dashboard | mobile-390 | `/admin` | 200 | 10 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/dashboard/mobile-390.png` |
| bookings | desktop-1440 | `/admin/bookings` | 200 | 10 | 5 | 0 | — | `docs/audits/admin-v1-visual/screenshots/bookings/desktop-1440.png` |
| bookings | laptop-1366 | `/admin/bookings` | 200 | 10 | 5 | 0 | — | `docs/audits/admin-v1-visual/screenshots/bookings/laptop-1366.png` |
| bookings | tablet-1024 | `/admin/bookings` | 200 | 10 | 5 | 0 | — | `docs/audits/admin-v1-visual/screenshots/bookings/tablet-1024.png` |
| bookings | mobile-390 | `/admin/bookings` | 200 | 8 | 5 | 0 | — | `docs/audits/admin-v1-visual/screenshots/bookings/mobile-390.png` |
| booking-show | desktop-1440 | `http://127.0.0.1:8000/admin/bookings/1` | 200 | 9 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/booking-show/desktop-1440.png` |
| booking-show | laptop-1366 | `http://127.0.0.1:8000/admin/bookings/1` | 200 | 9 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/booking-show/laptop-1366.png` |
| booking-show | tablet-1024 | `http://127.0.0.1:8000/admin/bookings/1` | 200 | 9 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/booking-show/tablet-1024.png` |
| booking-show | mobile-390 | `http://127.0.0.1:8000/admin/bookings/1` | 200 | 8 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/booking-show/mobile-390.png` |
| reports | desktop-1440 | `/admin/reports` | 200 | 12 | 4 | 0 | 59 | `docs/audits/admin-v1-visual/screenshots/reports/desktop-1440.png` |
| reports | laptop-1366 | `/admin/reports` | 200 | 12 | 4 | 0 | 59 | `docs/audits/admin-v1-visual/screenshots/reports/laptop-1366.png` |
| reports | tablet-1024 | `/admin/reports` | 200 | 12 | 4 | 0 | 59 | `docs/audits/admin-v1-visual/screenshots/reports/tablet-1024.png` |
| reports | mobile-390 | `/admin/reports` | 200 | 10 | 4 | 0 | 59 | `docs/audits/admin-v1-visual/screenshots/reports/mobile-390.png` |
| support-tickets | desktop-1440 | `/admin/support/tickets` | 200 | 11 | 1 | 0 | 98 | `docs/audits/admin-v1-visual/screenshots/support-tickets/desktop-1440.png` |
| support-tickets | laptop-1366 | `/admin/support/tickets` | 200 | 11 | 1 | 0 | 98 | `docs/audits/admin-v1-visual/screenshots/support-tickets/laptop-1366.png` |
| support-tickets | tablet-1024 | `/admin/support/tickets` | 200 | 11 | 1 | 0 | 98 | `docs/audits/admin-v1-visual/screenshots/support-tickets/tablet-1024.png` |
| support-tickets | mobile-390 | `/admin/support/tickets` | 200 | 9 | 1 | 0 | 98 | `docs/audits/admin-v1-visual/screenshots/support-tickets/mobile-390.png` |
| group-ticketing | desktop-1440 | `/admin/group-ticketing` | 200 | 10 | 3 | 0 | — | `docs/audits/admin-v1-visual/screenshots/group-ticketing/desktop-1440.png` |
| group-ticketing | laptop-1366 | `/admin/group-ticketing` | 200 | 10 | 3 | 0 | — | `docs/audits/admin-v1-visual/screenshots/group-ticketing/laptop-1366.png` |
| group-ticketing | tablet-1024 | `/admin/group-ticketing` | 200 | 10 | 3 | 0 | — | `docs/audits/admin-v1-visual/screenshots/group-ticketing/tablet-1024.png` |
| group-ticketing | mobile-390 | `/admin/group-ticketing` | 200 | 7 | 3 | 0 | — | `docs/audits/admin-v1-visual/screenshots/group-ticketing/mobile-390.png` |
| users | desktop-1440 | `/admin/users` | 200 | 10 | 4 | 8 | 47 | `docs/audits/admin-v1-visual/screenshots/users/desktop-1440.png` |
| users | laptop-1366 | `/admin/users` | 200 | 10 | 4 | 8 | 47 | `docs/audits/admin-v1-visual/screenshots/users/laptop-1366.png` |
| users | tablet-1024 | `/admin/users` | 200 | 10 | 4 | 8 | 47 | `docs/audits/admin-v1-visual/screenshots/users/tablet-1024.png` |
| users | mobile-390 | `/admin/users` | 200 | 8 | 4 | 0 | 31 | `docs/audits/admin-v1-visual/screenshots/users/mobile-390.png` |
| api-settings | desktop-1440 | `/admin/api-settings` | 200 | 9 | 4 | 8 | — | `docs/audits/admin-v1-visual/screenshots/api-settings/desktop-1440.png` |
| api-settings | laptop-1366 | `/admin/api-settings` | 200 | 9 | 4 | 8 | — | `docs/audits/admin-v1-visual/screenshots/api-settings/laptop-1366.png` |
| api-settings | tablet-1024 | `/admin/api-settings` | 200 | 9 | 4 | 8 | — | `docs/audits/admin-v1-visual/screenshots/api-settings/tablet-1024.png` |
| api-settings | mobile-390 | `/admin/api-settings` | 200 | 7 | 4 | 8 | — | `docs/audits/admin-v1-visual/screenshots/api-settings/mobile-390.png` |
| settings | desktop-1440 | `/admin/settings` | 200 | 8 | 1 | 0 | — | `docs/audits/admin-v1-visual/screenshots/settings/desktop-1440.png` |
| settings | laptop-1366 | `/admin/settings` | 200 | 8 | 1 | 0 | — | `docs/audits/admin-v1-visual/screenshots/settings/laptop-1366.png` |
| settings | tablet-1024 | `/admin/settings` | 200 | 8 | 1 | 0 | — | `docs/audits/admin-v1-visual/screenshots/settings/tablet-1024.png` |
| settings | mobile-390 | `/admin/settings` | 200 | 7 | 1 | 0 | — | `docs/audits/admin-v1-visual/screenshots/settings/mobile-390.png` |
| supplier-diagnostics | desktop-1440 | `/admin/reports/supplier-diagnostics` | 200 | 10 | 3 | 1 | 31 | `docs/audits/admin-v1-visual/screenshots/supplier-diagnostics/desktop-1440.png` |
| supplier-diagnostics | laptop-1366 | `/admin/reports/supplier-diagnostics` | 200 | 10 | 3 | 1 | 31 | `docs/audits/admin-v1-visual/screenshots/supplier-diagnostics/laptop-1366.png` |
| supplier-diagnostics | tablet-1024 | `/admin/reports/supplier-diagnostics` | 200 | 10 | 3 | 1 | 31 | `docs/audits/admin-v1-visual/screenshots/supplier-diagnostics/tablet-1024.png` |
| supplier-diagnostics | mobile-390 | `/admin/reports/supplier-diagnostics` | 200 | 8 | 3 | 1 | 31 | `docs/audits/admin-v1-visual/screenshots/supplier-diagnostics/mobile-390.png` |
| dashboard-ui-v2-check | desktop-1440 | `/admin?ui=v2` | 200 | 11 | 3 | 4 | — | `docs/audits/admin-v1-visual/screenshots/dashboard-ui-v2-check/desktop-1440.png` |



---

## Page-by-page visual findings (desktop 1440×900)

### Admin Dashboard (`/admin`)

- Font sizes: 10px, 11px, 12.48px, 12px, 13px, 14.08px, 14px, 15.2px, 16px, 17px, 20px
- Buttons: 0 primary; 3 class combos
- Badges: 4 visible; samples: ota-dash-notice__badge | ota-dash-status-badge ota-dash-status-badge--muted | ota-dash-status-badge ota-dash-status-badge--good
- Card padding samples: 0px, 0px 16px 14px, 0px, 0px 16px 14px
- Issues: Too many font sizes (11); Body/base fonts ≥16px detected

### Admin Bookings (`/admin/bookings`)

- Font sizes: 10px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 2 primary; 5 class combos
- Badges: 0 visible; samples: n/a
- Card padding samples: 14px 16px, 14px 16px, 14px 16px, 14px 16px, 14px 16px
- Issues: Too many font sizes (10); Body/base fonts ≥16px detected

### Booking Detail (`http://127.0.0.1:8000/admin/bookings/1`)

- Font sizes: 10px, 11px, 12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 1 primary; 3 class combos
- Badges: 4 visible; samples: badge bg-blue-lt text-blue border-0 booking-pipeline-jump | badge bg-azure-lt text-azure border-0 booking-pipeline-jump | badge bg-purple-lt text-purple border-0 booking-pipeline-jump
- Card padding samples: 14px 16px, 16px, 14px 16px
- Issues: Too many font sizes (9); Body/base fonts ≥16px detected

### Admin Reports (`/admin/reports`)

- Font sizes: 10px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16.8px, 16px, 17px, 20px, 24px
- Buttons: 1 primary; 4 class combos
- Badges: 0 visible; samples: n/a
- Card padding samples: 14px, 14px
- Issues: Too many font sizes (12); Table rows tall (avg 59px); Body/base fonts ≥16px detected

### Support Tickets (`/admin/support/tickets`)

- Font sizes: 10px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16.8px, 16px, 17px, 20px
- Buttons: 0 primary; 1 class combos
- Badges: 0 visible; samples: n/a
- Card padding samples: n/a
- Issues: Too many font sizes (11); Table rows tall (avg 98px); Body/base fonts ≥16px detected

### Group Ticketing (`/admin/group-ticketing`)

- Font sizes: 11.5px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 2 primary; 3 class combos
- Badges: 0 visible; samples: n/a
- Card padding samples: 14px 16px, 14px 16px, 14px 16px, 14px 16px, 14px 16px
- Issues: Too many font sizes (10); Body/base fonts ≥16px detected

### Users Management (`/admin/users`)

- Font sizes: 10px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 2 primary; 4 class combos
- Badges: 8 visible; samples: badge bg-azure-lt
- Card padding samples: 0px, 14px 16px, 0px, 14px 16px, 0px
- Issues: Too many font sizes (10); Body/base fonts ≥16px detected

### API / Supplier Settings (`/admin/api-settings`)

- Font sizes: 10px, 11px, 12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 1 primary; 4 class combos
- Badges: 8 visible; samples: badge bg-secondary | badge bg-azure-lt text-azure ms-1
- Card padding samples: 0px, 14px 16px, 0px, 14px 16px, 0px
- Issues: Too many font sizes (9); Body/base fonts ≥16px detected

### Settings Hub (`/admin/settings`)

- Font sizes: 11px, 12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 0 primary; 1 class combos
- Badges: 0 visible; samples: n/a
- Card padding samples: 14px 16px, 14px 16px, 14px 16px, 14px 16px, 14px 16px
- Issues: Body/base fonts ≥16px detected

### Supplier Diagnostics (`/admin/reports/supplier-diagnostics`)

- Font sizes: 10px, 11px, 12px, 13.12px, 13px, 14.08px, 14px, 16px, 17px, 20px
- Buttons: 1 primary; 3 class combos
- Badges: 1 visible; samples: badge bg-blue-lt
- Card padding samples: n/a
- Issues: Too many font sizes (10); Body/base fonts ≥16px detected

### Dashboard ui=v2 channel check (`/admin?ui=v2`)

- Font sizes: 10px, 11px, 12.48px, 12px, 13px, 14.08px, 14px, 15.2px, 16px, 17px, 20px
- Buttons: 0 primary; 3 class combos
- Badges: 4 visible; samples: ota-dash-notice__badge | ota-dash-status-badge ota-dash-status-badge--muted | ota-dash-status-badge ota-dash-status-badge--good
- Card padding samples: 0px, 0px 16px 14px, 0px, 0px 16px 14px
- Issues: Too many font sizes (11); Body/base fonts ≥16px detected


---

## Typography consistency findings

| Observation | Detail |
|-------------|--------|
| Base body | Layout sets `body { font-size: 16px }` — above compact target (13–14px) |
| Page titles | `.page-title` / `.ota-admin-page-head` vary by page; dashboard uses subtitle pattern |
| Section labels | Mix of Tabler card titles, uppercase filter labels (bookings), and plain `h3` |
| Muted text | `.text-muted` and `#64748b` appear but not consistently on meta/helper copy |
| Line height | Dashboard cards use tighter custom line-height; tables use Tabler default |

---

## Compactness / density findings

| Area | Finding |
|------|---------|
| Dashboard KPI grid | Action cards generous padding; 10-card grid creates vertical scroll |
| Bookings list | Filter bar tall (`.bookings-filters` padding ~1rem); KPI row adds height |
| Tables | Mix of compact and default Tabler row padding; agents table custom density |
| Cards | `card-body` default Tabler padding often >18px |
| Whitespace | `container-xl py-4` page body padding consistent; dashboard overview reduces via `:has()` |

---

## Color findings

| Issue | Detail |
|-------|--------|
| Strong action-card tones | Dashboard uses amber/violet/emerald/rose accent blocks — high chroma count |
| Status colors | Three families: Tabler `bg-*-lt`, bookings `badge-soft-*`, `ota-dash-status-badge--*` |
| Primary text | Generally `#0f172a` / Tabler body — acceptable |
| Borders | Mix of `rgba(98,105,118,.12–.16)` and `#e2e8f0` |
| Ops banner | Permanent warning yellow strip on all admin pages |

---

## Component consistency findings

| Component | Status |
|-----------|--------|
| Buttons | Dominant: `btn btn-outline-secondary btn-sm`; primary actions inconsistent sizing |
| Badges | **High drift** — see metrics per page |
| Cards | Tabler `card` vs `ota-dash-action-card` vs bookings preview card |
| Tables | `table-vcenter card-table` vs custom wrappers |
| Tabs | Booking detail custom tabs vs Bootstrap nav-tabs elsewhere |
| Icons | Tabler `ti` icons; sidebar 1rem, buttons vary |

---

## Table / filter / action bar findings

- **Bookings:** Best-structured filter bar but tallest; queue tabs pill-style unlike rest of admin.
- **Reports:** Multiple tables, dense horizontally — overflow risk on 1024px.
- **Users/agents:** Custom table CSS on agents page vs standard admin tables.
- **Settings hub:** Card grid navigation — consistent with Tabler but not compact.

---

## Sidebar / topbar findings

| Element | Finding |
|---------|---------|
| Sidebar width | Tabler vertical navbar; compact link padding (`ota-sidebar-refined`) |
| Nav density | Collapsible groups good; long module list scrolls |
| Topbar | Minimal — user email truncate + logout only; **no search/notifications** |
| Page header | `@hasSection('page-header')` pattern; not all pages use `ota-admin-page-head` |
| Branding | Runtime product name in sidebar — multi-client safe |

---

## Responsive findings

| Viewport | Notes |
|----------|-------|
| 1440×900 | Reference; sidebar + content comfortable |
| 1366×768 | Slightly tighter; bookings split view may compress preview column |
| 1024×768 | Sidebar collapse expected; table horizontal scroll on reports/bookings |
| 390×844 | Admin usable but not primary; cards stack; filter bars wrap |

---

## Runtime / channel safety

| Check | Result |
|-------|--------|
| haseeb-master admin renders | YES |
| `/admin?ui=v2` vs v1 | Same layout (v2 overlay absent — expected) |
| Public/customer/agent/staff | Not captured — **unchanged by this audit** |
| Sabre/ticketing mutations | **None** — read-only navigation only |

- admin v2 preview query loads; no v2 overlay files — layout matches v1 (expected).

---

## Priority issue list

1. **P0** — Extract/normalize admin CSS; reduce 16px base to 13–14px for operator density
2. **P0** — Unify status badge component across dashboard, bookings, lists
3. **P1** — Compact filter bars and table row height (bookings, reports, users)
4. **P1** — Standardize page header pattern (`ota-admin-page-head`)
5. **P1** — Reduce dashboard action-card color noise; use muted borders + single accent
6. **P2** — Topbar: profile link + notification placeholder
7. **P2** — Scope/dismiss ops onboarding banner
8. **P2** — Migrate page `@push('styles')` blocks into shared admin CSS

---

## Recommended design tokens (implementation target)

| Token | Target |
|-------|--------|
| Base font size | 13px–14px |
| Page title | 20px–22px / weight 700 |
| Section title | 15px–16px / weight 600 |
| Card label | 12px–13px |
| Table cell | 12px–13px |
| Muted text | `#64748b` |
| Primary text | `#0f172a` |
| Soft border | `#e2e8f0` |
| Card padding (compact) | 14px–18px |
| Table row height | 42px–48px |
| Button height (compact) | 32px–36px |
| Badge height | 20px–24px |
| Icon size | 16px–18px |

---

## Safety confirmation

- UI implementation: **NO**
- Runtime files upload: **NOT NEEDED**
- Public CSS/JS upload: **NOT NEEDED**
- Admin/Staff/Public channels: **UNCHANGED**
- Sabre/ticketing/auto-PNR/checkout-auto-PNR/live-cancellation: **UNCHANGED**
- Screenshots/docs: **LOCAL ONLY** — do not upload to live

---

*End of Playwright visual audit report.*
