# Admin & Staff RBAC Matrix

Phase: **JETPK-DASH-01**  
Authority: [`RolePermissionMatrix`](../../app/Support/Access/RolePermissionMatrix.php), [`StaffPermission`](../../app/Support/Staff/StaffPermission.php), [`bootstrap/app.php`](../../bootstrap/app.php)

## Model

- **Not Spatie.** Permissions live on `users.account_type` and optional `users.meta.staff_permissions` / `agent_permissions`.
- **Platform admin** (`platform_admin`): sole account type for `/admin` routes.
- **Staff** (`staff`): `/staff` portal; granular keys via `StaffPermission` constants.
- **No `admin_staff` account type.** Sabre/booking `admin_staff_*` strings are operator context flags, not roles.

## Portal access (effective matrix)

| Area | platform_admin | staff | agent | customer |
|------|----------------|-------|-------|----------|
| Admin dashboard `/admin` | Allowed | **Denied** | Denied | Denied |
| Staff portal `/staff` | Denied | Allowed (granular) | Denied | Denied |
| Client page settings `/admin/page-settings` | Allowed | Limited (`staff.page_settings.manage` explicit) | Denied | Denied |

Full matrix rows: see `RolePermissionMatrix::areas()` and in-app page `admin.roles-permissions`.

## Middleware

| Alias | Class | Use |
|-------|-------|-----|
| `account.type` | `EnsureAccountType` | Portal gate |
| `staff.permission` | `EnsureStaffPermission` | Staff route actions |
| `platform.module` | `EnsurePlatformModuleRouteEnabled` | Feature modules |
| `agency.context` | `EnsureAgencyContext` | Agency scope |

## Staff permission keys (assignable)

From `StaffPermission::staffSelectable()`:

- Bookings: `staff.bookings.view`, `update_status`, `staff.bookings.notes`
- Payments: `record`, `verify`, `reject`
- Cancellations: `create`, `approve`, `process`
- Refunds: `create`, `approve`, `mark_paid`, `reject`
- Documents: `generate`, `download`
- Ticketing: `issue` (+ `platform.module:ticketing`)
- Support: `view`, `reply`, `status`
- Ledger: `view`, `manage`, `adjust`
- Reports: `view`, `export`
- Page settings: `staff.page_settings.manage`

Presets: `staff_manager`, `staff_operator`, `staff_support`.

**Legacy rule:** staff users **without** `meta.staff_permissions` key retain full staff access; empty array denies. Page settings requires **explicit** grant via `hasExplicitStaffPermission`.

## Policy highlights (admin dashboard)

| Policy / gate | platform_admin | staff |
|---------------|----------------|-------|
| `platform.admin` | Yes | No |
| `BookingPolicy` | Full | Limited by staff perms |
| `BookingPolicy::assignStaff` | Admin only | Denied |
| `UserManagementPolicy`, `SupplierConnectionPolicy`, `CmsPagePolicy`, most settings | Admin only | Denied |
| `SupportTicketPolicy`, ledger/reports policies | Admin | Staff (permission-gated) |

## Next.js `/testdash` (DASH-01)

Shared Admin + Staff UI is **preview-only** with no auth. Future phase must split nav visibility by `platform_admin` vs `staff` + `StaffPermission` and never expose admin-only mutations to staff sessions.
