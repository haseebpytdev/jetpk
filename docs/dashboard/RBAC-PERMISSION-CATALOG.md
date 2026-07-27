# RBAC Permission Catalog — JETPK-DASH-10 Prompt 02

Phase: **JETPK-DASH-10 Prompt 02**

Canonical source: [`dashboard/lib/access-control/permission-catalog.ts`](../../dashboard/lib/access-control/permission-catalog.ts)

This catalog defines **46 fixture permissions** for the JetPakistan dashboard preview. All entries have `implementationStatus: fixture`. Laravel Policies/Gates listed in **Laravel policy hint** are the target enforcement surface — not implemented in the Next preview.

**Risk levels:** `standard` (read/metadata), `elevated` (mutations/requests), `high` (approval, suspension, settings, export).

**Typical roles** reference fixture roles from [`dashboard/mocks/rbac-fixtures.ts`](../../dashboard/mocks/rbac-fixtures.ts) (**14 roles**). Abbreviations: SA = Super Administrator, OM = Operations Manager, BA = Booking Agent, TA = Ticketing Agent, FO = Finance Officer, CS = Customer Support, CM = Content Manager, AN = Analyst, RA = Read-only Auditor, AD = Administrator, PR = PNR Reviewer, NS = NDC Specialist, OB = Overly Broad Role.

---

## Implemented UI surfaces

The catalog is rendered across multiple preview pages. None of these surfaces enforce production authorization.

| Surface | Route | Component | What it shows |
|---------|-------|-----------|---------------|
| Permissions directory | `/testdash/users/permissions` | `PermissionsWorkspace` | Full **46-permission** table with domain, action, risk, assigned role count |
| Permission detail drawer | `/testdash/users/permissions?selected=JP-PRM-####` | `PermissionDetailDrawerContent` | Identity, classification, scope support, prerequisites, Laravel hint, assigned roles |
| Permission filters | `/testdash/users/permissions` | `PermissionsFilterBar` | Domain, action, risk, scope, prerequisite state, validation state |
| Permission matrix | `/testdash/users/roles` | `RolePermissionMatrix` | Grid of permissions × roles; `matrixDomain` and `matrixRole` URL filters |
| Role detail permissions | `/testdash/users/roles?selected=JP-ROL-####` | `RoleDetailDrawerContent` | Permission keys for selected role from `mockRolePermissions` |
| Permission assignment preview | Role detail drawer | `PermissionAssignmentPreview` | Client-side add/remove from all 46 keys; non-persistent |
| Effective access summary | User drawer, role drawer | `EffectiveAccessSummaryPanel` | Per-domain capability counts derived from role union |
| Access decision explainer | Role detail drawer | `AccessDecisionExplainer` | `evaluateAccessDecision()` for any catalog key against fixture user |
| Role comparison | `/testdash/users/roles?compareA=…&compareB=…` | `RoleComparisonPanel` | Unique/shared permission diff between two roles |
| User role preview | `/testdash/users?selected=JP-USR-####` | `RoleAssignmentPreview` | Role assignment preview (not permission-level) |
| Settings permission gates | `/testdash/settings/*` | Settings workspaces | `settings.view` / `settings.update` catalogued; UI is non-persistent regardless |
| Nav gating (future) | All `/testdash` routes | `nav-config.ts` | Laravel route hints; no client enforcement in preview |

**Services:**

| Service | File | Catalog usage |
|---------|------|---------------|
| Permissions module | `services/permission-service.ts` | `PERMISSION_CATALOG` source for table rows |
| Roles module | `services/role-service.ts` | Permission keys via `getRolePermissionKeys()` |
| Users module | `services/user-service.ts` | Effective access via `buildEffectiveAccessSummary()` |

**Query/filter libraries:**

- `lib/permissions/query-filters.ts` — permission table projection, facets, validation
- `lib/roles/query-filters.ts` — role permission key resolution from `ROLE_PERMISSION_MAP`
- `lib/access-control/effective-access.ts` — domain summaries from permission keys
- `lib/access-control/access-decision.ts` — single-permission allow/deny simulation
- `lib/access-control/permission-preview-validation.ts` — preview assignment rules

**Tests:** `dashboard/tests/users-access.foundation.spec.ts` asserts catalog integrity (46 permissions, high-risk classification, role-permission references). `dashboard/tests/users.smoke.spec.ts` asserts route shells for roles and permissions pages.

---

## Catalog

