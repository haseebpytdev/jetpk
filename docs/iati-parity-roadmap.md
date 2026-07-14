# IATI parity implementation roadmap (E-series)

**E0 — Master roadmap / checklist tracker.** This file is the permanent source of truth for IATI-vs-OTA gap closure. Update **Status** and **Notes** here when a phase ships; do not rely on chat history.

**Last reviewed:** 2026-05-17  
**Related:** [`summary.md`](../summary.md) (agent map), [`docs/product-overview.md`](product-overview.md), [`docs/releases/phase-23c-notification-management.md`](releases/phase-23c-notification-management.md)

---

## 1. Executive summary

### IATI strengths (reference product)

| Area | What IATI does well |
|------|---------------------|
| Portal UX | Dense, module-style customer and agent dashboards; accordion sidebars; wallet/credit surfaces |
| Commercial ops | Wallet, credit, deposit request/history flows for agents |
| Support | First-class support ticket list, thread, and status workflow |
| Account surfaces | Notification center, profile/account portal patterns |
| Booking UX | Multi-module booking dashboard (flights + broader travel verticals) |
| Vertical breadth | Hotels, packages, and other modules beyond flights |

### OTA strengths (keep and extend)

| Area | What OTA does well |
|------|---------------------|
| Architecture | Laravel role separation: `platform_admin`, `agency_admin`, `staff`, `agent`, `customer` |
| Authorization | Policies, middleware, agency scoping — not session-only admin gates |
| Sabre safety | B77 stale shop guard, B80/B82 operational status honesty, B83 itinerary display honesty, B84 PNR itinerary sync foundation |
| Back office | Booking queues, payment/refund/cancel flows, documents, communication delivery logs |
| Suppliers | Per-agency `SupplierConnection`, encrypted credentials, extensive Sabre diagnostics (inspect/compare commands) |
| Notifications (foundation) | `OtaNotificationService`, per-event settings, templates, scheduled reports (Phase 23C) |

### Strategic direction

Implement **Laravel-native** parity **inspired by** IATI UX and workflows — **not** a direct copy of IATI PHP structure, credentials, or unsafe patterns.

- Reuse existing controllers, services, policies, and Blade/Tailwind patterns.
- Prefer one portal layout (`layouts/dashboard.blade.php`) and shared dashboard components (E1).
- Keep Sabre/ticketing gates explicit; no “shortcut” live ticketing to match IATI demos.
- Track every feature in **§3** until **Status** = `done` or `deferred` with rationale.

---

## 2. E-series phase map

| Phase | Name | Goal | Depends on | Tracker |
|-------|------|------|------------|---------|
| **E0** | Master roadmap / checklist tracker | This document; status hygiene | — | `done` (2026-05-17) |
| **E1** | Dashboard UI foundation | Shared KPI/quick-action/empty-state/status-badge components; customer/agent/staff home alignment | — | `done` (2026-05-17) |
| **E2** | Customer dashboard parity | Customer home, bookings list/detail polish, self-service actions | E1, E6 (partial) | `done` (2026-05-17) |
| **E3** | Agent dashboard parity | Agent home, bookings, commissions UX; prep for wallet | E1, E9 (design) | `done` (2026-05-17) |
| **E4** | Staff/operator dashboard upgrade | Queue cards, assigned work, payment review UX | E1 | `done` (2026-05-17) |
| **E5** | Admin dashboard polish | KPIs, quick actions, booking ops cards alignment | E1 | `done` (2026-05-17) |
| **E6** | SMTP role-based notifications | Route events by role; customer/agent inbox surfaces | Phase 23C base | `done` (2026-05-17) |
| **E7** | Support ticket system | Tickets CRUD, assignment, thread, customer/agent visibility | E2, E3 | `done` (2026-05-17) |
| **E8** | Payments/documents polish | Payment proof upload UX, invoice/receipt downloads, refund request UX | E2 | `done` (2026-05-17) |
| **E9** | Agent wallet/credit/deposit | Ledger, balance, deposit request/approval (agency policy) | E3, E8 | `done` (2026-05-17) |
| **E10** | Ticketing readiness checklist | Preconditions, flags, admin actions — **no** blind live issue | Sabre B-series stable | `done` (2026-05-17) |
| **E11** | Saved passengers / traveler profiles | Reuse travelers across bookings per user/agency rules | E2, E3 | `done` (2026-05-17) |
| **E12** | CMS / promo / settings parity | Pages, promos, homepage/branding admin parity vs IATI marketing surfaces | E5 | `done` (2026-05-17) |

