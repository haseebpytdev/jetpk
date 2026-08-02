# JP-OPS-04 Staff Management Contract

## Index JSON (`GET /agent/staff?format=json`)

```json
{
  "ok": true,
  "staff": [
    {
      "id": 1,
      "name": "Staff A",
      "email": "staff@alpha.test",
      "status": "active",
      "role_label": "Sales agent",
      "permissions_count": 2,
      "edit_url": "/agent/staff/1"
    }
  ],
  "capabilities": {
    "can_create": true,
    "can_manage_permissions": true
  },
  "permission_labels": { "...": "..." },
  "grouped_permissions": { "Bookings": { "...": "..." } },
  "role_templates": [
    { "value": "sales_agent", "label": "Sales agent", "summary": "..." }
  ]
}
```

## Create form (`GET /agent/staff/create?format=json`)

- `permission_labels`, `default_permissions` (`bookings.view`, `agency.view`)
- `submit_url`: `/laravel/agent/staff`

## Create (`POST /agent/staff?format=json`)

| Field | Required | Notes |
|-------|:--------:|-------|
| `name` | ✓ | |
| `email` | ✓ | Unique per agency → 409 `staff_already_exists` |
| `password` | ✓ | Hashed server-side |
| `phone` | | Stored in `meta.phone` |
| `permissions[]` | | Normalized to `staffSelectable()` |

Creates `AccountType::AgentStaff` user + `AgencyUser` row with `owner_agent_id` in meta.

## Edit form (`GET /agent/staff/{id}/edit?format=json`)

Returns `staff` detail, `selected_permissions`, `agency_role`, capability flags, mutation URLs:

- `update_url`
- `permissions_update_url`
- `apply_template_url`
- `agency_role_update_url`

## Update (`PATCH /agent/staff/{id}?format=json`)

Profile fields: `name`, `email`, `phone`, `status`, optional `password`, optional `permissions[]`.

Self-update blocked for staff actors.

## Permissions (`PATCH /agent/staff/{id}/permissions?format=json`)

Owner only. Uses `AgencyStaffPermissionAssignment::assignManual`.

## Apply template (`POST .../permissions/apply-template?format=json`)

Owner only. Uses `AgencyStaffPermissionAssignment::assignFromTemplate`. Not available for owner agency role.

## Deactivate (`DELETE /agent/staff/{id}?format=json`)

Owner only. Sets `status = inactive` (soft deactivate, not hard delete).

## Next.js pages

| Route | Component |
|-------|-----------|
| `/agent/staff` | `AgentStaffPage` |
| `/agent/staff/new` | `AgentStaffCreatePage` |
| `/agent/staff/[id]` | `AgentStaffDetailPage` |

All pages gate on 403 from JSON and show `PermissionDeniedState` when denied.
