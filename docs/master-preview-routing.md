# Master preview routing (MC-4, MC-5A, MC-5B)

Master testing workspace (`ota.haseebasif.com`) can preview deployment client profiles under URL prefixes:

- `/{clientSlug}` → redirects to `/{clientSlug}/home` for **non-default** clients (MC-5A)
- `/{clientSlug}/home`, `/login`, `/admin`, `/staff`, `/agent` — preview/parity routes for non-default clients

Example: `/jetpk` → `/jetpk/home`; `/jetpk/admin`.

**Default deployment slug** (`config('ota_client.slug')` or `haseeb-master`) is the **canonical root client**. Prefixed URLs are **alias-only** and always redirect (302) to production root paths — no preview context is set:

- `/haseeb-master`, `/haseeb-master/home` → `/`
- `/haseeb-master/login` → `/login`
- `/haseeb-master/register` → `/register`
- `/haseeb-master/admin` → `/admin`
- `/haseeb-master/admin/bookings` → `/admin/bookings`
- `/haseeb-master/staff` → `/staff`
- `/haseeb-master/agent` → `/agent`
- `/haseeb-master/customer` → `/customer`
- `/haseeb-master/lookup-booking` → `/lookup-booking`
- `/haseeb-master/groups/search` → `/groups/search`
- `/haseeb-master/*` → `/*` (query string preserved)

Normal production URLs (`/`, `/admin`, …) do **not** require a client slug prefix.
Default runtime context resolves **`haseeb-master`** when no preview slug is present (MC-5A).

## Purpose

MC-4 loads an **active** `ClientProfile` row from the database and sets request-scoped runtime context via `App\Services\Client\CurrentClientContext`. Preview pages show profile metadata (name, slug, themes, asset profile, module flags, supplier flags, branding summary), resolved asset URLs (MC-5A), and a clear **master preview mode** banner.

This phase does **not**:

- Clone full public/admin/staff/agent route trees under the prefix
- Switch theme assets or CSS/JS bundles on production layouts
- Change supplier execution, platform module gates, or `App\Support\Client\ClientProfile` static reads
- Replace production routes (`/`, `/login`, `/admin`, `/staff`, `/agent`)

## Components

| Piece | Role |
|-------|------|
| `CurrentClientContext` | Request-scoped holder; lazy default profile (MC-5A); `set()` marks preview mode |
| `ClientAssetResolver` | Theme/client asset path + URL helpers (MC-5A); see [runtime-client-asset-resolution.md](runtime-client-asset-resolution.md) |
| `ReservedClientPreviewSlugs` | Blocks reserved path segments from matching `{clientSlug}` (MC-5A) |
| `ResolvePreviewClient` | Middleware on preview/parity route groups; default slug → canonical root redirect (302, alias-only); non-default slug sets preview context; 404 when slug missing/inactive |
| `ClientPreviewController` | Safe placeholder views under `resources/views/preview/client/` |
| `routes/preview.php` | Preview-only route group registered in `bootstrap/app.php` |

Route names: `client.preview.root`, `client.preview.home`, `client.preview.login`, `client.preview.admin`, `client.preview.staff`, `client.preview.agent`.

## Reserved slugs

Preview `{clientSlug}` cannot be: `admin`, `staff`, `agent`, `dev`, `login`, `register`, `booking`, `bookings`, `groups`, `api`, `storage`, `css`, `js`, `images`, `assets`, `build`, `vendor`, `client-assets`, `themes`.

## Production client domains

Production client deployments should **not** expose other clients' preview URLs. Restrict preview routing to the master workspace host (e.g. `ota.haseebasif.com`) in a later phase via host/env guard if needed. MC-4/5A register routes globally but only resolve preview context when an active profile slug matches.

## Next phase

Wire theme selection, module gates, supplier context, and optional host guard into production layouts (`frontend.blade.php`) — without duplicating entire route files.

## Verification

```bash
php artisan route:list --name=client.preview
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=ClientAssetResolverTest
```

Manual (master workspace): open `/jetpk` or `/jetpk/home` (or any active slug from Dev CP → Clients) and confirm context card, resolved asset URLs, and preview banner.

See also: [runtime-client-asset-resolution.md](runtime-client-asset-resolution.md).