**Phase status legend:** `not_started` | `in_progress` | `done` | `deferred`

---

## 3. Master feature checklist

Update **Status** when implementing. **Gap type:** `missing` | `partial` | `done` | `better-than-IATI` | `skipped`.

| Area | Feature | IATI status | OTA status | Gap type | Priority | Target phase | Notes | Status |
|------|---------|-------------|------------|----------|----------|--------------|-------|--------|
| Customer dashboard | Home KPIs + quick actions | Strong | Partial (E1 components) | partial | P1 | E2 | `CustomerBookingController@dashboard`, shared components | in_progress |
| Customer dashboard | Sidebar module navigation | Strong | Basic | missing | P2 | E2 | Single-flight focus OK for v1 | not_started |
| Customer bookings | Bookings list + filters | Strong | Partial | partial | P1 | E2 | Portal routes exist | in_progress |
| Customer booking detail | Status timeline + actions | Strong | Partial | partial | P1 | E2 | Align badges with `BookingOperationalStatus` | not_started |
| Payment proof | Upload / replace proof | Strong | Partial | partial | P1 | E8 | Staff review path exists | not_started |
| Documents/downloads | Invoice, receipt, itinerary PDF | Strong | Partial | partial | P1 | E8 | `BookingDocumentController` + policies | not_started |
| Cancellation/refund request | Customer-initiated request | Strong | Partial | partial | P1 | E2/E8 | Cancel flow exists; refund request UX TBD | not_started |
| Support tickets | Create + list + thread | Strong | Missing | missing | P1 | E7 | Frontend support may be contact-only today | not_started |
| Notifications/inbox | In-app notification center | Strong | Missing | missing | P1 | E6/E2 | Email events exist; no customer inbox | not_started |
| Agent dashboard | Home KPIs + quick actions | Strong | Partial (E1) | partial | P1 | E3 | `Agent\DashboardController` | in_progress |
| Agent commissions | Statements + balance | Strong | Partial | partial | P2 | E3 | `AgentCommissionController` | in_progress |
| Agent wallet/credit/deposit | Balance, deposit request/history | Strong | Missing | missing | P1 | E9 | No ledger model in roadmap baseline | not_started |
| Agent agency profile | Agency branding/contact view | Strong | Partial | partial | P2 | E3 | Profile routes | not_started |
| Agent booking creation | Agent-assisted booking | Strong | Partial | partial | P2 | E3 | Agent booking controllers | not_started |
| Staff dashboard | Home KPIs + queue snapshot | Strong | Partial (E1) | partial | P1 | E4 | `Staff\DashboardController` | in_progress |
| Staff booking queue | Operational queue list | Strong | Partial | partial | P1 | E4 | Staff booking index | not_started |
| Staff assigned bookings | My assignments filter | Strong | Partial | partial | P2 | E4 | May need assignment model/rules | not_started |
| Staff payment review | Verify/reject payment proof | Strong | Partial | partial | P1 | E4/E8 | `Staff\BookingPaymentController` | not_started |
| Staff manual review | Notes + needs_review handling | Strong | Partial | partial | P1 | E4 | B80 messaging on show | in_progress |
| Admin dashboard | KPIs + quick actions | Strong | Partial | partial | P2 | E5 | `AgencyDashboardService` | in_progress |
| Admin booking ops | Command center + supplier actions | Strong | Partial | partial | P1 | E5 | B83/B84 sync; Sabre diagnostics | in_progress |
| Admin reports | Daily/weekly/monthly | Strong | Partial | partial | P2 | E5/E6 | Artisan + Phase 23C | in_progress |
| Admin users/agents/staff | CRUD + applications | Strong | Partial | partial | P2 | E5 | Admin controllers | in_progress |
| Supplier/API settings | Connections + diagnostics | Weak | Strong | better-than-IATI | P3 | — | Keep; do not regress | done |
| Notification templates | Per-event templates | Strong | Partial | partial | P1 | E6 | Phase 23C admin UI | in_progress |
| Payment settings | Methods, proof rules | Strong | Partial | partial | P2 | E8 | Agency/payment config | not_started |
| Documents | Template/generation settings | Strong | Partial | partial | P2 | E8 | `BookingDocumentService` | in_progress |
| Audit logs | Admin audit trail | Strong | Partial | partial | P2 | E5 | `AuditLog` model | in_progress |
| Ticketing readiness | Checklist + gated issue | Strong (live) | Disabled by policy | partial | P1 | E10 | OTA safer; match only when certified | not_started |
| Saved passengers | Traveler profile reuse | Strong | Missing | missing | P2 | E11 | — | not_started |
| CMS/pages/promo/settings | Marketing + promos | Strong | Partial | partial | P2 | E12 | Branding/homepage admin | not_started |

