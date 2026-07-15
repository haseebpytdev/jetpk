# JetPK Page Builder — module keys (Dev CP readiness)

Documented for future Dev CP toggles. **Not enforced in this phase.**

| Module key | Purpose |
|------------|---------|
| `page_builder` | Client-scoped Page Settings (draft/publish, assets) |
| `client_theme_palette` | Logo-based auto palette generation + approval |
| `page_live_preview` | Admin draft preview session on public routes |

Platform registry alias (if added later): `OTA_MODULE_PAGE_BUILDER`.

Current behavior: routes are available to platform admins under `/jetpk/admin/page-settings` when client preview context is active. No Dev CP gate yet.
