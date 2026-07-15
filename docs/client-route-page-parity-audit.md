# Client route/page parity audit (MC-7A)

Read-only audit tooling and architecture for multi-client route prefix parity on the master OTA workspace.

## Why full route parity is needed

The OTA runs as **one Laravel codebase** with per-client deployment profiles (`ClientProfile`, `clients/{slug}/`, `public/client-assets/{profile}/`). Today:

- **Production (haseeb-master default):** unprefixed routes (`/`, `/login`, `/admin`, …) are the live paths.
- **Master preview (MC-4/5A):** only six placeholder GET routes exist under `/{clientSlug}/home`, `/login`, `/admin`, `/staff`, `/agent`.
- **Client QA:** operators need to exercise **real pages** under `/jetpk/*` (and similar) without a separate deployment.

Full parity means every meaningful page is reachable in three modes:

| Mode | Example login URL | Purpose |
|------|-------------------|---------|
| Default root | `/login` | haseeb-master production |
| Default prefixed | `/haseeb-master/login` | Debug/parity on master workspace |
| Client prefixed | `/jetpk/login` | Client preview/QA |

MC-7A produces the **audit matrix**; MC-7B implements prefixed route wrappers. See [client-prefixed-route-parity.md](client-prefixed-route-parity.md) for MC-7B runtime registration.

## Default haseeb-master root route behavior

- `/` remains the normal haseeb-master homepage.
- `/login`, `/admin`, `/agent`, `/staff`, `/customer`, `/dev/cp` stay unprefixed production routes.
- `CurrentClientContext` resolves the default profile when no preview slug is present (MC-5A).
- **No change in MC-7A** — audit only.

## haseeb-master prefixed route parity

On the master workspace, `/haseeb-master/*` currently **redirects** to unprefixed production URLs (MC-5B):

- `/haseeb-master/home` → `/`
- `/haseeb-master/login` → `/login`

MC-7B will add an optional **true parity mode** so `/haseeb-master/*` can mirror root routes for debugging without affecting default root behavior.

## Non-default client preview route parity

For slugs like `jetpk`:

- `ResolvePreviewClient` middleware loads the active `ClientProfile` and sets `CurrentClientContext`.
- Prefixed URIs use the same controllers/views as production (MC-7B), not placeholder cards.
- Reserved slug segments (`admin`, `login`, `api`, …) must not collide with `{clientSlug}` — see `ReservedClientPreviewSlugs`.

## What should NOT be prefixed

The audit classifier marks these as **never auto-prefix**:

| Category | Examples | Reason |
|----------|----------|--------|
| Dev CP | `/dev/cp/*` | Platform operator tooling |
| Webhooks | future webhook endpoints | External callback URLs |
| Internal APIs | `/flights/results/data`, `airports/search` | XHR/JSON; not browser pages |
| Supplier live actions | `supplier-booking`, `revalidate-offer` | Live GDS/API calls |
| Static assets | `css/`, `js/`, `storage/`, `client-assets/` | Asset paths |
| Debug/audit | telescope, debugbar | Dev tooling |
| Excluded | `/up`, `client.preview.*` placeholders | Health + MC-4 placeholders |

**Destructive POST** booking/ticketing/cancel actions are **high-risk** and deferred until explicit MC-7B+ review (`should_have_client_prefix=no`).

## Running the audit

```bash
php artisan ota:client-route-parity-audit --client=haseeb-master --target=jetpk
```

Options:

- `--client=haseeb-master` — default deployment slug (documented in summary)
- `--target=jetpk` — example client slug for `suggested_prefixed_uri` column
- `--fail-on-high-risk` — exit code 1 if any route is both `should_have_client_prefix=yes` and `risk_level=high`
- `--export-dir=storage/app/audits` — override export directory (tests use `storage/framework/testing/...`)

Exports (timestamped):

- `storage/app/audits/client-route-parity-{Ymd-His}.json` — full matrix
- `storage/app/audits/client-route-parity-{Ymd-His}.md` — summary + truncated table

Each route row includes:

- route name, method, URI, controller/action, middleware
- classification (17 categories)
- `should_have_client_prefix`: yes/no
- `suggested_prefixed_uri`
- `risk_level`: low/medium/high
- notes

## Classification categories

`public_page`, `public_action`, `auth_page`, `auth_action`, `customer_dashboard`, `agent_dashboard`, `staff_dashboard`, `admin_dashboard`, `dev_cp`, `group_ticketing`, `booking_flow`, `supplier_api_action`, `internal_api`, `asset_static`, `webhook`, `debug_or_audit`, `excluded`

## Implementation phases after audit

**Do not start MC-7B until the JSON export is reviewed** and high-risk rows are signed off.

| Phase | Scope |
|-------|-------|
| **MC-7B1** | Infrastructure: `ClientPrefixedRouteRegistrar`, URL helper, parity route naming |
| **MC-7B2** | Public GET parity (home, flights, groups, support, lookup) |
| **MC-7B3** | Auth GET parity (login, register, password reset) |
| **MC-7B4** | Dashboard GET parity (admin/agent/staff/customer read pages) |
| **MC-7B5** | haseeb-master prefix true-parity mode (optional config) |
| **MC-7B6** | Mutating actions — per-route allowlist from audit JSON |
| **MC-7C** | Host guard — restrict prefixed routes to master workspace |
| **MC-7D** | Link generation — Blade/route helpers for preview mode |

## Related docs

- [client-prefixed-route-parity.md](client-prefixed-route-parity.md) — MC-7B runtime parity registration
- [master-preview-routing.md](master-preview-routing.md) — MC-4/5A/5B preview routing
- [client-deployment-architecture.md](client-deployment-architecture.md) — deployment profiles

## Verification

```bash
php artisan test --filter=OtaClientRouteParityAuditCommandTest
php artisan test --filter=ClientRouteParityClassifierTest
php artisan ota:client-route-parity-audit --client=haseeb-master --target=jetpk --fail-on-high-risk
```

MC-7A is **read-only**: no supplier calls, no bookings, no route registration changes.
