# JetPK public asset inventory

## Theme assets (tracked in git)

Base URL: `/themes/frontend/jetpakistan/`

| File | Purpose | Cache bust |
|------|---------|------------|
| `css/tokens.css` | Design tokens (colors, spacing, typography) | `$jpAssetVersion` in layout |
| `css/theme.css` | Shell, auth, legacy ota-* compatibility | Same |
| `css/forms.css` | Inner-page forms and cards | Same |
| `css/search.css` | Homepage search widget layout | Homepage `@push('styles')` |
| `css/search-overrides.css` | Autocomplete, date picker, pax skin | Homepage |
| `js/theme.js` | Theme toggle, loader, header | Layout |
| `js/search.js` | Search widget helpers | Homepage (if used) |
| `js/effects.js` | Visual effects | Homepage |
| `js/reveal.js` | Scroll reveal | Homepage |

**Dual deploy:** If using split web root (`OTA_PUBLIC_WEBROOT_PATH`), upload theme files to **both** Laravel `public/` and live `public_html/` paths.

Increment `$jpAssetVersion` in `layouts/frontend.blade.php` when changing theme CSS/JS.

## Client branding assets (upload — not in git)

Base URL: `/client-assets/jetpk-assets/`

| Path | Required | Notes |
|------|----------|-------|
| `logo/logo.svg` | Yes | Referenced in DB branding |
| `favicon/favicon.ico` | Yes | Layout `<link rel="shortcut icon">` |
| `banners/hero.jpg` | Optional | Future homepage hero |
| `banners/og-image.png` | Optional | Social share |

Source files: design team / jetpakistan.com brand kit.

Upload target (Hostinger example):

```
/home/{user}/domains/jetpakistan.com/public_html/client-assets/jetpk-assets/
```

See `clients/jetpk/deployment.json` for client-specific paths.

## External assets (CDN)

| Asset | Used on |
|-------|---------|
| Google Fonts (Space Grotesk, Inter, IBM Plex Mono) | All JP pages |
| Font Awesome 4.7 | Homepage search |

## Shared platform assets (not JetPK-owned)

Loaded by legacy views inside JP shell:

- `public/css/ota-public.css`
- Platform JS for autocomplete, booking widgets
- Airline logos, flags under `public/images/`

## Pre-deploy asset checklist

- [ ] All 9 theme files uploaded to web root
- [ ] Logo + favicon uploaded to `jetpk-assets`
- [ ] `$jpAssetVersion` bumped if CSS/JS changed
- [ ] `/jetpk/home` — logo and favicon load (no 404)
- [ ] Browser hard-refresh confirms new CSS

## Rollback

Restore prior theme file versions + prior `$jpAssetVersion`; re-upload client assets from backup.

See [rollback-checklist.md](rollback-checklist.md).
