# JP-OPS-04 Agent Staff RBAC Matrix

## Policy: `AgentStaffPolicy`

| Ability | Owner (`agent`) | Staff + `staff.manage` | Staff without perm | Self-edit |
|---------|:---------------:|:----------------------:|:------------------:|:---------:|
| `viewAny` | ✓ | ✓ | 403 | — |
| `view` | ✓ | ✓ (owned staff) | 403 | — |
| `create` | ✓ | ✓ | 403 | — |
| `update` | ✓ | ✓ (not self) | 403 | ✗ |
| `delete` (deactivate) | ✓ | ✗ | 403 | ✗ |

## Permission assignment

| Action | Route | Actor | JSON |
|--------|-------|-------|------|
| Manual permissions | `PATCH /agent/staff/{id}/permissions` | Owner only | ✓ |
| Apply role template | `POST .../permissions/apply-template` | Owner only | ✓ |
| Agency role | `PATCH /agent/staff/{id}/agency-role` | Owner only | Blade + JSON URLs in presenter |
| Create staff | `POST /agent/staff` | Owner or staff.manage | 201 / 409 duplicate |
| Deactivate | `DELETE /agent/staff/{id}` | Owner only | Sets `status=inactive` |

Staff-selectable permissions (`AgentPermission::staffSelectable()`) exclude `staff.manage` and `agency.edit` — only owners receive those implicitly.

## Middleware stack

| Route group | Middleware |
|-------------|------------|
| Staff CRUD | `agent.permission:agent.staff.manage`, `platform.module:agent_staff` |
| Commissions | `agent.admin` |
| Reports | `agent.permission:agent.reports.view`, `platform.module:agent_reports` |
| Booking create | `agent.permission:agent.bookings.create` |
| Agency view | `agent.permission:agent.agency.view` |
| Agency edit | `agent.permission:agent.agency.edit` |

## Presenter capabilities (`AgentPortalStaffPresenter`)

| Field | Meaning |
|-------|---------|
| `capabilities.can_create` | Owner or staff.manage |
| `capabilities.can_manage_permissions` | Owner only |
| `capabilities.can_update` | Owner or staff.manage (not self) |
| `capabilities.can_update_permissions` | Owner, not self |
| `capabilities.can_apply_template` | Owner, non-owner agency role |
| `capabilities.can_deactivate` | Owner, not self |

## Scenario staff matrix (tests)

| Key | Permissions | Wallet JSON | Staff index |
|-----|-------------|:-----------:|:-----------:|
| A1 | bookings.view | 403 | 403 |
| A2 | bookings.view, bookings.create | 403 | 403 |
| A3 | wallet.view | ✓ | 403 |
| A4 | wallet.view, ledger.view | ✓ | 403 |
| A5 | wallet.view, payments.upload | ✓ | 403 |
| Admin A | all (owner) | ✓ | ✓ |
