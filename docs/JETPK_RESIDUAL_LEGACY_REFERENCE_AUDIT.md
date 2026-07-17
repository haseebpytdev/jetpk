# JetPK Residual Legacy Reference Audit

**Phase:** JETPK-STANDALONE-RESIDUAL-LEGACY-REFERENCE-CLEANUP  
**Baseline SHA:** `d943d5b`  
**Date:** 2026-07-17

## Scan command

```bash
git grep -n -i -E 'Parwaaz|haseeb-master|haseeb_master|YoursDomain|YD Travel|ota\.haseebasif\.com|support@haseebasif\.com' -- app config resources/views/themes/customer/jetpakistan resources/views/themes/agent/jetpakistan resources/views/themes/frontend/jetpakistan routes database/seeders
```

## Before cleanup (approximate)

| Area | Match count | Active runtime |
|------|-------------|----------------|
| `app/` | ~45 files | ~8 (email fallback, webroot comment, OAuth slug, audit CLI defaults) |
| `config/` | ~8 files | ~3 (webroot default, ui reminder, master slug compat) |
| JetPK theme views | ~24 files | ~4 rendered fallbacks + ~20 Parwaaz comments |
| `routes/` | 0 | 0 |
| `database/seeders/` | 0 | 0 |

## Classification summary (after cleanup)

| Class | Description | Action |
|-------|-------------|--------|
| **A** Active runtime default | Email/webroot/OAuth defaults | **Removed** — canonical `config/client.php` |
| **B** Active rendered branding | `support@haseebasif.com` in auth/error shells | **Removed** — `config('client.canonical_support_email')` |
| **C** Active route/link | None in portal routes | N/A |
| **D** Prohibited-brand blocklist | `jetpk_email.php`, `jetpk_operational_email.php` | **Preserved** as `prohibited_brand_markers` |
| **E** Test assertion | Audit command forbidden lists, leakage tests | **Preserved** |
| **F** Historical migration | None touched | N/A |
| **G** Documentation/comment | Audit CLI descriptions | **Preserved** (read-only tooling) |
| **H** Dead code | None removed this phase | N/A |
| **I** Standalone=false compat | `client_features.php` `haseeb-master` map, `allow_haseeb_master_prefixed_parity` env key | **Preserved** behind `standalone=false` |

## Files changed this phase

| File | Change |
|------|--------|
| `config/client.php` | `canonical_support_email`, `deprecated_operational_emails` |
| `config/ota_client.php` | `public_webroot_path` default → `base_path('public')` |
| `config/ota-ui.php` | Neutral deployment reminder |
| `config/jetpk_email.php` | `prohibited_brand_markers` + alias |
| `config/jetpk_operational_email.php` | `prohibited_brand_markers` + alias |
| `app/Support/Emails/JetpkEmailBrandingResolver.php` | Config-driven email remap |
| `app/Support/Emails/JetpkEmailBrandingLeakageAuditor.php` | Reads `prohibited_brand_markers` |
| `app/Support/Client/ClientPublicWebrootPath.php` | Neutral comment |
| `app/Support/Client/ClientProfileConfigReader.php` | Canonical master slug default |
| `app/Support/Auth/SocialOAuthClientContext.php` | Canonical slug only |
| `app/Services/Client/CurrentClientContext.php` | Comment |
| `app/Http/Middleware/ResolvePreviewClient.php` | Comment |
| `resources/views/themes/frontend/jetpakistan/errors/partials/shell.blade.php` | Config-driven legacy email remap |
| `resources/views/layouts/auth.blade.php` | Canonical support email |
| `resources/views/ui/site/v2/layouts/auth.blade.php` | Canonical support email |
| `resources/views/errors/layout.blade.php` | Canonical support email |
| `resources/views/themes/**/jetpakistan/**` | Parwaaz comment cleanup (24 files) |

## Allowed remaining matches (active runtime scan)

| Location | Match | Justification |
|----------|-------|---------------|
| `config/jetpk_email.php` | Parwaaz, YD, haseeb-master | **D** `prohibited_brand_markers` |
| `config/jetpk_operational_email.php` | Same | **D** rejection markers |
| `config/client_features.php` | `haseeb-master` | **I** multi-client feature map when `standalone=false` |
| `config/client_route_parity.php` | `allow_haseeb_master_prefixed_parity` | **I** deprecated env flag name |
| `app/Console/Commands/*Audit*` | Legacy strings in scan lists | **G** read-only audit tooling |
| `app/Support/Audits/*` | Scan deny lists | **G** audit-only |
| `tests/**` | Assertion strings | **E** |

## Active runtime defaults after cleanup

**0** — verified by `JetpkResidualLegacyReferenceCleanupTest` and post-scan of JetPK theme trees.

## Reproducible verification

```bash
php artisan test --filter=JetpkResidualLegacyReferenceCleanup
php artisan test --filter=ClientPublicWebroot
php artisan test --filter=JetpkEmailBrandingLeakage
php artisan test --filter=RuntimeViewResolver
php artisan ota:route-page-health-audit --all
```
