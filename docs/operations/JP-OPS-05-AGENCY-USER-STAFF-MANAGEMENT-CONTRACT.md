# JP-OPS-05 Agency User Staff Management Contract

## Connected reads

- Agents: `/api/dashboard/agents`
- Users: `/api/dashboard/users` (Admin)

## Mutations

| Area | Next status | Blade fallback |
|------|-------------|----------------|
| Agency approve/suspend | Deferred | `admin.agencies.*` |
| Agent applications | Deferred | `admin.agent-applications.*` |
| User suspend/activate | Deferred | `admin.users.*` |
| Staff permission edits | Deferred | Admin Users UI |

## Safety rules (enforced when connected)

- Platform Staff cannot self-promote
- Staff cannot edit own permissions
- No password/OTP/token exposure in JSON
- Protected Admin accounts cannot be modified by Staff
