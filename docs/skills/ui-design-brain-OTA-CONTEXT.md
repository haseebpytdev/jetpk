# OTA project context (read with ui-design-brain)

This file applies when using **ui-design-brain** inside the **Hayat OTA** repo.
**OTA rules win** on any conflict with generic skill defaults.

**Install:** after cloning ui-design-brain into `.cursor/skills/ui-design-brain/`,
copy this file there as `OTA-CONTEXT.md` (`.cursor/` is gitignored).

## When to load

- Public/agent/admin **v2** UI (`/v2` preview lane)
- Blade + `public/css/v2/*` + `resources/views/ui/site/v2/**`
- New components, forms, tables, modals, empty states, skeletons

Read first:

1. `.cursor/rules/v2-ui-implementation.mdc`
2. `docs/design-references/navan-style-reference.md` (inspiration only)
3. `.cursor/skills/ui-design-brain/SKILL.md` + `components.md` (component patterns)

## OTA overrides (non-negotiable)

| ui-design-brain default | OTA rule |
|-------------------------|----------|
| 8px spacing grid | **4px grid** (`--ota-v2-space-*`) |
| Generic accent / purple SaaS | **`--ota-v2-color-action`** via `--brand-primary` / client palette |
| Inter/Roboto as default | **Plus Jakarta Sans** (public shell); match existing `ota-*` / `ota-v2-*` |
| Freestyle Tailwind redesign | **Blade + existing v2 atoms**; no v1 CSS/JS edits |
| One design for all pages | **v2 lane only** until scoped; `/` stays v1 |
| Decorative motion | Motion confirms state only; see v2 motion table |
| Primary buttons everywhere | **One primary CTA per view/section** |

## Stack

- Laravel Blade, Alpine.js, Font Awesome
- v2 tokens: `public/css/v2/ota-design-system-v2.css`, `ota-public-v2.css`
- Views: `ui_view()` overlays under `resources/views/ui/site/v2/`
- Links: `client_route()`, `ui_preserve_route()` — keep `/v2` namespace

## Hard stops

Do not use this skill to change booking, checkout, supplier APIs, PNR,
ticketing, payment, or cancellation logic.

## Deploy

v2 CSS/JS: upload **both** Laravel `public/css/v2/*` and live web-root `css/v2/*`
(see `docs/v2-master-client-ui.md`).
