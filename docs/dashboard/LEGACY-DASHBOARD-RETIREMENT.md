# Legacy Dashboard Retirement — DASH-13

## Retired active routes

| Legacy item | Replacement | Redirect |
|-------------|-------------|----------|
| `GET /admin` → `Admin\DashboardController@index` | `GET /admin/dashboard` → Next.js shell | `/admin` → `/admin/dashboard` |
| `GET /staff` → `Staff\DashboardController@index` | `GET /staff/dashboard` → Next.js shell | `/staff` → `/staff/dashboard` |
| `/testdash` (Next preview, port 3001) | Production mount | Redirect via Laravel |

## Retained for rollback (not user-accessible)

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Staff/DashboardController.php`
- Blade views under `resources/views/themes/dashboard/` and JetPK admin/staff theme paths
- Legacy Tabler/JetPK dashboard partials

## Not affected

- Agent dashboard (`/agent`)
- Agent Staff routes under agent portal
- Customer dashboard (`/customer`, `/customer/bookings`)

## Safe removal timing

Defer Blade dashboard view deletion until post-stabilization (minimum one production cycle after DASH-13 sign-off).

## Rollback dependency

Restore `routes/admin.php` and `routes/staff.php` dashboard route lines to legacy `DashboardController@index`; remove `BackOfficeDashboardController` routes; redeploy previous `public/_next` if changed.
