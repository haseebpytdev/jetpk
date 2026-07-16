# JetPK Standalone Fallback Audit

**Date:** 2026-07-17  
**Scope:** `app/` and `config/` only (excludes `resources/views`, `tests/`, `docs/`, `storage/`)  
**Context:** After JETPK-STANDALONE-PORTAL closure — `config/client.php` sets `standalone=true`, canonical slug `jetpk`, `allow_cross_client_views=false`.

---

## Scan command (reproducible)

```bash
rg -n "Parwaaz|haseeb-master|YoursDomain|YD Travel|ota\.haseebasif\.com|haseebasif" app config
```

**Result (2026-07-17):** 39 files matched (listed below by classification).

---

## Classification legend

| Bucket | Meaning |
|--------|---------|
| **active-runtime** | Can execute on JetPK standalone deploy and affect behavior or URLs |
| **active-runtime-mitigated** | Runtime path references legacy identifiers but remaps/blocks/gates them for JetPK |
| **diagnostic-default** | Config default or env fallback; overridden on dedicated JetPK server |
| **audit-only** | Read-only scanners, forbidden-term lists, or CLI `--client=haseeb-master` defaults |
| **comment-doc** | Docblocks, comments, or planning stubs only |
| **legacy-compat-gated** | Multi-tenant compatibility; disabled when `client.standalone=true` |

---

## Config (`config/`)

| File | Match | Bucket | Notes |
|------|-------|--------|-------|
| `config/ota_client.php` | `ota.haseebasif.com` in `public_webroot_path` default | **diagnostic-default** | `OTA_PUBLIC_WEBROOT_PATH` overrides on dedicated server; used for on-disk asset diagnostics, not URL branding |
| `config/ota-ui.php` | `haseebasif.com/public_html/ota.haseebasif.com` | **comment-doc** | `public_asset_root_reminder` — deploy documentation string |
| `config/jetpk_email.php` | `Parwaaz`, `YD Travel`, `YoursDomain`, `haseeb-master` | **audit-only** | Forbidden branding needles for `ota:jetpk-email-template-audit` |
| `config/jetpk_operational_email.php` | same | **audit-only** | Forbidden branding list for operational email audits |
| `config/client_features.php` | `haseeb-master` client block | **comment-doc** | Planning stub for `ota:jetpk-deep-flow-isolation-audit`; not a runtime gate |

**No `config/` match is an active Parwaaz UI fallback** when `OTA_CLIENT_SLUG=jetpk` and `OTA_STANDALONE=true`.

---

## App — active runtime (mitigated or intentional)

| File | Match | Bucket | JetPK standalone behavior |
|------|-------|--------|---------------------------|
| `app/Support/Emails/JetpkEmailBrandingResolver.php` | `support@haseebasif.com` | **active-runtime-mitigated** | Legacy sender in blocklist → canonical `ota@jetpakistan.pk` |
| `app/Support/Client/ClientProfileConfigReader.php` | `haseeb-master` default for `is_master_profile` | **legacy-compat-gated** | `is_master_profile` false when `client.standalone=true` (line 216–217) |
| `app/Support/Auth/SocialOAuthClientContext.php` | `haseeb-master` slug compare | **legacy-compat-gated** | OAuth redirect parity for master slug; JetPK uses `jetpk` / unprefixed routes |
| `app/Services/Client/CurrentClientContext.php` | docblock `haseeb-master` | **comment-doc** | Describes default profile resolution history |
| `app/Http/Middleware/ResolvePreviewClient.php` | docblock default slug | **comment-doc** | Redirect behavior documented; JetPK default slug is `jetpk` via env |
| `app/Support/Client/ClientPublicWebrootPath.php` | `ota.haseebasif.com` in comment | **comment-doc** | Example production path |

---

## App — audit / CLI only (not user-facing runtime)

