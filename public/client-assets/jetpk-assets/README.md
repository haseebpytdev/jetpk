# JetPakistan client branding assets

Upload files here for profile `jetpk-assets`. Paths must match `clients/jetpk/branding.json` and DB seed.

## Required

```
logo/logo.svg       (or logo.png — update branding.json if changed)
favicon/favicon.ico
```

## Optional

```
banners/hero.jpg
banners/og-image.png
```

## Deploy

Upload to live web root:

```
public_html/client-assets/jetpk-assets/
```

On master workspace preview, verify at `/jetpk/home` — header logo and favicon should load.

**Do not commit** large binary brand files unless approved for git LFS. Ops upload via SFTP.

## Dual path

If `OTA_PUBLIC_WEBROOT_PATH` is set, ensure files exist on the live public web root used by `ClientPublicWebrootPath`.
