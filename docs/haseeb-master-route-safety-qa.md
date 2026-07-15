# haseeb-master Route Safety QA (MC-5C)

Phase **MC-5C** verifies the default/root OTA deployment still works after MC-1 through MC-5B. Production URLs must **not** require a `/haseeb-master` prefix. Master preview routing (MC-4/5A/5B) must not capture reserved platform paths or redirect the default slug to preview placeholders.

## Command

```bash
php artisan ota:route-safety-audit --client=haseeb-master
```

Optional: `--client=` overrides the slug under test (default `haseeb-master`).

### Output columns

| Column | Meaning |
|--------|---------|
| **route name** | Laravel route name (or `-` for path-only checks) |
| **method** | HTTP method (or `-` for inventory checks) |
| **URI** | Path under audit |
| **status** | `OK`, `missing`, or `collision-risk` |
| **notes** | Human-readable detail |

Exit code **0** when all rows are `OK`; **1** when any row is `missing` or `collision-risk`.

### Safety guarantees

- **Read-only** — route registry, internal GET dispatch for default-slug redirects only
- **No supplier API calls**
- **No booking submission**
- **No DB writes**
- **No changes** to `public/css` or `public/js`

Banner lines match other OTA audit commands (`live_supplier_call_attempted=false`, `db_write_attempted=false`).

## Scope covered

1. **Public homepage** — `/` (`home`)
2. **Public flight search** — `/flights/results*`, `/airports/search`
3. **Flight results / select / checkout** — return options, details, `/booking/passengers`, `/booking/review`, `/booking/confirmation`
4. **Booking lookup & support** — `/lookup-booking`, `/support`
5. **Auth** — `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`
6. **Admin** — `/admin`, `/admin/bookings`, plus `admin.*` route inventory
7. **Agent** — `/agent`, `/agent/bookings`, plus `agent.*` inventory
8. **Staff** — `/staff`, `/staff/bookings`, plus `staff.*` inventory
9. **Customer** — `/dashboard`, `/customer`, `/customer/bookings`, plus `customer.*` inventory
10. **Dev CP** — `/dev/cp`, `/dev/cp/clients`, plus `dev.cp.*` inventory
11. **Group ticketing** — `/groups/search`, `/groups/package/{inventory}`
12. **Static / public asset paths** — sample paths under `/css/*`, `/js/*`, `/images/*`, `/storage/*`, `/client-assets/*`, `/themes/*` (reserved slug guard + no preview capture)

### MC-5B redirect checks (default slug only)

When `--client=haseeb-master` matches the configured default deployment slug:

| Prefixed URL | Expected redirect |
|--------------|-------------------|
| `/haseeb-master` | `/` |
| `/haseeb-master/home` | `/` |
| `/haseeb-master/admin` | `/admin` |
| `/haseeb-master/login` | `/login` |

### Reserved slug guard

Every slug in `App\Support\Client\ReservedClientPreviewSlugs` is probed as `/{slug}/home`. None may match a `client.preview.*` route. See [master-preview-routing.md](master-preview-routing.md).

### Client preview routes

These named routes must remain registered:

- `client.preview.root`
- `client.preview.home`
- `client.preview.login`
- `client.preview.admin`
- `client.preview.staff`
- `client.preview.agent`

## Related verification

```bash
php artisan route:list --name=client.preview
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=OtaRouteSafetyAuditCommandTest
php artisan ota:smoke-live-routes --guest-only
```

Manual (master workspace): confirm `/` loads without prefix; `/haseeb-master/admin` redirects to `/admin`; `/jetpk/home` (active non-default client) still shows preview banner.

## Implementation map

| Piece | Path |
|-------|------|
| Audit catalog | `app/Support/Audits/HaseebMasterRouteSafetyCatalog.php` |
| Audit service | `app/Support/Audits/HaseebMasterRouteSafetyAuditService.php` |
| Artisan command | `app/Console/Commands/OtaRouteSafetyAuditCommand.php` |
| Feature tests | `tests/Feature/Console/OtaRouteSafetyAuditCommandTest.php` |

## When to run

- After MC multi-client route changes (MC-4 through MC-5B and later)
- Before deploying master workspace route/middleware updates
- As part of production readiness checks alongside `ota:smoke-live-routes --guest-only`

## Rollback

Revert the MC-5C files listed above if the audit command itself causes issues. Route behavior rollback is separate — restore prior `routes/preview.php`, `ResolvePreviewClient`, or `ReservedClientPreviewSlugs` from git/SFTP as needed.
