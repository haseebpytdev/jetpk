# JETPK-UI-02A — Global Legacy Typography Authority and Project-Wide Font Closure

## Phase metadata

| Field | Value |
| --- | --- |
| Phase | JETPK-UI-02A — Global Legacy Typography Authority |
| Target gap | JETPK-UI-016 (cross-surface typography inconsistency) |
| Application baseline | `8d62db8c2a37038e52e3130d45b9ad284510bfee` |
| UI-01 audit parent | `6bc4f6d739a7d2e9208465d092da54eb2f2bebc7` |
| Branch | `phase/jetpk-ui-02a-global-legacy-typography` |
| Commit subject | `feat: restore JetPakistan legacy typography globally` |
| Deployment | **NOT PERFORMED** |

## Legacy typography discovery

### Evidence table

| Candidate | Source path(s) | Classification | Body | Display | Dashboard | Blade | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **Inter** | `public/themes/frontend/jetpakistan/css/tokens.css`, JetPakistan Blade layouts, `frontend/app/layout.tsx`, `docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md` | **AUTHORITATIVE_LEGACY** | ✓ | fallback | was missing | ✓ | HIGH |
| **Space Grotesk** | Same JetPakistan theme tokens + Blade Google Fonts links | **AUTHORITATIVE_LEGACY** | fallback | ✓ | was missing | ✓ | HIGH |
| **IBM Plex Mono** | JetPakistan theme tokens (`--font-mono`), Blade layouts | **AUTHORITATIVE_LEGACY** | — | — | — | ✓ numeric/labels | HIGH |
| Instrument Sans | `resources/css/{app,frontend,dashboard}.css` | **LATER_REDESIGN** (Laravel Vite scaffold) | — | — | — | partial | MEDIUM |
| Plus Jakarta Sans | `public/css/ota-public.css`, `ota-design-system.css` | **GENERIC_FALLBACK** (shared OTA stack) | partial | partial | partial | partial | MEDIUM |
| Fraunces / Instrument Sans (audit note) | UI-01 audit observation only | **UNRELATED / STALE AUDIT** | — | — | — | not in JetPakistan code | LOW |
| Segoe UI | `dashboard/tailwind.config.ts` (pre-phase) | **LATER_REDESIGN** (dashboard-only drift) | — | — | ✓ | — | HIGH |

### Locked legacy authority

| Token | Value |
| --- | --- |
| `LEGACY_BODY_FONT` / `LEGACY_UI_FONT` | **Inter** |
| `LEGACY_HEADING_FONT` / `LEGACY_PRIMARY_DISPLAY` | **Space Grotesk** |
| `LEGACY_NUMERIC_FONT` | **IBM Plex Mono** |
| `LEGACY_FALLBACK_STACK` | `system-ui, -apple-system, "Segoe UI", sans-serif` |
| Loading | `next/font/google` (frontend + dashboard); Google Fonts links (Blade JetPakistan layouts) |
| Weights | Inter 400–700; Space Grotesk 400–700; IBM Plex Mono 400/500/600 |

**Decision:** proven **two-family legacy system** (display + UI/body) plus mono for fares/codes — not Fraunces/Instrument Sans.

## Previous vs new systems

| Surface | Before | After |
| --- | --- | --- |
| Frontend Next.js | Inter + Space Grotesk; mono = Cascadia stack | Inter + Space Grotesk + **IBM Plex Mono**; semantic `--font-jetpk-*` |
| Dashboard Next.js | **Segoe UI** hardcoded | Inter + Space Grotesk + IBM Plex Mono via `next/font` |
| JetPakistan Blade theme | Inter + Space Grotesk + IBM Plex Mono (already correct) | unchanged authority; reinforced with `jetpk-typography-authority.css` on fallbacks |
| Laravel Vite CSS | Instrument Sans | **Inter** |
| Shared OTA CSS (`ota-public`, `ota-design-system`) | Plus Jakarta Sans | unchanged file (non–JetPakistan-safe to edit globally); **overridden** on JetPakistan Blade fallbacks |

### Global semantic token map

