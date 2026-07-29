# Day/Night Theme Architecture

Phase: **JP-UI-02**

## Supported preferences

| Preference | Storage value | Resolved behavior |
|------------|---------------|-------------------|
| System | `system` | Follows `prefers-color-scheme` |
| Light | `light` | Always light |
| Dark | `dark` | Always dark |

Default: `system`.

## Resolution order

1. Explicit stored preference (`jp-theme-preference` in `localStorage`)
2. System color scheme when preference is `system`
3. Documented default (`system` → light unless OS prefers dark)

Invalid stored values are ignored safely.

## No-flash strategy

1. Inline bootstrap script in `app/layout.tsx` (`lib/theme/theme-bootstrap-script.ts`) runs before paint.
2. Sets `data-theme` and `color-scheme` on `<html>`.
3. `ThemeProvider` hydrates with lazy `useState` reading the same storage key.
4. Preference changes write to `localStorage` synchronously and update `data-theme` immediately.

## Components

| File | Role |
|------|------|
| `components/theme/ThemeProvider.tsx` | Single app-wide provider |
| `components/theme/ThemeSwitch.tsx` | Accessible 3-state cycle control |
| `lib/theme/constants.ts` | Preference types and resolver |

## Placement

- Desktop: `SiteHeader` action cluster
- Mobile: `MobileNavigation` drawer footer
- Auth/booking: via shared `PublicShell` header

Customer/Agent portals inherit the same provider through root layout.

## Persistence

- Key: `jp-theme-preference`
- Values: `system` \| `light` \| `dark` only (no PII)
- Scope: entire `frontend/` app on same origin

## Reduced motion

Theme transitions use short color transitions; global `prefers-reduced-motion` rules disable decorative motion.

## Print

Print stylesheet forces legible light output regardless of theme.

## Known limitations

- Turnstile widget remains light-themed (documented JP-UI-01 gap; unchanged in JP-UI-02).
- Dashboard `dashboard/` Laravel Blade shell is out of scope.
