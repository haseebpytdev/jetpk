# OTA Responsive UI Standards

Canonical Cursor rule: `.cursor/rules/ui-responsive-rules.mdc`

## CSS entry points

| Surface | Primary CSS |
|---------|-------------|
| Public, customer, agent (frontend shell) | `public/css/ota-public.css` (+ `ota-design-system.css`) |
| Admin / staff (Tabler) | `resources/views/layouts/dashboard.blade.php` inline block + Tabler |

## Utility classes (`ota-public.css`)

| Class | Purpose |
|-------|---------|
| `.ota-r-table-wrap` | Horizontal scroll table container |
| `.ota-r-action-bar` | Wrapping toolbar / button row |
| `.ota-r-form-grid` | Responsive form columns |
| `.ota-r-text-safe` | Long string wrap |
| `.ota-r-truncate` | Ellipsis truncation |
| `.ota-r-dropdown-panel` | Viewport-safe dropdown panel |

Existing account classes (`.ota-account-table-wrap`, `.ota-account-header`, etc.) remain the default for customer/agent portals.

## Tests

`tests/Feature/Ui/ResponsiveRenderTest.php` — smoke render of key routes after CSS/Blade changes (not a visual regression suite).

Optional: `npm run e2e:ui-qa` (Playwright) for viewport checks.
