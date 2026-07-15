# Phase 0 · Document 05 — Navigation, Role Separation & Module Access Map

> **REVISION 1** — Adds explicit **four-way role separation** (Agent vs Agent Staff
> vs Internal Staff vs Platform Admin), a dedicated **Agent Staff navigation /
> permission-denied map**, and the **Authenticated-Entry redirect/denied** surfaces.
> No role is treated as a monolith.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> Navigation visibility is a function of **three independent gates** — account type
> (role), per-tenant platform module, and fine-grained permission
> (agent/staff). The redesign must preserve all three: no nav item may become
> visible to a role/tenant/permission that could not previously reach it.

---

## 1. Role separation (explicit)

| Role | `account_type` | Route surface | Fine-grained gate | Console character |
|---|---|---|---|---|
| **Customer** | `customer` | `/customer/*` | — (+ `customer.email.portal.verified`) | Consumer self-service |
| **Agent** | `agent` | `/agent/*` | agency-admin functions via `agent.admin`; feature perms via `agent.permission` | Agency administrator |
| **Agent Staff** | `agent_staff` | `/agent/*` (**shared with Agent**) | `agent.permission:*` (permission-limited) | Sub-user of an agency; no admin-only routes |
| **Internal Staff** | `staff` | `/staff/*` | `staff.permission:*` | Internal operational console |
| **Platform Admin** | `platform_admin` | `/admin/*` (+ `/admin-page-settings` shared with staff) | module + policy | Full operations + settings |

Key distinctions the redesign must honour:
- **Agent vs Agent Staff** share the **same route surface and views**. They differ
  only by **permission** → gated nav + gated in-page actions + a permission-denied
  state. **No duplicate Agent-Staff views.** (Base guard on `agent.php` is
  `account.type:agent,agent_staff`, so both enter; `agent.permission:*` and
  `agent.admin` then differentiate.)
- **Internal Staff (`staff`)** is a **separate console** at `/staff/*` — not the
  agent surface — but **reuses some Admin views** (`dashboard.admin.ledger.*`,
  `dashboard.admin.reports`); preserve that reuse rather than forking.
- **Platform Admin** owns `/admin/*` plus the shared `/admin-page-settings`
  surface (`account.type:platform_admin,staff`).

---

## 2. Account types (`account.type` → `EnsureAccountType`)

| Route file | Base guard |
|---|---|
| `routes/customer.php` | `web, auth, agency.context, account.type:customer, customer.email.portal.verified` |
| `routes/agent.php` | `web, auth, agency.context, account.type:agent,agent_staff` |
| `routes/staff.php` | `web, auth, agency.context, account.type:staff` |
| `routes/admin.php` | `web, auth, agency.context, account.type:platform_admin` |
| `routes/admin-page-settings.php` | `web, auth, agency.context, account.type:platform_admin,staff` |

---

## 3. Platform modules (`platform.module:<key>` → `EnsurePlatformModuleRouteEnabled`)

Per-tenant on/off; a disabled module throws `PlatformModuleDisabledException`.
**23 keys in use:**

Customer/public: `customer_portal`, `customer_checkout`, `customer_booking_lookup`,
`customer_registration`, `saved_travelers`, `support_system`, `payment_proofs`,
`public_flight_search`, `public_umrah_groups`.
Agent: `agent_wallet`, `agent_deposits`, `agent_ledger`, `agent_reports`,
`agent_staff`, `agent_support`, `agent_applications`.
Finance/ops/admin: `finance_reports`, `supplier_booking`, `ticketing`,
`api_settings`, `branding_settings`, `markup_settings`, `notifications`.

Nav items render only when `PlatformModuleGate::visible('<key>')` is true. Preserve
this call in every nav partial for every role.

---

## 4. Agent permissions (`agent.permission:<AgentPermission::*>`)

13 constants (`app/Support/Agents/AgentPermission.php`):
`AgencyView` (`agency.show`), `AgencyEdit` (`agency.edit/update`), `StaffManage`
(all `staff.*` + `agent_staff` module), `BookingsView` (`bookings.index/show`,
`cancellations`), `BookingsCreate` (`bookings.create/store/exit-mode`),
`WalletView` (`wallet.show`, `deposits.index`), `LedgerView` (`ledger.index`,
`accounting.ledger.*`), `LedgerManage`, `ReportsView` (`reports.index`),
`PaymentsUpload` (`payment-proof`, `deposits.create/store`), `TravelersManage`
(`travelers.*`), `SupportManage` (`support.tickets.*`), `ProfileManage`.
`agent.admin` (`EnsureAgentAdmin`) additionally gates `commissions.*` (agency-admin
only).

## 5. Staff permissions (`staff.permission:<StaffPermission::*>`)

28 constants (`app/Support/Staff/StaffPermission.php`): `BookingsView`,
`BookingsUpdateStatus`, `BookingsNotes`, `PaymentsRecord`, `PaymentsVerify`,
`PaymentsReject`, `CancellationsCreate`, `CancellationsApprove`,
`CancellationsProcess`, `RefundsCreate`, `RefundsApprove`, `RefundsMarkPaid`,
`RefundsReject`, `DocumentsGenerate`, `DocumentsDownload`, `TicketingIssue`,
`SupportView`, `SupportReply`, `SupportStatus`, `LedgerView`, `LedgerManage`,
`LedgerAdjust`, `ReportsView`, `ReportsExport`, `PageSettingsManage`,
`PresetManager`, `PresetOperator`, `PresetSupport`.

---

