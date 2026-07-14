# JetPK Dashboard Design System

Phase: **JETPK-DASHBOARD-SYSTEM-PAGE-BUILDER-EMAIL-PACK-CLOSURE-9G**

## Design tokens

Source: `public/themes/frontend/jetpakistan/css/tokens.css` + admin shell `public/themes/admin/jetpakistan/css/dashboard.css`.

| Token | Value / rule |
|-------|----------------|
| Spacing rhythm | 4px base (`--sp-1` … `--sp-6`) |
| Control radius | `--r-input` 12px, `--r-card` 14px, `--r-pill` pill buttons |
| Typography | `--font-display` (Space Grotesk), body Inter 14px |
| Accent | JetPK orange `--brand` / `--brand-bright` |
| Surfaces | `--bg`, `--card`, `--surface`, `--line` hierarchy |

## Shell rules

| Role | Layout | CSS |
|------|--------|-----|
| Admin / Staff | `themes.admin.jetpakistan.layouts.dashboard` | `tokens.css` + `dashboard.css` |
| Agent / Customer | `themes.*.jetpakistan.layouts.*-portal` | `tokens.css` + `portal.css` |
| DevCP | `layouts.developer` | `devcp.css` only |

**Forbidden:** Tabler `layouts/dashboard.blade.php` on JetPK production profile; `ota-admin-console.css` in JetPK admin shell.

## Components

| Class / pattern | Use |
|-----------------|-----|
| `.jp-card` | Section container |
| `.jp-form-section` | Grouped form block |
| `.jp-form-section--secure` | Credentials / secrets |
| `.jp-form-actions` | Sticky save/cancel bar |
| `.jp-input`, `.jp-label` | Native admin forms |
| `.jp-module-compat` | Legacy supplier form shim inside JetPK shell |
| `.jp-dtable` | Data tables |
| `.jp-btn` | Actions |

## Forms

- Labels above controls, 12px muted weight 600
- Grids: `.jp-form-grid`, `.jp-form-grid--3`
- Checkboxes: `.jp-check`, switches via compat layer
- Helper text: `.jp-form-section__hint`, `.form-hint`
- Secrets: masked badges, “leave blank to retain” hints

## Tables

- Wrapper: `.jp-dtable` or card + responsive scroll
- Status: `.jp-badge` / status-badge component
- Empty: `x-themes.admin.jetpakistan.components.empty-state`

## Forbidden patterns

- Raw Bootstrap `page-header` / `page-pretitle` in JetPK themed views
- Unwrapped legacy `form-control` stacks without `jp-module-compat`
- Master client names (Parwaaz, YD Travel, YoursDomain)
- Duplicate shell wrappers (nested `jp-dash`)

## Responsive

- Sidebar collapses < 1100px
- Form grids single-column on mobile
- Sticky action bars respect safe-area inset
