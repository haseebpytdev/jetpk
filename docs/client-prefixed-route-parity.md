# Client-prefixed route parity (MC-7B)

Runtime GET/HEAD parity routes under `/{clientSlug}/{originalUri}` for multi-client preview and QA on the master OTA workspace.

## Three URL modes

| Mode | Example login | Purpose |
|------|---------------|---------|
| Default root | `/login` | haseeb-master production (unchanged) |
| Default prefixed alias | `/haseeb-master/login` | Redirects to `/login` (alias-only; no preview context) |
| Client prefixed | `/jetpk/login` | Client preview/QA parity route |

Root production routes (`/`, `/login`, `/admin`, `/agent`, `/staff`, `/customer`, `/dev/cp`) are **unchanged**.

## Configuration

File: `config/client_route_parity.php`

| Key | Default | Purpose |
|-----|---------|---------|
| `enabled` | `true` | Master switch |
| `allow_haseeb_master_prefixed_parity` | `true` | **Deprecated — ignored.** Default slug always redirects to canonical root paths |
| `master_host` | `null` | Host-guard placeholder (MC-7C) |
| `host_guard_enabled` | `false` | Not enforced yet |
| `allowed_methods` | GET, HEAD | Safe methods only |
| `max_risk` | `low` | Classifier risk ceiling |

Env overrides:

- `CLIENT_ROUTE_PARITY_ENABLED`
- `CLIENT_ROUTE_PARITY_ALLOW_HASEEB_MASTER`

**Rollback:** set `CLIENT_ROUTE_PARITY_ENABLED=false` to disable all client-prefixed parity routes (non-default clients lose `/jetpk/*` mirrors).

## How parity routes are registered

`App\Services\Client\ClientPrefixedRouteRegistrar` runs after all route files load (`bootstrap/app.php`). It scans the route registry and uses `ClientRouteParityClassifier` (MC-7A) to register mirrors where:

- `should_have_client_prefix=yes`
- `risk_level=low`
- method is GET or HEAD
- classification is in the allowed list

Parity route names: `client.parity.{original_route_name}` (or `client.parity.generated.{hash}` for unnamed routes).

Middleware: `web`, `preview.client`, `preview.client.persist`, plus the original route middleware.

See [client-context-persistence-links.md](client-context-persistence-links.md) for MC-7C/7D navigation and redirect wiring.

## Intentionally excluded

Not prefixed (by design):

- Dev CP (`/dev/cp/*`)
- Webhooks
- Internal/XHR APIs (`/flights/results/data`, `airports/search`, etc.)
- Supplier live actions (`supplier-booking`, `revalidate-offer`, etc.)
- Static assets (`css/`, `js/`, `storage/`, `client-assets/`, `themes/`)
- All mutating POST/PUT/PATCH/DELETE routes (deferred to MC-7C allowlist review)

## URL helpers (MC-7B + MC-7C/7D)

Autoloaded from `app/Support/Client/client_helpers.php`:

- `is_client_preview()` — true when `CurrentClientContext` is in preview mode
- `client_route($name, $params, $clientSlug)` — prefers `client.parity.*` when slug is known
- `client_url($path, $clientSlug)` — prefixes path with client slug when in preview or explicit slug
- `current_client_slug()`, `current_client_profile()`, `client_relative_path()` — MC-7C/7D

Shared layouts use **`client_route()`** / **`client_url()`** for high-impact nav (MC-7D). See **`client-context-persistence-links.md`**.

## haseeb-master behavior (MC-7B vs MC-5B)

When parity is enabled:

- `/haseeb-master` → redirects to `/haseeb-master/home` (real homepage)
- `/haseeb-master/login` → same login controller with haseeb-master context
- `/haseeb-master/admin` → same admin auth middleware (redirects to login when guest)

When parity is disabled (MC-5B legacy):

- `/haseeb-master/*` redirects to unprefixed production URLs

## Host guard (future MC-7C)

Production client domains should eventually set `host_guard_enabled=true` and `master_host` so prefixed parity routes are only available on the master workspace host. Not enforced in MC-7B.

## Verification

```bash
php artisan ota:client-route-parity-status --client=haseeb-master --target=jetpk
php artisan route:list --name=client.parity.login
php artisan route:list --path=jetpk/groups/search
php artisan ota:route-safety-audit --client=haseeb-master
php artisan test --filter=ClientPrefixedRouteParityTest
php artisan test --filter=ClientPreviewRoutingTest
```

## Related docs

- [client-route-page-parity-audit.md](client-route-page-parity-audit.md) — MC-7A audit matrix
- [master-preview-routing.md](master-preview-routing.md) — MC-4/5A/5B preview routing

## Future phases

| Phase | Scope |
|-------|-------|
| MC-7E | Host guard — restrict prefixed routes to master workspace / production client domains |
| MC-7B+ | Mutating action allowlist from audit JSON |
