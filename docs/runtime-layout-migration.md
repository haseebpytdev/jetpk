# Runtime layout migration (MC-9A – MC-9E)

MC-9A through MC-9E opt production **page views** into `client_layout()` so layouts
resolve through MC-8D theme shells without changing visible UI.

## What changed

### MC-9A — frontend and auth

Page views under:

- `resources/views/frontend/**`
- `resources/views/auth/**`
- `resources/views/frontend/agent-registration/**` (auth layout pages)

now use:

```blade
@extends(client_layout('frontend', 'frontend'))
@extends(client_layout('auth', 'frontend'))
```

instead of direct `@extends('layouts.frontend')` / `@extends('layouts.auth')`.

`auth/force-password-change.blade.php` uses the **frontend** layout helper because
it historically extended `layouts.frontend`.

### MC-9B — admin dashboard

All views under `resources/views/dashboard/admin/**` now use:

```blade
@extends(client_layout('dashboard', 'admin'))
```

instead of `@extends('layouts.dashboard')`.

### MC-9C — staff dashboard

Views under `resources/views/dashboard/staff/**` with a layout extend now use:

```blade
@extends(client_layout('dashboard', 'staff'))
```

**Migrated files (6):**

| File |
|------|
| `dashboard/staff/index.blade.php` |
| `dashboard/staff/bookings/index.blade.php` |
| `dashboard/staff/support/tickets/index.blade.php` |
| `dashboard/staff/support/tickets/show.blade.php` |
| `dashboard/staff/accounting/reconciliation/index.blade.php` |
| `dashboard/staff/accounting/ledger/index.blade.php` |

**Skipped (include-only, no `@extends`):**

- `dashboard/staff/finance/statements/index.blade.php`
- `dashboard/staff/finance/statements/show.blade.php`
- `dashboard/staff/accounting/ledger/show.blade.php`

### MC-9D — agent portal

All agent portal views under `resources/views/dashboard/agent/**` now use:

```blade
@extends(client_layout('agent-portal', 'agent'))
```

**Migrated files (22):** every agent blade with a layout extend (all
`layouts.agent-portal` views; partial `dashboard/agent/staff/_form.blade.php` has
no layout).

### MC-9E — customer account

Customer portal views now use:

```blade
@extends(client_layout('customer-account', 'customer'))
```

or, for the legacy Tabler operator-style index:

```blade
@extends(client_layout('dashboard', 'customer'))
```

**Migrated files (7):**

| File | Layout helper |
|------|----------------|
| `dashboard/customer/dashboard.blade.php` | `customer-account` |
| `dashboard/customer/bookings/index.blade.php` | `customer-account` |
| `dashboard/customer/bookings/show.blade.php` | `customer-account` |
| `dashboard/customer/support/tickets/index.blade.php` | `customer-account` |
| `dashboard/customer/support/tickets/create.blade.php` | `customer-account` |
| `dashboard/customer/support/tickets/show.blade.php` | `customer-account` |
| `dashboard/customer/index.blade.php` | `dashboard` |

**New theme shell:** `resources/views/themes/customer/default-customer/layouts/dashboard.blade.php`
(delegate-only → `layouts.dashboard`).

**Skipped:** `dashboard/customer/partials/default-traveler-card.blade.php` (partial).

### What did not change

Theme delegate shells under `resources/views/themes/{area}/{theme}/layouts/` still
`@extends` legacy layouts — that is intentional.

**Deferred** (still on legacy `@extends`):

| File | Reason |
|------|--------|
| `profile/edit-dashboard.blade.php` | Shared admin/staff profile shell |
| `profile/edit-agent.blade.php` | Shared agent profile shell |
| `profile/edit-frontend.blade.php` | Shared customer/public profile shell |

No changes to `public/css`, `public/js`, supplier logic, module gates, or Dev CP views.

## Full portal runtime status

| Portal | Migrated pattern | Theme shell (haseeb-master) | Status |
|--------|------------------|----------------------------|--------|
| frontend | `client_layout('frontend', 'frontend')` | `themes.frontend.v1-classic.layouts.frontend` | MC-9A |
| auth | `client_layout('auth', 'frontend')` | `themes.frontend.v1-classic.layouts.auth` | MC-9A |
| admin | `client_layout('dashboard', 'admin')` | `themes.admin.default-admin.layouts.dashboard` | MC-9B |
| staff | `client_layout('dashboard', 'staff')` | `themes.staff.default-staff.layouts.dashboard` | MC-9C |
| agent | `client_layout('agent-portal', 'agent')` | `themes.agent.default-agent.layouts.agent-portal` | MC-9D |
| customer (account) | `client_layout('customer-account', 'customer')` | `themes.customer.default-customer.layouts.customer-account` | MC-9E |
| customer (index) | `client_layout('dashboard', 'customer')` | `themes.customer.default-customer.layouts.dashboard` | MC-9E |

## Resolution chain (unchanged from MC-8D)

1. Page view calls `client_layout('dashboard', 'staff')`.
2. Resolver returns `themes.staff.default-staff.layouts.dashboard` when theme file exists.
3. Theme shell delegates to `layouts.dashboard`.
4. Rendered HTML is identical to pre-MC-9.

## Migrate one view safely

1. Confirm a theme layout shell exists for the area (see `ota:client-layout-audit`).
2. Change only the first line of the target Blade file:

   ```blade
   @extends(client_layout('dashboard', 'staff'))
   ```

   Use the appropriate area and layout name (`agent-portal` / `agent`,
   `customer-account` / `customer`, etc.).

3. Do **not** edit theme shells or legacy `resources/views/layouts/*` unless
   intentionally changing production markup.
4. Run verification commands below.
5. SFTP upload the single Blade file; on server: `php artisan view:clear`.

## Rollback one view

Revert the `@extends` line to the legacy name:

```blade
@extends('layouts.dashboard')
@extends('layouts.agent-portal')
@extends('layouts.customer-account')
```

Upload the reverted file and run `php artisan view:clear`. No database or config
rollback is required.

## Verification commands

```powershell
# Local Windows
php artisan ota:runtime-layout-migration-audit --client=haseeb-master
php artisan ota:client-layout-audit --client=haseeb-master
php artisan ota:ui-runtime-audit --client=haseeb-master
php artisan ota:route-safety-audit --client=haseeb-master
php artisan test --filter=Mc9RuntimeLayoutMigrationTest
php artisan test --filter=OtaRuntimeLayoutMigrationAuditCommandTest
```

**Server SSH after Blade upload:**

```bash
php artisan view:clear
```

Smoke URLs (guest — all redirect to login):

- `/` → 200
- `/haseeb-master/home` → 200
- `/login` → 200
- `/haseeb-master/login` → 200
- `/admin` → redirect to `/login`
- `/haseeb-master/admin` → redirect to `/haseeb-master/login`
- `/staff` → redirect to `/login`
- `/haseeb-master/staff` → redirect to `/haseeb-master/login`
- `/agent` → redirect to `/login`
- `/haseeb-master/agent` → redirect to `/haseeb-master/login`
- `/customer` → redirect to `/login`
- `/haseeb-master/customer` → redirect to `/haseeb-master/login`

## Related docs

- View resolver (MC-8B/8C): [`runtime-view-layout-resolution.md`](runtime-view-layout-resolution.md)
- Theme engine (MC-8A): [`runtime-theme-engine.md`](runtime-theme-engine.md)
