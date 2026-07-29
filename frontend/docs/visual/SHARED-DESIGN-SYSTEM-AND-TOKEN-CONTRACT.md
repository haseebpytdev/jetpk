# Shared Design System and Token Contract

Phase: **JP-UI-02**  
Baseline: `952a39a`

## Authoritative sources

| Layer | Path |
|-------|------|
| CSS tokens | `frontend/styles/tokens.css` |
| Tailwind bridge | `frontend/tailwind.config.ts` |
| Global utilities | `frontend/app/globals.css` |

## Semantic color tokens

Light and dark values are defined under `:root` / `[data-theme="light"]` and `[data-theme="dark"]`.

| Token | Purpose |
|-------|---------|
| `--jp-bg`, `--jp-bg-subtle` | Page backgrounds |
| `--jp-surface*` | Cards, panels, inputs |
| `--jp-text*` | Body, muted, subtle text |
| `--jp-border*` | Dividers and control borders |
| `--jp-brand*` | Primary actions and brand accents |
| `--jp-accent*`, `--jp-info*` | Secondary emphasis |
| `--jp-success/warning/danger*` | Status surfaces |
| `--jp-overlay` | Drawer/backdrop scrims |
| `--jp-skeleton*` | Loading placeholders |

Legacy `--jp-primary*` aliases map to `--jp-brand*` for backward compatibility.

## Typography tokens

| Token group | Notes |
|-------------|-------|
| `--jp-font-sans` | Inter via `next/font` |
| `--jp-font-display` | Space Grotesk via `next/font` |
| `--jp-text-*` | Compact fluid body scale |
| `--jp-heading-*` | Display hierarchy |

## Spacing and geometry

- Page gutter: `--jp-page-gutter` / `px-jp-xl`
- Public max width: `--jp-maxw` / `max-w-jp-container`
- Booking width: `--jp-maxw-booking`
- Narrow forms: `--jp-maxw-narrow`
- Header height: `--jp-nav-height`
- Control height: `--jp-control-height`

## Motion tokens

| Token | Value role |
|-------|------------|
| `--jp-motion-fast` | Buttons, toggles |
| `--jp-motion-standard` | Surfaces, drawers |
| `--jp-motion-slow` | Illustrative entrances |
| `--jp-ease-standard` | Default easing |

## Consumption rules (JP-UI-03+)

1. Route components use semantic Tailwind classes (`bg-jp-surface`, `text-jp-muted`) — not raw hex.
2. New tokens require updates to `tokens.css` **and** `tailwind.config.ts`.
3. Do not introduce page-specific color systems.
4. Status colors must use semantic success/warning/danger tokens.

## Tailwind alias fixes (JP-UI-02)

Added missing mappings: `jp-bg`, `jp-text-muted`, `jp-border-soft`, `jp-accent-soft`, `jp-base`, semantic status colors.
