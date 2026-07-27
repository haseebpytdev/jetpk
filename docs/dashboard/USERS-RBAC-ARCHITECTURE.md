# Users & RBAC Domain Architecture — JETPK-DASH-10 Prompt 02

Phase: **JETPK-DASH-10 Prompt 02** (RBAC UI foundation)

## Overview

The JetPakistan dashboard preview defines a **fixture-backed access-control domain** for user directory, role definitions, permission catalog, effective-access computation, and validation. This layer models how Laravel will eventually enforce RBAC; it does **not** authenticate users or persist changes.

| Layer | Location | Role |
|-------|----------|------|
| Production auth | Laravel `/admin`, `/staff` | Authoritative session, policies, gates |
| Preview UI | `/testdash/users`, `/testdash/users/roles`, `/testdash/users/permissions` | Fixture presentation + local preview tools |
| Access contracts | `dashboard/lib/access-control/` | Shared types, catalog, validation, decisions |
| Fixtures | `dashboard/mocks/user-fixtures.ts`, `rbac-fixtures.ts` | Deterministic users, roles, role-permissions |
| Services | `dashboard/services/user-service.ts`, `role-service.ts`, `permission-service.ts` | Fixture reads via `useMockData()` guard |

Reference date for fixtures: `2026-07-01T00:00:00.000Z`.

## Domain model

### User

Represents a dashboard operator (staff or platform admin). Key surfaces:

- **Profile** — `fullName`, `displayName`, `department`, `jobTitle`, `userType`
- **Contact** — `email`, optional `phone` (preview domain: `@staff-preview.jetpakistan.example`)
- **Security** — `status`, `verificationState`, `mfaState`, `invitationState`, `failedSignInCount`, `activeSessionCount`, `mfaRequired`
- **Session summary** — counts and masked last-sign-in location only (no session tokens)
- **Assigned roles** — `UserRoleAssignment[]` with `roleId`, `assignedAt`, `assignedBy`, `source`
- **Effective access** — derived summary from role union (see below)
- **Validation** — `validationState` + `validationIssues[]` from `validateUser()`

Types: `dashboard/types/access-control.ts`.

### Role

Named permission bundle with metadata:

- **Identity** — `id` (`JP-ROL-####`), `key`, `name`, `description`
- **Classification** — `category` (system, operations, finance, content, analytics, audit, custom), `scope` (e.g. `allRecords`, `ownRecords`, `gdsOnly`, `ndcOnly`)
- **Protection** — `isSystem`, `isProtected` (Super Administrator, Read-only Auditor)
- **Counts** — `assignedUserCount`, `permissionCount`, `permissionGroups[]`
- **Lifecycle** — `status`, `revision`, `lastEditor`, `validationState`

**14 fixture roles** including intentional edge cases (`Incomplete Role` `JP-ROL-0013`, `Overly Broad Role` `JP-ROL-0014`).

### Permission

Atomic capability keyed by dot notation (`domain.action` or `domain.subAction.action`):

- **Catalog entry** — `id` (`JP-PRM-####`), `key`, `domain`, `action`, `label`, `description`
- **Risk** — `standard` | `elevated` | `high`; `isHighRisk` flag for approval-gated actions
- **Prerequisites** — approval permissions may require a request permission (e.g. `bookings.cancel.approve` → `bookings.cancel.request`)
- **Scope support** — `supportedScopes[]`; channel-aware permissions add `channel:*` scopes
- **Laravel hint** — `laravelPolicyHint` maps to future Policy/Gate method
- **Status** — `implementationStatus: "fixture"` for all **46 catalog entries**

Source of truth: `dashboard/lib/access-control/permission-catalog.ts`.

### RolePermission (assignment)

Join between role and permission:

```text
roleId + permissionKey + effect (allow) + scope (all | own | branch | supplier | channel:*)
```

Effective user access is the **union** of permissions from all assigned roles. Multi-role evaluation uses `combineMultiRoleDecisions()` — any granting role wins; approval-required permissions surface `requiresApproval: true`.

## Assignment flow

```text
User ──assignedRoles[]──► Role ──mockRolePermissions[]──► Permission
                                    │
                                    └── scope per assignment
```

1. User seeds declare `roleIds[]` in `user-fixtures.ts`.
2. `buildRoleAssignments()` creates `UserRoleAssignment` records.
3. `buildEffectiveAccessSummary(roleIds)` aggregates permission keys and domain summaries.
4. `evaluateAccessDecision()` / `evaluateMultiRoleAccess()` simulate allow/deny for a permission key (fixture-only).

