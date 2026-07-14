# JetPakistan deployment notes

**Slug:** `jetpk`  
**Theme:** `jetpakistan`  
**Assets:** `jetpk-assets`  
**Preview (master):** `/jetpk/home`

## Roadmap

See `docs/JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md` and `docs/jetpk/README.md`.

## Before first production deploy

1. Fill `deployment.json` SSH/SFTP paths (no passwords in git).
2. Upload logo + favicon to `public/client-assets/jetpk-assets/` (see README there).
3. Copy `env.production.example` → server `.env`.
4. Run `php artisan ota:seed-jetpakistan-client-profile` on server.
5. Follow `docs/jetpk/sftp-deployment-checklist.md`.

## Master workspace QA

Keep `OTA_CLIENT_SLUG=haseeb-master` on master. Test JetPK at `/jetpk/*` with parity enabled.

## Content reference

Live site copy notes: `docs/content-source-notes.md`.

## Support contacts (product)

- Phone: 0311 1222427
- Email: ticketingjp@jetpakistan.com
- Public: support@jetpakistan.com
