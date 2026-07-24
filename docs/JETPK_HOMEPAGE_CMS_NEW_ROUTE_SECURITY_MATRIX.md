# JetPK Homepage CMS — New Route Security Matrix

**Branch:** `integration/jetpk-homepage-cms-final`
**Baseline:** `624f3dd`
**Scope:** Routes added in `routes/admin-page-settings.php` between baseline and HEAD

All routes inherit the existing `admin` route group middleware from `bootstrap/app.php` / route service provider (web stack, auth, admin portal). No new public/guest routes were added.

| Method | URI | Route name | Middleware | Controller | AuthZ | CSRF | Profile source | page_key allowlist | Destructive confirm | Transaction |
|--------|-----|------------|------------|------------|-------|------|----------------|-------------------|---------------------|-------------|
| POST | `admin/page-settings/{pageKey}/save-as-default` | `admin.page-settings.save-as-default` | web, auth, admin | `ClientPageSettingsController@saveCurrentAsDefault` | `Gate::authorize('client.page-settings.manage')` | Yes (POST form) | `CurrentClientContext::get()` via `requireProfile()` — never hidden input | `ClientPageKeys::isValid($pageKey)` → 404 | `visual_approval_confirmed` required accepted | Yes — `ClientPageSettingDefaultService` DB transaction |
| POST | `admin/page-settings/{pageKey}/reset/preview` | `admin.page-settings.reset.preview` | web, auth, admin | `ClientPageSettingsController@previewReset` | `Gate::authorize('client.page-settings.manage')` | Yes | `requireProfile()` | `ClientPageKeys::isValid($pageKey)` | No write — read-only preview | No writes |
| POST | `admin/page-settings/{pageKey}/reset/draft` | `admin.page-settings.reset.draft` | web, auth, admin | `ClientPageSettingsController@resetDraft` | `Gate::authorize('client.page-settings.manage')` | Yes | `requireProfile()` | `ClientPageKeys::isValid($pageKey)` | Draft-only reset; Published untouched | Yes — `ClientPageResetService` transaction |
| POST | `admin/page-settings/{pageKey}/reset/publish` | `admin.page-settings.reset.publish` | web, auth, admin | `ClientPageSettingsController@resetAndPublish` | `Gate::authorize('client.page-settings.manage')` | Yes | `requireProfile()` | `ClientPageKeys::isValid($pageKey)` | `reset_and_publish_confirmed` required accepted | Yes — revision snapshot + publish in transaction |

## Access matrix (required)

| Role | Access |
|------|--------|
| Guest | Denied (auth middleware) |
| Customer | Denied |
| Agent | Denied |
| Staff | Denied (unless platform admin impersonation — not a route target) |
| Admin with `client.page-settings.manage` | Allowed |

## Additional controls

- **No GET writes:** all new routes are POST only.
- **No IDOR:** `pageKey` validated against `ClientPageKeys` allowlist; profile resolved server-side.
- **Preview:** existing `beginPreview` unchanged; draft preview uses session flag — robots noindex on preview documented in acceptance.
- **Media destroy:** existing `destroyAsset` retains reference guard (integration test `MediaAssetReferenceGuardTest`).
- **Diagnostic:** `JetpkHomepageContextDiagnostic` has no route — config-gated log only.

## Verdict

**PASS** — four new admin POST routes meet authz, CSRF, allowlist, and transaction requirements.
