# Typography and Compactness Contract

Phase: **JP-UI-02**

## Selected fonts

| Role | Family | Source / license |
|------|--------|------------------|
| Body (`font-sans`) | **Inter** | Google Fonts via `next/font` — SIL Open Font License |
| Display (`font-display`) | **Space Grotesk** | Google Fonts via `next/font` — SIL Open Font License |

## Loading strategy

- `next/font/google` with `display: "swap"`
- CSS variables: `--font-body`, `--font-display`
- Mapped to `--jp-font-sans` and `--jp-font-display` in tokens

## Fallback stack

`system-ui, -apple-system, "Segoe UI", sans-serif`

## Numeric alignment

- `font-feature-settings: "tnum"` on `body`
- Utility: `.tabular-nums` for prices, references, tables

## Type scale (semantic)

| Token | Tailwind | Use |
|-------|----------|-----|
| `--jp-text-xs` | `text-jp-xs` | Captions, badges |
| `--jp-text-sm` | `text-jp-sm` | Labels, helper text |
| `--jp-text-base` | `text-jp-body` | Body copy |
| `--jp-heading-sm` | `text-jp-h3` | Card titles |
| `--jp-heading-md` | `text-jp-xl` | Section titles |
| `--jp-heading-lg` | `text-jp-h2` | Page titles |
| `--jp-heading-xl` | `text-jp-h1` | Hero headlines |

## Compactness rules

- Prefer token sizes over arbitrary `text-[Npx]`
- Dense flows (results, checkout) use `leading-snug` / `text-jp-sm` where already established
- Full homepage compact search parity deferred to **JP-UI-03**

## Mockup alignment rationale

Inter + Space Grotesk provide a licensed, production-safe geometric pairing close to approved mockup density without importing unlicensed mockup font files.