| Key | Domain | Action | Risk | Description | Typical roles | Prerequisite | Scope support | Laravel policy hint | Fixture status |
|-----|--------|--------|------|-------------|---------------|--------------|---------------|---------------------|----------------|
| `dashboard.view` | dashboard | view | standard | Access the operations dashboard overview. | All roles | — | all, own, branch, supplier | `Gate::define('dashboard.view')` | fixture |
| `bookings.view` | bookings | view | standard | View booking records and summaries. | SA, OM, BA, TA, CS, PR, RA, OB | — | all, own, branch, supplier | `BookingPolicy::view` | fixture |
| `bookings.create` | bookings | create | elevated | Create new booking records. | SA, OM, BA, OB | — | all, own, branch, supplier | `BookingPolicy::create` | fixture |
| `bookings.update` | bookings | update | elevated | Modify booking details. | SA, OM, BA, OB | — | all, own, branch, supplier | `BookingPolicy::update` | fixture |
| `bookings.cancel.request` | bookings | request | elevated | Submit booking cancellation requests. | SA, OM, OB | — | all, own, branch, supplier | `BookingPolicy::cancelRequest` | fixture |
| `bookings.cancel.approve` | bookings | approve | high | Approve or reject booking cancellation requests. | SA | `bookings.cancel.request` | all, own, branch, supplier | `BookingPolicy::cancelApprove` | fixture |
| `payments.view` | payments | view | standard | View payment ledger and transactions. | SA, OM, FO, RA, OB | — | all, own, branch, supplier | `PaymentPolicy::view` | fixture |
| `payments.record` | payments | create | elevated | Record payment entries. | SA, FO, OB | — | all, own, branch, supplier | `PaymentPolicy::record` | fixture |
| `payments.reconcile` | payments | manage | elevated | Reconcile payment records. | SA, FO, OB | — | all, own, branch, supplier | `PaymentPolicy::reconcile` | fixture |
| `payments.refund.request` | payments | request | elevated | Submit refund requests. | SA, FO, OB | — | all, own, branch, supplier | `PaymentPolicy::refundRequest` | fixture |
| `payments.refund.approve` | payments | approve | high | Approve or reject refund requests. | SA | `payments.refund.request` | all, own, branch, supplier | `PaymentPolicy::refundApprove` | fixture |
| `customers.view` | customers | view | standard | View customer profiles. | SA, OM, BA, CS, OB | — | all, own, branch, supplier | `CustomerPolicy::view` | fixture |
| `customers.update` | customers | update | elevated | Modify customer records. | SA, CS, OB | — | all, own, branch, supplier | `CustomerPolicy::update` | fixture |
| `suppliers.view` | suppliers | view | standard | View supplier connection metadata. | SA, OB | — | all, own, branch, supplier | `SupplierPolicy::view` | fixture |
| `suppliers.manage` | suppliers | manage | elevated | Manage supplier configuration metadata. | SA, OB | — | all, own, branch, supplier | `SupplierPolicy::manage` | fixture |
| `agents.view` | agents | view | standard | View agent accounts. | SA, OM, BA, OB | — | all, own, branch, supplier | `AgentPolicy::view` | fixture |
| `agents.manage` | agents | manage | elevated | Manage agent account metadata. | SA, OB | — | all, own, branch, supplier | `AgentPolicy::manage` | fixture |
| `pnrs.view` | pnrs | view | standard | View PNR and NDC order records. | SA, OM, TA, PR, NS, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `PnrPolicy::view` | fixture |
| `pnrs.review` | pnrs | view | elevated | Review PNR and order details. | SA, OM, TA, PR, NS, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `PnrPolicy::review` | fixture |
| `pnrs.cancel.request` | pnrs | request | elevated | Submit PNR or order cancellation requests. | SA, OM, PR, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `PnrPolicy::cancelRequest` | fixture |
| `pnrs.cancel.approve` | pnrs | approve | high | Approve PNR or order cancellation. | SA | `pnrs.cancel.request` | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `PnrPolicy::cancelApprove` | fixture |
| `tickets.view` | tickets | view | standard | View ticket and document records. | SA, OM, TA, CS, NS, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `TicketPolicy::view` | fixture |
| `tickets.review` | tickets | view | elevated | Review ticket issuance readiness. | SA, OM, TA, NS, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `TicketPolicy::review` | fixture |
| `tickets.issue.request` | tickets | request | elevated | Submit ticket issuance requests. | SA, OM, TA, NS, OB | — | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `TicketPolicy::issueRequest` | fixture |
| `tickets.issue.approve` | tickets | approve | high | Approve ticket issuance requests. | SA | `tickets.issue.request` | all, channel:gds, channel:ndc, channel:oneApi, channel:manual, channel:mock, own, branch | `TicketPolicy::issueApprove` | fixture |
| `reports.view` | reports | view | standard | Access analytics and reports. | SA, OM, FO, AN, RA, OB | — | all, own, branch, supplier | `ReportPolicy::view` | fixture |
| `reports.export` | reports | export | elevated | Export report data. | SA, FO, AN, OB | — | all, own, branch, supplier | `ReportPolicy::export` | fixture |
| `cms.view` | cms | view | standard | View CMS content records. | SA, CM, OB | — | all, own, branch, supplier | `CmsPolicy::view` | fixture |
| `cms.preview` | cms | view | standard | Preview CMS content locally. | SA, CM, OB | — | all, own, branch, supplier | `CmsPolicy::preview` | fixture |
| `cms.edit` | cms | update | elevated | Edit CMS content drafts. | SA, CM, OB | — | all, own, branch, supplier | `CmsPolicy::edit` | fixture |
| `cms.review` | cms | view | elevated | Review CMS content changes. | SA, CM, OB | — | all, own, branch, supplier | `CmsPolicy::review` | fixture |
| `cms.publish.request` | cms | request | elevated | Submit CMS publication requests. | SA, CM, OB | — | all, own, branch, supplier | `CmsPolicy::publishRequest` | fixture |
| `cms.publish.approve` | cms | approve | high | Approve CMS publication. | SA | `cms.publish.request` | all, own, branch, supplier | `CmsPolicy::publishApprove` | fixture |
| `users.view` | users | view | standard | View dashboard user directory. | SA, AD, OB | — | all, own, branch, supplier | `UserPolicy::view` | fixture |
| `users.invite` | users | invite | elevated | Invite new dashboard users. | SA, AD, OB | — | all, own, branch, supplier | `UserPolicy::invite` | fixture |
| `users.update` | users | update | elevated | Update user profile metadata. | SA, AD, OB | — | all, own, branch, supplier | `UserPolicy::update` | fixture |
| `users.suspend` | users | suspend | high | Suspend user accounts. | SA | — | all, own, branch, supplier | `UserPolicy::suspend` | fixture |
| `users.assignRoles` | users | assign | high | Assign roles to users. | SA, AD, OB | — | all, own, branch, supplier | `UserPolicy::assignRoles` | fixture |
| `roles.view` | roles | view | standard | View role definitions. | SA, AD, OB | — | all, own, branch, supplier | `RolePolicy::view` | fixture |
| `roles.create` | roles | create | elevated | Create custom roles. | SA, AD, OB | — | all, own, branch, supplier | `RolePolicy::create` | fixture |
| `roles.update` | roles | update | elevated | Update role definitions. | SA, AD, OB | — | all, own, branch, supplier | `RolePolicy::update` | fixture |
| `roles.assignPermissions` | roles | assign | high | Assign permissions to roles. | SA | — | all, own, branch, supplier | `RolePolicy::assignPermissions` | fixture |
| `settings.view` | settings | view | standard | View system settings metadata. | SA, AD, OB | — | all, own, branch, supplier | `SettingPolicy::view` | fixture |
| `settings.update` | settings | update | high | Update system settings. | SA, AD, OB | — | all, own, branch, supplier | `SettingPolicy::update` | fixture |
| `audit.view` | audit | view | standard | View audit event history. | SA, AD, RA, OB | — | all, own, branch, supplier | `AuditPolicy::view` | fixture |
| `audit.export` | audit | export | high | Export audit events. | SA | — | all, own, branch, supplier | `AuditPolicy::export` | fixture |