Role assignment **preview** (user drawer) and permission assignment **preview** (role drawer) are client-side only; changes are not saved.

## Effective access

`buildEffectiveAccessSummary()` produces:

- Per-domain counts and capability flags (`viewAccess`, `requestAccess`, `approvalAccess`, `manageAccess`, `exportAccess`, `highRiskCount`)
- `totalPermissions`, `highRiskPermissions[]`, source `roleIds`

Domain order follows operational priority: dashboard → bookings → payments → … → audit.

## Validation

Pure functions in `dashboard/lib/access-control/access-validation.ts`:

| Function | Target | Notable rules |
|----------|--------|---------------|
| `validateUser()` | User | No roles, duplicate roles, suspended + active sessions, MFA required but disabled, stale invitation, excessive high-risk (≥6), missing department |
| `validateRole()` | Role + permission keys | Empty permissions, duplicates, protected role modified, excessive domains (≥10), high-risk without prerequisite, name conflict |
| `validatePermission()` | Permission | Unknown domain/action, empty scopes, duplicate key, approval without prerequisite |
| `validateRoleAssignmentPreview()` | User role preview | Empty preview, duplicates, protected role on first assignment, excessive high-risk, ops + audit conflict |
| `validatePermissionAssignmentPreview()` | Role permission preview | Duplicates, protected role modification, missing prerequisites, excessive high-risk, empty preview |

Issues carry `severity`, `code`, `fieldPath`, `blocking`, and `suggestedResolution`. Blocking issues set entity `validationState` to `blocked`.

Permission preview validation: `dashboard/lib/access-control/permission-preview-validation.ts`.

## Route map

All routes are prefixed by `basePath: /testdash` in production build.

| Route | Module key | Purpose | Prompt 02 status |
|-------|------------|---------|------------------|
| `/testdash/users` | `directory` | User directory, filters, detail drawer | Live |
| `/testdash/users/roles` | `roles` | Role directory, matrix, comparison, permission preview | Live |
| `/testdash/users/permissions` | `permissions` | Permission catalog, filters, detail drawer | Live |
| `/testdash/settings` | — | Settings overview | Live |
| `/testdash/settings/general` | — | Organization display metadata | Live |
| `/testdash/settings/security` | — | MFA/password policy metadata | Live |
| `/testdash/settings/notifications` | — | Alert channel metadata | Live |
| `/testdash/settings/integrations` | — | Supplier status metadata (no credentials) | Live |
| `/testdash/audit` | — | Audit event timeline (preview-only events) | Live |

Nav registration: `dashboard/lib/nav-config.ts` under **Insights & system**.

### Roles page query parameters

Parsed by `dashboard/lib/roles-query.ts`:

| Param | Purpose |
|-------|---------|
| `search`, `category`, `status`, `roleType`, `protected`, `risk` | Filters |
| `validationState`, `channelScope`, `assignedState` | Filters |
| `page`, `pageSize`, `sort`, `direction` | Pagination and sort |
| `selected` | Role detail drawer (`JP-ROL-####`) |
| `compareA`, `compareB` | Role comparison panel |
| `matrixDomain`, `matrixRole` | Permission matrix filters |
| `previewError`, `previewLoading`, `previewEmpty` | QA simulation |

### Permissions page query parameters

Parsed by `dashboard/lib/permissions-query.ts`:

| Param | Purpose |
|-------|---------|
| `search`, `domain`, `action`, `risk`, `scope` | Filters |
| `prerequisite`, `assignedRoleState`, `validationState` | Filters |
| `page`, `pageSize`, `sort`, `direction` | Pagination and sort |
| `selected` | Permission detail drawer (`JP-PRM-####`) |
| `previewError`, `previewLoading`, `previewEmpty` | QA simulation |

## Fixture strategy

| Fixture file | Contents | Count (Prompt 02) |
|--------------|----------|-------------------|
| `user-fixtures.ts` | Users covering status/MFA/role edge cases | 40 |
| `rbac-fixtures.ts` | Roles, role-permission map, scope rules | **14 roles** |
| `permission-catalog.ts` | Canonical permission catalog | **46 permissions** |
| `audit-fixtures.ts` | Preview audit events with masked IPs | — |
| `settings-fixtures.ts` | Settings section payloads | 4 sections |

**Determinism rules:**