## 6. Agent Staff navigation & permission-denied map (Rev 1 — Phase 6)

Agent Staff sees the Agent sidebar **filtered by permission**. Link → required
permission → visible? And what happens on a **deep-link** to a denied page.

| Agent nav link | Required permission | Visible for Agent Staff? | Deep-link when denied |
|---|---|---|---|
| Dashboard | base | Always | n/a |
| Bookings (list/detail) | `BookingsView` | If granted | permission-denied state (P-1) |
| New booking | `BookingsCreate` | If granted | create action hidden; denied on route |
| Wallet | `WalletView` (+ `agent_wallet`) | If granted | denied state |
| Deposits | `WalletView`/`PaymentsUpload` (+ `agent_deposits`) | If granted | denied state |
| Ledger / Accounting | `LedgerView` (+ `agent_ledger`) | If granted | denied state |
| Reports | `ReportsView` (+ `agent_reports`) | If granted | denied state |
| Travelers | `TravelersManage` (+ `saved_travelers`) | If granted | denied state |
| Support | `SupportManage` (+ `agent_support`) | If granted | denied state |
| Staff management | `StaffManage` (+ `agent_staff`) | Rare (delegated) | denied state |
| Agency profile (view) | `AgencyView` | Usually | denied state |
| Agency profile (edit) | `AgencyEdit` | Rare | edit hidden; denied on route |
| Commissions | `agent.admin` (agency-admin) | **Never** (unless admin) | not shown; denied on route |

**Rules for Phase 6:**
1. Every hidden nav link must also be enforced at the route (guard already does
   this) **and** at the in-page action (`@can`/permission check) — hiding the link
   is not sufficient.
2. Pages that differ only by permission render the **same component** with actions
   gated — no `agent_staff`-specific view files.
3. The permission-denied state (component gap **P-1**, Document 03) is the single
   canonical denied UX, shared with the auth access-denied surface.

---

## 7. Navigation ownership (partials → role)

| Nav surface | Partial(s) | Gates |
|---|---|---|
| Customer desktop | `dashboard-sidebar-customer` + inline nav in `customer-account` | `PlatformModuleGate::visible()` |
| Agent desktop | `dashboard-sidebar-agent`, `agent-portal-nav` | module + `AgentPermission` |
| Agent Staff | *(same Agent partials, permission-filtered)* | module + `AgentPermission` (+ denied state) |
| Internal Staff desktop | `dashboard-sidebar-staff` | module + `StaffPermission` |
| Platform Admin desktop | `dashboard-sidebar-admin` | `account.type:platform_admin` + module |
| Guest | `dashboard-sidebar-guest` | — |
| Mobile (Customer/Agent only) | `mobile-app-top-bar`, `mobile-app-bottom-nav` | same gates, mobile presentation |

(Staff/Admin have no mobile nav — desktop shell only.)

---

## 8. Customer nav (verified, reference pattern)

From `layouts/customer-account.blade.php` (`$customerNavItems`), each gated by
`module` and linked via `client_route()`:

| Label | Route | Match | Module |
|---|---|---|---|
| Overview | `customer.dashboard` | `customer.dashboard` | `customer_portal` |
| Bookings | `customer.bookings.index` | `customer.bookings.*` | `customer_portal` |
| Travelers | `customer.travelers.index` | `customer.travelers.*` | `saved_travelers` |
| Payments | `customer.bookings.index` (`?filter=pending_payment`) | `customer.bookings.*` | `customer_portal` |
| Support Tickets | `customer.support.tickets.index` | `customer.support.tickets.*` | `support_system` |
| Profile Settings | `profile.edit` | `profile.*` | — (shared) |

This data-driven, gated, named-route pattern is the target all role sidebars
converge on during consolidation.

---

## 9. Authenticated-Entry redirects & denied surfaces (Rev 1)

Shared UI dependencies that steer users into the correct console (no auth-logic
change):

| Surface | Behaviour | UI owner |
|---|---|---|
| Post-login role redirect | Land each `account_type` on its console home (`customer.dashboard` / `agent.dashboard` / `staff.dashboard` / `admin` home) | session/redirect (logic preserved) |
| Forced password change | Interstitial before any dashboard route | `ForcePasswordChangeController` view |
| Email verification gate | `customer.email.portal.verified` blocks customer portal until verified | verification prompt view |
| Module disabled | `PlatformModuleDisabledException` → denied state | shared **P-1** denied component |
| Access denied (role/permission) | Deep-link to a route the user can't reach → denied state | shared **P-1** denied component |
| Inactive / suspended account | Blocked-state screen | account-state view |
| Session expiry | Redirect to `client_route('login')` | login view |

---

## 10. Role-aware navigation rules (verify after redesign)

1. Customer sees Customer sections only.
2. Agent sees Agent sections; **Agent Staff** sees the Agent surface filtered by
   `AgentPermission`, with `agent.admin` items hidden.
3. Internal Staff nav respects `StaffPermission` on the `/staff` console.
4. Admin nav preserves all administrative modules on `/admin`.
5. Every item is additionally hidden if its `platform.module` is disabled.
6. No `Sign In` / `Register` in authenticated shells; profile dropdown shows
   name + role + Profile + Security/Password + Logout across all roles.

---

## 11. Phase-0 disposition

Documentation only. **No nav partial, permission, module gate, or route is
modified in Phase 0.** This map is the authorization/visibility baseline; after
each implementation phase, re-verify that every nav item's (role × module ×
permission) visibility is unchanged — including the Agent-Staff permission matrix
in §6.