---

## High-risk permissions (10)

Flagged by `isHighRisk: true` in the catalog:

| Key | Prerequisite | Notes |
|-----|--------------|-------|
| `bookings.cancel.approve` | `bookings.cancel.request` | Separation of request vs approve |
| `payments.refund.approve` | `payments.refund.request` | Finance approval gate |
| `pnrs.cancel.approve` | `pnrs.cancel.request` | Channel-aware |
| `tickets.issue.approve` | `tickets.issue.request` | Channel-aware |
| `cms.publish.approve` | `cms.publish.request` | Content go-live gate |
| `users.suspend` | — | Account lifecycle |
| `users.assignRoles` | — | Privilege escalation surface |
| `roles.assignPermissions` | — | Super-admin only in fixtures |
| `settings.update` | — | Platform configuration |
| `audit.export` | — | Compliance data egress |

## Channel-aware permissions

PNR and ticket permissions support additional scopes: `channel:gds`, `channel:ndc`, `channel:oneApi`, `channel:manual`, `channel:mock`. Fixture roles `Ticketing Agent` (GDS), `PNR Reviewer` and `NDC Specialist` (NDC) demonstrate scoped assignments via `scopeForRole()`.

## Domain groups

| Domain | Permission count | Primary Laravel policy |
|--------|------------------|------------------------|
| dashboard | 1 | Gate |
| bookings | 5 | BookingPolicy |
| payments | 5 | PaymentPolicy |
| customers | 2 | CustomerPolicy |
| suppliers | 2 | SupplierPolicy |
| agents | 2 | AgentPolicy |
| pnrs | 4 | PnrPolicy |
| tickets | 4 | TicketPolicy |
| reports | 2 | ReportPolicy |
| cms | 6 | CmsPolicy |
| users | 5 | UserPolicy |
| roles | 4 | RolePolicy |
| settings | 2 | SettingPolicy |
| audit | 2 | AuditPolicy |

## Validation expectations

When assigning permissions to roles in future Laravel integration:

1. Approval permissions should include their prerequisite request permission (warning code `ROLE_HIGH_RISK_NO_PREREQUISITE`).
2. Duplicate keys and empty role permission sets are blocking errors.
3. Users with ≥6 high-risk permissions trigger least-privilege review (`USER_EXCESSIVE_HIGH_RISK`).

See [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md) for validation architecture and RBAC UI component map.

## Related documentation

- [`USERS-RBAC-ARCHITECTURE.md`](./USERS-RBAC-ARCHITECTURE.md) — roles/permissions pages, matrix, comparison, preview
- [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md) — `settings.view` / `settings.update` contracts
- [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md) — protected fields
