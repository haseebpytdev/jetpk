# Company Profile authority — CORRECTION-06

```
COMPANY_PROFILE_CANONICAL_UI=Laravel Admin Branding / Company profile (JetPakistan theme Blade)
COMPANY_PROFILE_CANONICAL_ROUTE=/admin/settings/branding
COMPANY_PROFILE_DB_MODEL=AgencySetting (agency_settings)
COMPANY_PROFILE_BRANDING_MODEL=AgencySetting + AgencyMedia (branding collection)
COMPANY_PROFILE_LOGO_FIELD=logo_path
COMPANY_PROFILE_FAVICON_FIELD=favicon_path
```

| Surface | Classification |
| --- | --- |
| `themes/admin/jetpakistan/settings/branding.blade.php` via `AgencyBrandingController` + `client_view('settings.branding','admin')` | **CANONICAL editable UI** |
| `resources/views/dashboard/admin/settings/branding.blade.php` | **LEGACY FALLBACK** for RuntimeViewResolver when theme view missing — same controller; not a second store |
| Next Dashboard settings workspaces | No Company Profile logo/favicon editor |
| Public config API `logo_url`/`favicon_url` | **READ-ONLY PROJECTION** of Company Profile |
