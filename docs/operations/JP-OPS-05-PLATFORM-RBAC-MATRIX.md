# JP-OPS-05 Platform RBAC Matrix

## Authority sources

| Layer | Location |
|-------|----------|
| Staff permission keys | `App\Support\Staff\StaffPermission` |
| Dashboard read keys | `DashboardPermissionResolver` |
| Portal routing | `account.type` middleware on `routes/admin.php`, `routes/staff.php` |
| Staff route gates | `staff.permission` middleware |
| Dashboard API gates | `dashboard.permission` middleware |
| Policies | `app/Policies/*` |

## Admin vs Staff

| Rule | Enforcement |
|------|-------------|
| Platform Admin full admin routes | `account.type:platform_admin` |
| Staff limited routes | `account.type:staff` + `staff.permission` |
| Staff cannot self-promote | UserManagementPolicy + no staff JSON on admin-only mutations |
| Staff cannot edit own permissions | Admin-only user management |
| Customer/Agent denied back-office | `BackOfficePortalAccess` on session + portal middleware |
| Revoked permission immediate | `hasStaffPermission()` per request |

## Tests

`tests/Feature/Dashboard/BackOfficeOperationalClosureTest.php` — session denial, staff payment verify denial, admin-only deposit approval.
