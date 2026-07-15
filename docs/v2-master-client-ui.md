# V2 Master Client UI Preview Lane (V2-MC-0)

## Overview

Phase **V2-MC-0** introduces a protected **v2 UI preview lane** that is an **exact clone** of the current v1 theme at launch. v1 remains the live default. v2 work must happen only in copied assets under `public/css/v2/` and `public/js/v2/`.

## Architecture

| Concept | Meaning |
|---------|---------|
| `/v2` | UI preview **namespace** only — not a client slug |
| `/haseeb-master` | Master client slug (v1 default) |
| `/jetpk`, `/easyticket` | Other client slugs |
| `/v2/haseeb-master/admin` | Master client path with forced v2 preview |

## Safety

- v1 is default (`CLIENT_UI_DEFAULT_VERSION=v1`, `CLIENT_UI_FORCE_V1_DEFAULT=true`).
- `/v2` is protected when `CLIENT_UI_PREVIEW_PROTECTION_ENABLED=true`.
- No POST/PUT/PATCH/DELETE routes are exposed under `/v2` (mutating requests return 404).
- Booking, supplier, ticketing, payment, and PNR logic are unchanged.

## Cloned assets

v1 sources are copied to v2 with `-v2` suffix under:

- `public/css/v2/`
- `public/js/v2/`

Layouts load v2 clones via `ui_asset()` when the resolved UI version is v2.

## How to preview

### First access (preview key)

```
/v2?preview_key=YOUR_SECRET
/v2/login?preview_key=YOUR_SECRET
/v2/admin?preview_key=YOUR_SECRET
/v2/groups/search?preview_key=YOUR_SECRET
/v2/lookup-booking?preview_key=YOUR_SECRET
/v2/haseeb-master/admin?preview_key=YOUR_SECRET
/v2/jetpk/admin?preview_key=YOUR_SECRET
```

After a valid key, session grant persists (no key in generated links).

### Sticky session preview (outside `/v2` URL)

```
/ui/v2?preview_key=YOUR_SECRET
/?ui=v2
/admin?ui=v2
```

### Reset

```
/ui/reset
```

## Internal access without key

- Authenticated **platform admin** users
- Active **Developer CP** session (`dev_cp_user_id`)

## Status command

```bash
php artisan ota:client-ui-version-status
```

## Switching v2 to default later (after full QA)

Only after explicit approval:

```env
CLIENT_UI_DEFAULT_VERSION=v2
CLIENT_UI_FORCE_V1_DEFAULT=false
```

## Env keys

See `.env.example` — `CLIENT_UI_*` block.

## V2 UI implementation standards

Agent rule: `.cursor/rules/v2-ui-implementation.mdc` — tokens, buttons, motion,
scope, acceptance, and interaction rules for all future v2 pages.

Design inspiration (not copy): `docs/design-references/navan-style-reference.md`.

Component patterns: `.cursor/skills/ui-design-brain/` (see `OTA-CONTEXT.md` for
OTA-specific overrides).

### Install ui-design-brain (once per machine / clone)

```bash
git clone --depth 1 https://github.com/carmahhawwari/ui-design-brain.git \
  .cursor/skills/ui-design-brain
cp docs/skills/ui-design-brain-OTA-CONTEXT.md \
  .cursor/skills/ui-design-brain/OTA-CONTEXT.md
```

`.cursor/` is gitignored — each developer runs the clone locally. The tracked
OTA overlay lives at **`docs/skills/ui-design-brain-OTA-CONTEXT.md`**.

Update upstream skill:

```bash
cd .cursor/skills/ui-design-brain && git pull
```

## Dual deploy for v2 CSS/JS (mandatory)

`ui_asset()` appends `?v=` from **filemtime** on the Laravel app copy under
`public/css/v2/` and `public/js/v2/`. The browser loads assets from the **live
web root** (`public_html/.../ota.haseebasif.com/css/v2/`).

After every v2 asset change, upload **both**:

| Profile | Path |
|---------|------|
| OTA App - Laravel | `public/css/v2/*`, `public/js/v2/*` |
| OTA Public - Live Web Root | `css/v2/*`, `js/v2/*` on public_html |

If only one copy is updated, `/v2` may show stale or unstyled UI despite a
successful app deploy.
