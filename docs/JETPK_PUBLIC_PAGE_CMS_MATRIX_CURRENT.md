# JetPK Public Page CMS Matrix (Current)

Generated during `integration/jetpk-homepage-cms-final` on baseline `624f3dd`.

| page_key | Frontend consumer | Admin editor | Resolver/default | Draft/Publish | Preview | Revisions/Defaults/Reset | Mobile parity | Status |
|---|---|---|---|---|---|---|---|---|
| home | `themes/.../frontend/home.blade.php` | Page Settings home panels | `ClientPageContentResolver` + `JetpkHomepageSectionData` | Yes | Yes | Yes (this phase) | Canonical responsive (Strategy 1) | **Connected** |
| about | `frontend/about.blade.php` | Page Settings about | Partial / hardcoded | Yes | Yes | Homepage-grade deferred | Responsive theme | **Partial** |
| support | `frontend/support` | Page Settings support | Partial / branding | Yes | Yes | Deferred | Responsive theme | **Partial** |
| footer | `partials/footer.blade.php` | Page Settings footer | `defaultFooterContent()` only | Yes | Yes | Deferred | Shared footer | **Disconnected** |
| global | layouts / branding | Page Settings global | `defaultGlobalContent()` only | Yes | Yes | Deferred | Shared | **Disconnected** |
| group-search | group search pages | Page Settings | Fallback catalog | Yes | Yes | Deferred | Responsive | **Disconnected** |
| booking-lookup | lookup page | Page Settings | Fallback catalog | Yes | Yes | Deferred | Mobile shell | **Disconnected** |
| agent-registration | agent registration | Page Settings | Fallback catalog | Yes | Yes | Deferred | Responsive | **Disconnected** |

Homepage is the only page with full revisions, saved-defaults, and reset in this phase. About/Support retain deployed canonical email (`ota@jetpakistan.pk`) and JetPakistan branding.