| File | Purpose |
|------|---------|
| `app/Support/Audits/JetpkMasterTraceAuditService.php` | Scans codebase for Master/Parwaaz/YD leaks |
| `app/Support/Audits/JetpkDashboardRouteAuditService.php` | Forbidden brand strings in dashboard routes |
| `app/Support/Audits/JetpkHomepageContentAuditService.php` | Homepage content leak needles |
| `app/Support/Audits/JetpkAirportParityAuditService.php` | Asserts no `haseebasif.com` in airport routes/blades |
| `app/Support/Audits/JetpkPhase9hDAuditService.php` | Email shell Parwaaz detection |
| `app/Support/Audits/HaseebMasterRouteSafetyCatalog.php` | MC-5C curated route matrix for master deployment |
| `app/Support/Audits/HaseebMasterRouteSafetyAuditService.php` | MC-5C audit executor |
| `app/Console/Commands/JetpkMasterTraceAuditCommand.php` | CLI wrapper for trace audit |
| `app/Console/Commands/JetpkBrandingConsumptionAuditCommand.php` | Forbidden brand scan |
| `app/Console/Commands/JetpkDashboardThemeAuditCommand.php` | Dashboard theme leak scan |
| `app/Console/Commands/JetpkDashboardFormAuditCommand.php` | Form branding scan |
| `app/Console/Commands/JetpkCanonicalBusinessEmailAuditCommand.php` | Legacy email detection (`support@haseebasif.com`) |
| `app/Console/Commands/JetpkUniversalEmailRenderAuditCommand.php` | Email render leak scan |
| `app/Console/Commands/JetpkPageSettingsParityAuditCommand.php` | Page settings parity needles |
| `app/Console/Commands/JetpkSitemapAuditCommand.php` | `/jetpk/` and `/haseeb-master/` URI risk |
| `app/Console/Commands/OtaJetpkEmailTemplateAuditCommand.php` | Template forbidden terms |
| `app/Console/Commands/OtaJetpkFlowLeakAuditCommand.php` | Flow leak / hardcoded master links |
| `app/Console/Commands/OtaJetpkDedicatedPackageAuditCommand.php` | Package path forbidden refs |
| `app/Console/Commands/OtaRouteSafetyAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientViewAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientViewSmokeCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientThemeAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientLayoutAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientRouteParityAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaClientRouteParityStatusCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaRuntimeLayoutMigrationAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaUiRuntimeAuditCommand.php` | `--client=haseeb-master` default |
| `app/Console/Commands/OtaAdminUiAuditCommand.php` | haseeb-master admin v1 inventory |
| `app/Console/Commands/OtaSyncCurrentClientProfileCommand.php` | Skips sync when slug is `haseeb-master` |

All rows in this section are **audit-only** unless explicitly run via Artisan.

---

## Standalone enforcement (active-runtime)

These files **block** cross-client fallback without matching strings in the grep list:

| File | Mechanism |
|------|-----------|
| `config/client.php` | `standalone`, `canonical_client`, `fallback_policy.*` |
| `app/Services/Client/RuntimeViewResolver.php` | `requiresStrictThemedView()` for `customer`, `agent`, `frontend`, `mobile` |
| `app/Services/Client/ClientProfileResolver.php` | Canonical `jetpk` deployment resolution (modified this phase) |

---

## Risk summary

| Risk | Status |
|------|--------|
| Portal page renders Parwaaz `dashboard.*` body via silent fallback | **Closed** — themed blades exist; strict standalone throws if missing |
| Visible Parwaaz/YD in portal HTML from `app/` | **None found** in `app/` (views audited separately) |
| `support@haseebasif.com` in outbound email | **Mitigated** — remapped to `ota@jetpakistan.pk` |
| Master slug in production URLs for JetPK | **Mitigated** — `OTA_CLIENT_SLUG=jetpk`, `ResolvePreviewClient` redirects prefixed paths |
| Stale `public_webroot_path` default | **Low** — diagnostic only; set `OTA_PUBLIC_WEBROOT_PATH` on dedicated host |

---

## Regenerate

```bash
rg -n "Parwaaz|haseeb-master|YoursDomain|YD Travel|ota\.haseebasif\.com|haseebasif" app config
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
```

Expected: test pass; no new **active-runtime** rows without classification.
