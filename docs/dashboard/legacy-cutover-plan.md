# Legacy Cutover Plan

## Principles

1. **Coexistence** — Blade `/admin` and `/staff` remain until feature parity and QA sign-off per module.
2. **No deletion** — Do not remove existing dashboard views, Tabler fallbacks, or JetPK theme Blade files during early Next phases.
3. **Read-first** — Each module cutover starts with read-only Next pages backed by Laravel APIs.
4. **Mutations stay on Laravel** until dedicated API hardening and audit.

## Suggested module order

| Order | Module | Legacy routes | Risk |
|-------|--------|---------------|------|
| 1 | Overview | `admin.dashboard` | Low (read) |
| 2 | Bookings list | `admin.bookings`, AJAX data | Medium |
| 3 | Booking detail drawer | `admin.bookings.show` | High |
| 4 | Support tickets | `admin.support.tickets.*` | Medium |
| 5 | Reports / finance | `admin.reports`, ledger | Medium |
| 6 | Settings / suppliers | `admin.settings.*`, `admin.api-settings` | High |

## Cutover verification per module

- Route health audit (`ota:route-page-health-audit`)
- RBAC tests for staff vs admin
- Responsive + accessibility smoke on `/testdash`
- SFTP upload only changed files

## Rollback

Disable `/testdash` mount or revert to Blade-only URLs; Laravel ops paths unchanged throughout DASH-01.