---

## 4. Permission / action matrix

**OTA current** = as of 2026-05-17 audit (routes/policies exist but UX may be incomplete). **Target phase** = when parity UX + rules should be complete.

| Action | Customer | Agent | Staff | Admin | OTA current | Target phase | Notes |
|--------|----------|-------|-------|-------|-------------|--------------|-------|
| View own booking | Yes | Agency bookings | Assigned/agency | All agency | Yes (scoped) | E2 | Customer portal + policies |
| Create booking | Yes (public/checkout) | Yes | No | Yes (assist) | Yes | E2/E3 | Public + agent paths |
| Upload payment proof | Own | Own bookings? | On behalf? | Yes | Partial | E8 | Confirm agent upload rules |
| Download invoice/receipt | Own | Agency | Agency | Agency | Partial | E8 | Policy on `BookingDocumentController` |
| Request cancellation | Own | Agency | Process | Approve | Partial | E2/E8 | Customer cancel exists |
| Create support ticket | Own | Own | Triage | Manage | No | E7 | New module |
| View agent bookings | — | Agency | Agency | Agency | Yes | E3 | Agent routes |
| Agent deposit request | — | Yes (IATI) | Approve? | Configure | No | E9 | New wallet ledger |
| Staff verify payment | — | — | Yes | Yes | Yes | E4/E8 | Staff payment controller |
| Staff add note | — | — | Yes | Yes | Yes | E4 | Booking notes |
| Admin create/retry PNR | — | — | — | Yes | Yes (gated) | E5/E10 | Sabre guards B77+ |
| Admin sync PNR itinerary | — | — | Yes | Yes | Yes | E5 | B84 `sync-pnr-itinerary` |
| Admin issue ticket | — | — | — | Yes | Gated/off | E10 | Ticketing disabled by default |
| Admin approve refund | — | — | Partial | Yes | Yes | E8 | Refund controllers |
| Admin manage users | — | — | — | Yes | Yes | E5 | Role middleware |
| Admin manage suppliers | — | — | — | Yes | Yes | — | `SupplierConnection` |
| Send communication | — | Limited | Yes | Yes | Yes | E6 | `OtaNotificationService` + logs |

---

## 5. UI inspiration checklist

Map IATI patterns to **OTA implementation** (Blade components + existing layout). Do not copy IATI CSS/HTML verbatim.

