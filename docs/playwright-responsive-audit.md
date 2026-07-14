# Playwright Responsive Visual Audit

Open-source Playwright-only responsive visual audit (no Percy or paid visual diff platform).

## Quick start

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run ui:audit:responsive
```

Reports: `UI_test/reports/responsive-visual-audit.md` and `.json`

## Config

- **Config file:** `playwright.responsive.config.ts` (separate from `playwright.config.ts`)
- **Base URL:** `LOCAL_OTA_URL` → defaults to `http://127.0.0.1:8000`
- **Browsers:** chromium, firefox, webkit
- **Viewports:** 360, 390, 430, 768, 1024, 1280, 1440, 1920

## Test code

| Path | Purpose |
|------|---------|
| `tests/visual/responsive-visual-audit.spec.ts` | Main audit runner |
| `tests/visual/route-manifest.ts` | Page/route manifest |
| `tests/visual/helpers/layout-checks.ts` | Overflow, tables, forms, landmarks |
| `tests/visual/helpers/interactions.ts` | Dropdowns, calendars, modals |
| `tests/visual/setup/auth.setup.ts` | UI login + storageState |

## Existing Playwright setup (unchanged)

`playwright.config.ts` still targets `test/e2e/` with desktop-chrome and mobile-chrome projects. The responsive audit uses its own config and does **not** read `STAGING_BASE_URL`.

## Agent staff coverage gap

Local DB has no `agent_staff` users by default. Set:

```bash
OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL=...
OTA_AUDIT_AGENT_STAFF_FULL_EMAIL=...
```

Or create staff via `/agent/staff` before re-running the audit.
