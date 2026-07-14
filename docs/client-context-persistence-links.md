# Client context persistence and client-aware links (MC-7C/7D)

MC-7B registered read-only GET/HEAD parity routes under `/{clientSlug}/{originalUri}`. MC-7C/7D keeps navigation, redirects, and session context inside that prefix for client preview and QA — without changing root production URLs or supplier execution.

## What MC-7C/7D adds

| Component | Purpose |
|-----------|---------|
| [`PersistClientPreviewContext`](../app/Http/Middleware/PersistClientPreviewContext.php) | After `ResolvePreviewClient`, stores slug in request attributes + session key `ota.preview_client_slug` |
| [`ClientRedirectResolver`](../app/Services/Client/ClientRedirectResolver.php) | Client-aware redirects for login, logout, dashboard hub, and auth success paths |
| [`client_helpers.php`](../app/Support/Client/client_helpers.php) | `client_route()`, `client_url()`, `current_client_slug()`, `current_client_profile()`, `client_relative_path()` |
| Layout / nav migration | High-impact links in shared layouts stay prefixed under client preview |
| [`ota:client-context-flow-audit`](../app/Console/Commands/OtaClientContextFlowAuditCommand.php) | Read-only QA command for prefixed flow |

## Slug resolution priority

1. **URL `{clientSlug}` route parameter** — always wins; context comes from the URL on parity routes, not session.
2. **`CurrentClientContext` preview mode** — set by `ResolvePreviewClient` on parity GET/HEAD.
3. **Session `ota.preview_client_slug`** — only for safe post-auth redirects (POST login/logout at root). Never overrides an explicit URL slug.

Dev CP (`/dev/cp/*`) is never prefixed and persistence middleware skips it.

## Middleware stack (parity routes only)

Parity routes use:

```
web → preview.client → preview.client.persist → (original route middleware)
```

Root routes (`/login`, `/admin`, etc.) are unchanged. POST parity is **not** registered (mutating routes excluded).

## URL helpers

Autoloaded from `app/Support/Client/client_helpers.php`:

- `client_route($name, $params, $clientSlug)` — prefers `client.parity.{name}` when parity exists and slug is known
- `client_url($path, $clientSlug)` — prefixes path; preserves query strings
- `current_client_slug()` — from URL param or preview context (not session)
- `current_client_profile()` — active `ClientProfile` from context
- `is_client_preview()` — true when context is preview mode
- `client_relative_path()` — path after stripping client slug (dashboard area detection)

Outside preview, helpers fall back to normal `route()` / `url()`.

## Layouts updated (high-impact nav)

- [`resources/views/layouts/frontend.blade.php`](../resources/views/layouts/frontend.blade.php) — public nav, login, register
- [`resources/views/layouts/auth.blade.php`](../resources/views/layouts/auth.blade.php) — auth brand home link
- [`resources/views/layouts/dashboard.blade.php`](../resources/views/layouts/dashboard.blade.php) — portal home URLs, `$urlArea` with client slug strip
- [`resources/views/layouts/customer-account.blade.php`](../resources/views/layouts/customer-account.blade.php)
- [`resources/views/layouts/agent-portal.blade.php`](../resources/views/layouts/agent-portal.blade.php)
- Sidebar partials: admin/staff/customer/agent — dashboard, public home, support, booking lookup
- [`resources/views/components/account-dropdown.blade.php`](../resources/views/components/account-dropdown.blade.php)

## Auth redirect handling

Controllers use `ClientRedirectResolver` where safe:

- Login success / intended redirect
- Logout (session slug captured before invalidate)
- Registration → verification notice
- Email verification → dashboard
- Password reset success → login
- Social auth success
- [`DashboardRedirectController`](../app/Http/Controllers/DashboardRedirectController.php)

[`LoginDestination`](../app/Support/Auth/LoginDestination.php) delegates to `ClientRedirectResolver::dashboardPathForUser()`.

Guest auth redirect (`AuthenticationException`) uses `client_route('login')` when preview context exists.

## Deferred (future passes)

- Deep admin/staff sidebar links (bookings queues, finance, settings)
- Auth sub-view cross-links (`auth/login.blade.php`, mobile auth)
- POST form actions (login, logout, register) — remain at root until POST parity allowlist review
- Email templates and notification `loginUrl` strings
- OAuth callback URLs
- **MC-7E** — production client domain host guard (`host_guard_enabled`, `master_host`)

## Verification

```bash
php artisan ota:client-context-flow-audit --client=jetpk
php artisan ota:route-safety-audit --client=haseeb-master
php artisan test --filter=ClientPrefixedRouteParityTest
php artisan test --filter=ClientContextPersistenceTest
php artisan test --filter=OtaClientContextFlowAuditCommandTest
```

Manual smoke (after deploy):

1. `/jetpk/home` → nav links stay under `/jetpk/*`
2. `/jetpk/login` → post-login dashboard under `/jetpk/*`
3. Root `/login` and `/admin` unchanged for default deployment

## Related docs

- [client-prefixed-route-parity.md](client-prefixed-route-parity.md) — MC-7B parity route registration
- [client-route-page-parity-audit.md](client-route-page-parity-audit.md) — MC-7A audit matrix