- Stable IDs (`JP-USR-####`, `JP-ROL-####`, `JP-PRM-####`)
- JetPakistan branding only; emails use `@staff-preview.jetpakistan.example`
- Validation edge cases are intentional (e.g. `JP-USR-0012` no roles, `JP-USR-0015` suspended + sessions, `JP-USR-0037` duplicate roles)
- Channel scopes applied in `scopeForRole()` for GDS/NDC ticketing roles

**Service boundary:** `user-service.ts`, `role-service.ts`, `permission-service.ts` read fixtures via `useMockData()` guard; live mode throws service errors.

## Frontend architecture

### Users directory

```text
UsersPageContent (server)
  → parseUsersQuery() / getUsersModule()
  → UsersModuleShell
      → sub-nav: Users | Roles | Permissions
      → UsersWorkspace (client)
          → UsersSummaryMetrics
          → UsersFilterBar / UsersActiveFilters
          → UsersDataTable + Pagination
          → UserDetailDrawer
              → UserSecuritySummary
              → EffectiveAccessSummaryPanel
              → AccessValidationSummary
              → RoleAssignmentPreview (non-persistent)
```

### Roles page

```text
RolesPageContent (server)
  → parseRolesQuery() / getRolesModule()
  → UsersModuleShell (module="roles")
      → RolesWorkspace (client)
          → RolesSummaryMetrics
          → RolesFilterBar / RolesActiveFilters
          → RolesDataTable + RoleMobileCard + Pagination
          → RolePermissionMatrix          [matrixDomain, matrixRole URL params]
          → RoleDetailDrawer
              → Role identity, classification, lifecycle
              → EffectiveAccessSummaryPanel
              → AccessValidationSummary
              → PermissionAssignmentPreview  [non-persistent]
              → RoleComparisonPanel          [compareA, compareB URL params]
              → AccessDecisionExplainer      [fixture user + permission selector]
```

**Key files:**

| Component | Path |
|-----------|------|
| Roles workspace | `features/roles/roles-workspace.tsx` |
| Permission matrix | `features/roles/components/role-permission-matrix.tsx` |
| Role comparison | `features/roles/components/role-comparison-panel.tsx` |
| Permission preview | `features/roles/components/permission-assignment-preview.tsx` |
| Access decision explainer | `features/roles/components/access-decision-explainer.tsx` |
| Role detail drawer | `features/roles/components/role-detail-drawer.tsx` |
| Comparison logic | `lib/access-control/role-comparison.ts` |

### Permissions page

```text
PermissionsPageContent (server)
  → parsePermissionsQuery() / getPermissionsModule()
  → UsersModuleShell (module="permissions")
      → PermissionsWorkspace (client)
          → PermissionsSummaryMetrics
          → PermissionsFilterBar / PermissionsActiveFilters
          → PermissionsDataTable + PermissionMobileCard + Pagination
          → PermissionDetailDrawer
              → Identity, classification, scope support
              → Assigned roles list
              → Prerequisite chain
              → Laravel policy hint
              → AccessValidationSummary
```

**Key files:**

| Component | Path |
|-----------|------|
| Permissions workspace | `features/permissions/permissions-workspace.tsx` |
| Permission detail drawer | `features/permissions/components/permission-detail-drawer.tsx` |
| Query filters | `lib/permissions/query-filters.ts` |
| Permissions service | `services/permission-service.ts` |

## RBAC UI surfaces (Prompt 02)

### Permission matrix

`RolePermissionMatrix` (`features/roles/components/role-permission-matrix.tsx`):

- Read-only grid of **46 permissions** × up to 6 visible roles from current filter page
- Domain filter (`matrixDomain` URL param) narrows to one of 14 permission domains
- Role filter (`matrixRole` URL param) highlights a single role's grants
- Uses `getRolePermissionKeys()` from `lib/roles/query-filters.ts` against `mockRolePermissions` in `rbac-fixtures.ts`
- Copy: *"Read-only matrix of fixture role permissions by domain. No mutations are persisted."*

### Role comparison

`RoleComparisonPanel` (`features/roles/components/role-comparison-panel.tsx`):

- Activated via `?compareA=JP-ROL-####&compareB=JP-ROL-####` query parameters
- Powered by `compareRoles()` in `lib/access-control/role-comparison.ts`
- Side-by-side metrics: permission count, domain coverage, view/request/approval/manage/export access
- Lists unique permissions per role and shared permissions
- High-risk permission diff between roles

### Permission assignment preview

`PermissionAssignmentPreview` (`features/roles/components/permission-assignment-preview.tsx`):

