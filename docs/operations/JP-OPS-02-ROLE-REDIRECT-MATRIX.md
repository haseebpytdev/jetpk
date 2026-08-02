# JP-OPS-02 Role Redirect Matrix

Resolver: `App\Support\Auth\AuthPostLoginRedirectResolver`

## Precedence

1. Suspended/inactive → portal denial (`session_usable: false`)
2. Pending OTP → `/login/otp`
3. Must change password → `/password/force-change`
4. Customer email verification → `/verify-email`
5. Role landing (below)

## Role landing

| Account type | Landing route |
|--------------|---------------|
| `platform_admin` | `/admin/dashboard` |
| `agency_admin` | `/account/legacy` |
| `staff` | `/staff/dashboard` |
| `agent` | `/agent` |
| `agent_staff` | `/agent` |
| `customer` | `/customer/bookings` (or `/customer`) |

## Next.js portal guards

| Route | Allowed | Wrong role | Anonymous |
|-------|---------|------------|-----------|
| `/customer/**` | `customer` | `dashboard_url` or `/access-denied` | `/login` |
| `/agent/**` | `agent`, `agent_staff` | `dashboard_url` | `/login` |

Layouts enforce guards via `requireCustomerPortalLayoutAccess` / `requireAgentPortalLayoutAccess`.

## Redirect allowlist

Post-auth redirects sanitized by `PublicAuthRedirectAllowlist`. Arbitrary external URLs rejected.
