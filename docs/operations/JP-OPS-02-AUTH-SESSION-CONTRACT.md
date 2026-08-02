# JP-OPS-02 Auth Session Contract

Canonical server-authoritative identity for all JetPakistan Next.js surfaces.

## Endpoint

`GET /api/public/auth/session` (`api.public.auth.session`)

**Headers:** `Cache-Control: no-store, private`, `Vary: Cookie`

## Anonymous

```json
{
  "authenticated": false,
  "csrf_ready": true,
  "logout": { "method": "POST", "path": "/logout" }
}
```

## Pending OTP

```json
{
  "authenticated": false,
  "requires_otp": true,
  "otp_challenge": {
    "masked_email": "u***@example.com",
    "resend_available_in": 0
  },
  "csrf_ready": true,
  "logout": { "method": "POST", "path": "/logout" }
}
```

OTP values are never included in this response.

## Authenticated

```json
{
  "authenticated": true,
  "user": {
    "id": "1",
    "name": "Ayesha Khan",
    "email": "ayesha@example.com",
    "account_type": "customer"
  },
  "role": "customer",
  "portal_type": "customer",
  "agency_id": null,
  "agency_role": null,
  "permissions": [],
  "dashboard_url": "/customer/bookings",
  "landing_route": "/customer/bookings",
  "requires_otp": false,
  "requires_password_change": false,
  "requires_email_verification": false,
  "account_status": "active",
  "email_verified": true,
  "session_usable": true,
  "csrf_ready": true,
  "logout": { "method": "POST", "path": "/logout" }
}
```

### Field reference

| Field | Type | Notes |
|-------|------|-------|
| `portal_type` | `customer\|agent\|admin\|staff\|agency_admin\|none` | Laravel-derived portal |
| `agency_id` | string\|null | Current agency when applicable |
| `agency_role` | `owner\|staff\|null` | Agent owner vs staff distinction |
| `permissions` | string[] | Staff/agent permissions from Laravel |
| `landing_route` | string | Post-login destination after gate checks |
| `session_usable` | bool | `false` when suspended/inactive |

## Authority

Built exclusively by `App\Support\Auth\PublicSessionBootstrapService`. Raw `User` models are never serialized.

## Dashboard compatibility

`GET /api/dashboard/session` remains a separate dashboard consumer shape. Not unified in JP-OPS-02.