- Client-side only; lives in role detail drawer
- Add/remove permissions from a local preview set (not fixture)
- Validates via `validatePermissionAssignmentPreview()` — duplicates, protected role warnings, missing prerequisites, excessive high-risk
- Shows `EffectiveAccessSummaryPanel` and `AccessValidationSummary` for preview state
- Reset restores fixture permission keys; refresh clears all preview changes
- Amber banner: *"Permission preview only — changes are local and not persisted."*

### Access decision explainer

`AccessDecisionExplainer` (`features/roles/components/access-decision-explainer.tsx`):

- Lives in role detail drawer; uses first assigned user (or `JP-USR-0001` fallback)
- Permission selector across all **46 catalog entries**
- Calls `evaluateAccessDecision()` from `lib/access-control/access-decision.ts`
- Displays: allowed/denied, reason code, source role, scope, `requiresApproval` flag
- Copy: *"Fixture-only decision … Laravel policies remain authoritative."*

## Supporting libraries

- `lib/users-query.ts`, `lib/roles-query.ts`, `lib/permissions-query.ts` — URL query parsing
- `lib/users/query-filters.ts`, `lib/roles/query-filters.ts`, `lib/permissions/query-filters.ts` — filter, sort, pagination
- `lib/access-control/*` — catalog, effective access, validation, access decisions, role comparison, permission preview validation
- `lib/access-control/settings-contracts.ts` — read-only settings field definitions

Settings module: see [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md).

Audit module: see [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md).

## Audit cross-links

RBAC and settings actions in fixtures emit corresponding audit events in `mocks/audit-fixtures.ts` (**60 events**). Key relationships:

| RBAC / settings surface | Audit event types | Target cross-link |
|-------------------------|-------------------|-------------------|
| User directory drawer | `user.recordViewed`, `user.rolePreviewOpened`, `user.rolePreviewApplied` | `target.type: user` → `/users?selected=` |
| Roles page | `role.viewed`, `role.comparisonOpened`, `permission.matrixViewed` | `target.type: role` → `/users/roles?selected=` |
| Permissions page | `permission.catalogueViewed`, `permission.assignmentPreviewOpened` | `target.type: permission` |
| Access decision explainer | `permission.authorizationPreviewEvaluated` | `category: security` |
| Settings workspaces | `settings.viewed`, `settings.*PreviewChanged` | `target.type: setting` → `/settings/{section}` |
| Security validation | `security.highRiskPermissionDetected`, `security.protectedRoleReview` | `category: security` |

Permissions: `audit.view` (read timeline), `audit.export` (future server export — SA only). Full event model, privacy rules, and export manifest: [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md).

## Non-persistence boundary

The preview **must not** be treated as an auth or RBAC admin backend.

| Allowed (Prompt 02) | Prohibited |
|---------------------|------------|
| Read fixture users, roles, permissions | POST/PATCH/DELETE to Laravel user or role endpoints |
| Display effective access and validation | Persist role or permission preview changes |
| Simulate access decisions in memory | Store passwords, MFA secrets, session IDs, API tokens |
| Local-preview settings edits (client state) | Apply settings changes to Laravel or database |
| URL filters, drawer selection, preview flags | Bypass Laravel policies in production |
| Role comparison and matrix visualization | Export live RBAC data |

Enforced by:

- `useMockData()` in services
- `assertPreviewSafe()` / `mutationsAllowed()` in `lib/preview.ts`
- UI copy: PreviewDataBanner and fixture-only disclaimers
- Playwright smoke tests asserting no password/secret strings in DOM

**Laravel remains the future authoritative layer** for authentication, policy evaluation, role mutation, session revocation, and audit generation. The dashboard catalog and validation rules are **contracts** to align with Laravel Policies, Gates, and `StaffPermission` middleware — not replacements.

## Related documentation

- [`DASHBOARD-AUDIT-ARCHITECTURE.md`](./DASHBOARD-AUDIT-ARCHITECTURE.md) — audit timeline, export manifest, privacy
- [`RBAC-PERMISSION-CATALOG.md`](./RBAC-PERMISSION-CATALOG.md) — full 46-permission catalog and UI surfaces
- [`USERS-SECURITY-AND-PRIVACY-RULES.md`](./USERS-SECURITY-AND-PRIVACY-RULES.md) — protected fields and privacy rules
- [`DASHBOARD-SETTINGS-ARCHITECTURE.md`](./DASHBOARD-SETTINGS-ARCHITECTURE.md) — settings domain
- [`DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`](./DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md) — migration sequence
- [`auth-rbac-integration-plan.md`](./auth-rbac-integration-plan.md) — earlier DASH-01 planning notes