| IATI inspiration | OTA current | Recommended OTA implementation | Target phase | Priority |
|------------------|-------------|----------------------------------|--------------|----------|
| Sidebar module accordion | Flat nav | `dashboard` layout + collapsible sections per role | E2/E3/E4 | P2 |
| Wallet/credit sidebar panel | None | Balance card + link to deposit history (E9) | E9 | P1 |
| Recent bookings dashboard table | Partial (E1 empty states) | Reuse `section-header` + table partial on customer/agent home | E2/E3 | P1 |
| Support ticket list/thread | None | New `SupportTicket` views under customer/agent/staff | E7 | P1 |
| Booking status badges | Partial (`status-badge` component) | Map to `BookingOperationalStatus` labels | E2/E4/E5 | P1 |
| Document download area | Admin-heavy | Customer booking detail “Documents” card | E8 | P1 |
| Agent deposit history | None | Ledger table + status badges | E9 | P1 |
| Customer notification center | None | In-app list fed by notification log (read-only) | E6/E2 | P1 |
| Admin booking operation cards | Partial (supplier panel) | Card grid: PNR, sync, revalidate inspect links, ticketing readiness | E5 | P2 |
| Staff queue cards | List-only | KPI + “needs payment” / “needs review” quick filters | E4 | P1 |

---

## 6. Do-not-copy list

These IATI or legacy patterns must **not** be ported to OTA.

| Rule | Rationale |
|------|-----------|
| No SSL verification bypass | Security; use proper CA bundle and env TLS settings |
| No raw credential logging | Secrets in logs; use redacted digests only (Sabre inspect pattern) |
| No hardcoded PCC / IATA / agency values | Use `SupplierConnection` + `config/suppliers.sabre.*` |
| No session-only `ADMIN_AUTH` style access control | Use Laravel auth, roles, policies, middleware |
| No monolithic PHP route closures | Keep `routes/*.php` → controllers → services |
| No live ticketing shortcuts | Ticketing stays behind readiness checklist (E10) and config flags |
| No broad admin access for staff | Staff ≠ admin; separate route groups and policies |
| No copying IATI Sabre wire as default | OTA wire validated by contract tests; IATI compare is diagnostic only (`iati_like_*` styles) |
| No PII in notification payloads | `NotificationPayloadSanitizer` scope rules |
| No duplicate Mock supplier | Removed by design; use Duffel/Sabre test modes |

---

## 7. E-series checkpoint (2026-05-17)

**E0–E12 foundation complete.** Portal dashboards, notifications routing, support tickets, payment/document panels, agent wallet/deposits, saved travelers, ticketing readiness checklist, and admin settings/promo hub are shipped at foundation level. See `summary.md` changelog rows for each phase.

### Deferred to T1+ (not blocking E-series closure)

| Item | Notes |
|------|--------|
| CMS pages CRUD | Static marketing pages admin; E12 shipped settings hub + promo CRUD only |
| Promo checkout integration | `PromoCodeValidationService` exists; apply at checkout not wired |
| Agent credit enforcement | Wallet ledger + deposits exist; booking checkout does not debit wallet yet |
| WhatsApp | Out of scope for E6; email/SMTP path only |
| Live ticketing planning | E10 checklist UI only; live Sabre issue remains disabled by policy |

**Parallel (no UI):** Keep Sabre B-series diagnostics green; do not enable live ticketing until product certifies beyond E10 checklist.

**How to use this file (E0):**

- Before starting a phase, filter §3 by **Target phase**.
- When merging a PR, update **Status** and add a one-line note; optionally mirror a changelog row in `summary.md`.
- For permission changes, update §4 in the same PR.

---

## Appendix A — E1 completion record

| Item | Status |
|------|--------|
| `kpi-stat`, `quick-action`, `empty-state`, `section-header`, `status-badge` components | done |
| Customer / agent / staff dashboards wired | done |
| Admin quick-actions header aligned | done |

See `summary.md` changelog **2026-05-17 Dashboard UI E1**.

---

## Appendix B — OTA “better than IATI” (do not regress)

- Sabre B77 stale segment guard before live CPNR
- B80 operational status mapping (needs_review, rate limits, stale shop copy)
- B83 itinerary source honesty (snapshot vs synced PNR)
- B84 PNR itinerary sync (`SabrePnrItinerarySyncService`)
- Supplier connection encryption + multi-connection per provider
- Communication delivery log + resend controls (Phase 23C)
- Role-separated route groups (`/admin`, `/staff`, `/agent`, `/customer`)
