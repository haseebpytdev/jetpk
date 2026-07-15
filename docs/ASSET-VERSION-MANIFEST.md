# ASSET-VERSION-MANIFEST

| Asset | Changed? | Old | New | Reference |
|---|---|---|---|---|
| `public/themes/mobile/jetpakistan-app/css/app.css` | **NEW** | — | **`?v=5`** | `$jpMobileAssetVersion` — inline `@php` at the top of `themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php`. Also versions the jp `tokens.css` link |
| `public/css/ota-mobile-app.css` | No | — | unchanged | **Never bump** (shared) |
| `public/css/ota-design-system.css` | No | — | unchanged | **Never bump** (shared) |
| `resources/views/layouts/mobile-app.blade.php` | No | — | unchanged | Shared — untouched |
| `themes/frontend/jetpakistan/css/*` | No | `?v=39` | unchanged | **Do not bump** |
| `themes/admin/jetpakistan/css/dashboard.css` | No | `?v=21` | unchanged | Set by the dashboard programme (Phase 7) |

**Version history of `app.css`:** MA-2 `1` → MA-3 `2` → MA-4 `3` → MA-5 `4` → **MA-6 `5`**.
Every layer bumped it in the same change, per the repo rule: **bump only when the asset changes.**
**Any future edit to `app.css` must bump `$jpMobileAssetVersion`** or browsers will serve stale CSS.
