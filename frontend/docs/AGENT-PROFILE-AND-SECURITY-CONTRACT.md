# Agent Profile and Security Contract

## Profile read

`GET /agent/profile?format=json`

Requires authenticated agent portal user (owner or staff).

Response sections:

### User

- `name`, `email`, `username`
- `email_verified`, `email_verified_at`
- `role_label` — `Agency owner` or `Agency staff`

### Personal profile

- `phone`, `whatsapp`, `country_code`, `city`, `designation`

### Agency profile

Read-only in JSON for staff without agency edit gate:

- `agency_name`, `legal_name`, `license_number`, `registration_number`, `tax_number`
- `email`, `phone`, `city`, `country`, `address`
- `logo_url`, `agent_code`, `platform_agency_name`
- `verification.is_complete`, `verification.missing_fields`

### Capabilities

- `can_edit_personal` — true for portal users
- `can_edit_agency` — `Gate::allows('updateAgency', $agent)`
- `can_view_agency` — `agent.agency.view` permission

### Form metadata

- `countries` — select options
- `personal_update_url` — `/laravel/profile`
- `agency_update_url` — `/laravel/agent/agency`
- `password_update_url` — `/laravel/password`
- `supported_personal_fields`, `supported_agency_fields`

## Profile update

### Personal

- `PATCH /profile` with session cookie + CSRF (via Next `/laravel/profile` proxy)
- Supported: `name`, `email`, `username`, `phone`, `whatsapp`, `country_code`, `city`
- Email change clears `email_verified_at` (Laravel behavior)
- Validation errors returned as JSON 422

### Agency

- Agency edit remains Laravel Blade at `/agent/agency/edit` when `can_edit_agency`
- Not implemented as Next.js form in JP-FE-12; JSON exposes read-only agency block for staff

## Security / password

Next.js route: `/agent/security`

- `PUT /password` with `current_password`, `password`, `password_confirmation`
- Laravel `Password::defaults()` policy applies
- CSRF required; no password in URL or client storage

## Role differences

| Capability | Owner | Staff |
|------------|-------|-------|
| Edit personal profile | Yes | Yes |
| View agency details | Yes | If `agent.agency.view` |
| Edit agency | Yes (gate) | Gate + `agent.agency.edit` (Blade) |
| Change password | Yes | Yes |

Staff permissions do not include `agent.profile.manage` in selectable set; personal edit is allowed for all portal users.

## Excluded

- Staff permission management — Blade `/agent/staff`
- Email verification flow — Laravel `/verify-email`
- Agency logo upload from Next.js — agency edit Blade form
- Account deactivation or role changes from dashboard

## Blade fallback

`GET /agent/profile` without JSON renders existing Blade profile view.
