# Favicon authority inventory — CORRECTION-06

Canonical runtime authority: Company Profile (`agency_settings.favicon_path` → `JetpkCompanyBrandingResolver` → public config `favicon_url` → Next metadata / Blade link).

| PATH | ACTION |
| --- | --- |
| Company Profile storage media | KEEP_CANONICAL |
| `public/client-assets/jetpk/favicon/favicon.ico` | KEEP_REQUIRED_FALLBACK |
| `public/client-assets/jetpk-assets/favicon/*` | KEEP_REQUIRED_FALLBACK (`jetpk-assets` profile) |
| `public/favicon.ico` | KEEP_REQUIRED_FALLBACK |
| `frontend/public/favicon.ico` | KEEP_REQUIRED_FALLBACK |
| `frontend/app/favicon.ico` | KEEP_REQUIRED_FALLBACK |
| `frontend/public/client-assets/jetpk/favicon/favicon.ico` | KEEP_REQUIRED_FALLBACK |
| Dashboard `generateMetadata` icons via public config | KEEP_CANONICAL (wired) |
| `frontend/app/icon.png` | DELETE_OBSOLETE (removed — was default Next metadata icon) |
| `public/client-assets/client-demo/favicon` | KEEP_REQUIRED_FALLBACK (demo profile) |

Targets: FAVICON_AUTHORITIES=1; DEFAULT_NEXT_FAVICON=0; MASTER/PARWAAZ_FAVICON=0.