| Semantic token | Maps to | Tailwind |
| --- | --- | --- |
| `--font-jetpk-ui` / `--font-jetpk` | Inter (`--font-body`) | `font-sans` |
| `--font-jetpk-display` | Space Grotesk (`--font-display`) | `font-display` |
| `--font-jetpk-mono` | IBM Plex Mono (`--font-mono`) | `font-mono` |
| `--jp-font-sans` | body stack (on `body`) | inherited |
| `--jp-font-display` | display stack (on `body`) | inherited |
| `--jp-font-mono` | mono stack (on `body`) | inherited |

Contract modules: `frontend/lib/theme/typography.ts`, `dashboard/lib/theme/typography.ts`.

## Files changed

### Application

- `frontend/lib/theme/typography.ts` (new)
- `frontend/lib/theme/constants.ts`
- `frontend/tailwind.config.ts`
- `frontend/app/layout.tsx`
- `frontend/styles/tokens.css`
- `dashboard/lib/theme/typography.ts` (new)
- `dashboard/lib/theme/constants.ts`
- `dashboard/styles/typography-tokens.css` (new)
- `dashboard/tailwind.config.ts`
- `dashboard/app/layout.tsx`
- `dashboard/app/globals.css`
- `public/css/jetpk-typography-authority.css` (new)
- `public/themes/admin/jetpakistan/css/jp-admin-ops-overrides.css`
- `resources/css/app.css`, `frontend.css`, `dashboard.css`
- `resources/views/themes/frontend/jetpakistan/layouts/portal.blade.php`
- `resources/views/themes/admin/jetpakistan/layouts/dashboard.blade.php`
- `resources/views/profile/edit-{frontend,dashboard,agent}.blade.php`

### Tests

- `frontend/tests/jp-ui-02a-legacy-typography-contract.spec.ts` (new)
- `dashboard/tests/visual-system.foundation.spec.ts`
- `dashboard/playwright.config.ts` (`reuseExistingServer: !isCi` — local infra only)
- `tests/Feature/JetPakistanLegacyTypographyAuthorityTest.php` (new)

## Verification

### Frontend

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/jp-ui-02a-legacy-typography-contract.spec.ts tests/public-shell.spec.ts` | **11 passed** |

### Dashboard

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| Typography subset `visual-system.foundation.spec.ts` | **4 passed** |

### Laravel

| Command | Result |
| --- | --- |
| `php artisan test tests/Feature/JetPakistanLegacyTypographyAuthorityTest.php` | **2 passed** |

### Visual evidence

`%TEMP%\jetpk-ui-02a-evidence` — 12 PNG captures (public, portals, dashboard, light/dark). **PASS**

### Responsive / theme regression

Sample viewports 1440, 1280, 390 exercised via Playwright + screenshots. No typography overflow regressions observed. Light/dark font bindings preserved.

## Final font-family search (post-implementation)

| Location | Family | Classification |
| --- | --- | --- |
| `frontend/**`, `dashboard/**` typography modules | Inter / Space Grotesk / IBM Plex Mono | **Legacy authority** |
| `public/themes/frontend/jetpakistan/**` | Inter / Space Grotesk / IBM Plex Mono | **Legacy authority** |
| `public/css/jetpk-typography-authority.css` | Inter / Space Grotesk / IBM Plex Mono | **JetPakistan override** |
| `public/css/ota-public.css`, `ota-design-system*.css` | Plus Jakarta Sans | **Shared OTA generic** — overridden on JetPakistan fallbacks |
| `resources/css/*.css` | Inter (updated from Instrument Sans) | **Aligned** |
| Monospace in tables/debug | ui-monospace stacks | **Legitimate mono fallback** |

No unexplained competing JetPakistan **primary** authority remains on implemented surfaces.

## Gap closure

| Gap | Status |
| --- | --- |
| JETPK-UI-016 | **CLOSED** |

**Remaining UI gaps:** 21

## Out of scope confirmed untouched

JETPK-UI-001–015, 017–022 — no implementation.

## Production

No production connections, mutations, or deployment.

## Scope repair (JETPK-UI-02A-R1)

- **UI-02A supersedes** the sibling branch `phase/jetpk-ui-02-design-system-shared-shell-closure` (`588d28b`); that branch must **not** be merged.
- `dashboard/playwright.config.ts` was **restored to the UI-01 audit parent** (`6bc4f6d`) because `reuseExistingServer` is dashboard Playwright infrastructure behavior and is outside global typography scope.
- Targeted typography verification does not depend on committing that config modification; UI-02A typography contract tests and build/typecheck/lint remain the acceptance evidence.
