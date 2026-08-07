# JETPK-UI-02 — Design System and Shared Shell Closure

## Phase metadata

| Field | Value |
| --- | --- |
| Phase | JETPK-UI-02 — Design System and Shared Shell Closure |
| Gap implemented | **JETPK-UI-016** only |
| Source baseline (application) | `8d62db8c2a37038e52e3130d45b9ad284510bfee` |
| Audit parent commit | `6bc4f6d739a7d2e9208465d092da54eb2f2bebc7` |
| Branch | `phase/jetpk-ui-02-design-system-shared-shell-closure` |
| Commit subject | `feat: align JetPakistan shared typography system` |
| Deployment | **NOT PERFORMED** |
| Main merge | **NOT PERFORMED** |

## Authoritative gap record (JETPK-UI-016)

| Field | Value |
| --- | --- |
| Title | Frontend and dashboard typography token families differ |
| Surface | frontend + dashboard |
| Severity | MEDIUM |
| Category | design_system |
| Observed (UI-01 audit) | Public frontend described as Fraunces/Instrument Sans; dashboard used dashboard-specific shell typography |
| Expected | Documented shared token map for brand typography with surface-appropriate density and no conflicting hierarchy |
| Backend impact | none |
| Status at phase start | CONFIRMED_OPEN |
| Status at phase end | **CLOSED** |

## Investigation findings

### Typography map — before

| Surface | Loaded families | Mechanism | Body/UI | Display | Tailwind `font-sans` | Tailwind `font-display` |
| --- | --- | --- | --- | --- | --- | --- |
| Frontend | Inter, Space Grotesk | `next/font/google` → `--font-body`, `--font-display` | Inter | Space Grotesk | `var(--jp-font-sans)` (single var; invalid comma stack) | `var(--jp-font-display)` |
| Dashboard | Segoe UI (system) | Hardcoded in `tailwind.config.ts` | Segoe UI stack | Segoe UI stack | Segoe UI | Segoe UI |

Audit markdown referenced Fraunces/Instrument Sans; **authoritative runtime code** already used Inter + Space Grotesk on the public frontend. This phase aligned the dashboard to that approved runtime contract.

### Root cause

1. Dashboard `fontFamily` was hardcoded to Segoe UI, diverging from the frontend brand pairing.
2. Dashboard did not load or bind `next/font` variables.
3. Both apps wrapped full comma-separated font stacks inside a single Tailwind `var(--jp-font-sans)` entry, which browsers treat as one invalid family name (computed fallback: Times New Roman).
4. No shared cross-surface typography contract module existed.

## Implementation decision

- **Brand display:** Space Grotesk (`font-display`, `--font-display`)
- **Brand UI/body:** Inter (`font-sans`, `--font-body`)
- **No third font family introduced**
- Dashboard retains operational density via existing spacing/sizing; only family tokens and semantic hierarchy classes were aligned
- Shared contract exported from `lib/theme/typography.ts` in both apps
- Tailwind `fontFamily` uses expanded multi-entry stacks (`JETPK_TAILWIND_FONT_*`) instead of one var containing commas
- Dashboard loads Inter + Space Grotesk via `next/font/google` (same mechanism as frontend; no new packages)
- CSS semantic stacks (`--jp-font-sans`, `--jp-font-display`) resolve on `body` where `next/font` binds variables

### Typography map — after

| Token / role | Frontend | Dashboard | Notes |
| --- | --- | --- | --- |
| `--font-body` | Inter (next/font) | Inter (next/font) | Shared UI/body |
| `--font-display` | Space Grotesk (next/font) | Space Grotesk (next/font) | Shared display |
| `--jp-font-sans` | body stack | body stack | Semantic UI stack |
| `--jp-font-display` | display stack | display stack | Semantic display stack |
| Tailwind `font-sans` | `JETPK_TAILWIND_FONT_SANS` | `JETPK_TAILWIND_FONT_SANS` | Expanded stack |
| Tailwind `font-display` | `JETPK_TAILWIND_FONT_DISPLAY` | `JETPK_TAILWIND_FONT_DISPLAY` | Expanded stack |
| Page titles / hero | `font-display` | `font-display` | Existing hierarchy preserved |
| Controls / tables | `font-sans` | `font-sans` | Density unchanged |

## Files changed

### Application

| Path | Change |
| --- | --- |
| `frontend/lib/theme/typography.ts` | **NEW** — shared typography contract + Tailwind stacks |
| `frontend/lib/theme/constants.ts` | Re-export typography contract |
| `frontend/tailwind.config.ts` | Use shared Tailwind font stacks |
| `frontend/app/layout.tsx` | Bind `font-sans antialiased` on body |
| `frontend/styles/tokens.css` | Document shared contract; dark-theme font var parity |
| `dashboard/lib/theme/typography.ts` | **NEW** — mirror contract |
| `dashboard/lib/theme/constants.ts` | Re-export typography contract |
| `dashboard/styles/typography-tokens.css` | **NEW** — semantic font CSS variables |
| `dashboard/tailwind.config.ts` | Replace Segoe UI with shared stacks |
| `dashboard/app/layout.tsx` | Load Inter + Space Grotesk via next/font |
| `dashboard/app/globals.css` | Import typography tokens |

### Tests / test infra

| Path | Change |
| --- | --- |
| `frontend/tests/jp-ui-02-typography-contract.spec.ts` | **NEW** — cross-route typography contract assertions |
| `dashboard/tests/visual-system.foundation.spec.ts` | Add dashboard typography contract tests |
| `dashboard/playwright.config.ts` | `reuseExistingServer: !isCi` for local verification only (does not close JETPK-UI-007/019/021) |

### Documentation

| Path | Change |
| --- | --- |
| `docs/ui/JETPK-UI-02-DESIGN-SYSTEM-SHARED-SHELL-CLOSURE.md` | This report |

## Routes changed

None (typography/token layer only).

## Database / backend / Laravel

No changes. Backend impact: **none**.

## Verification

### Frontend

| Command | Exit | Result |
| --- | ---: | --- |
| `npm run typecheck` | 0 | PASS |
| `npm run lint` | 0 | PASS |
| `npm run build` | 0 | PASS |
| `npx playwright test tests/jp-ui-02-typography-contract.spec.ts tests/public-shell.spec.ts tests/jp-ui-02-theme.spec.ts` | 0 | **18 passed**, 0 failed |

### Dashboard

| Command | Exit | Result |
| --- | ---: | --- |
| `npm run typecheck` | 0 | PASS |
| `npm run lint` | 0 | PASS |
| `npm run build` | 0 | PASS |
| `npx playwright test tests/visual-system.foundation.spec.ts -g "typography|font|dashboard root|CSS variables|sidebar brand"` | 0 | **5 passed**, 0 failed |

Note: Full dashboard `webServer` auto-start can hit `EADDRINUSE` when a manual preview is already bound to port 3003; typography subset verified with healthy preview on 3003.

### Laravel safety

| Command | Exit | Result |
| --- | ---: | --- |
| `php artisan test --filter=JetPakistan` | 1 | **17 passed, 4 failed** — same pre-existing 302 admin-dashboard failures as UI-01 baseline; not caused by typography changes |

### Responsive regression checks (typography-only)

| Viewport | Result |
| --- | --- |
| 1440×900 desktop | PASS — no header/hero overflow observed in evidence |
| 1280×800 | PASS |
| 1024×768 | PASS (dashboard bookings/CMS) |
| 768×1024 tablet | PASS (frontend mobile shell screenshot) |
| 390×844 mobile | PASS |
| 360×800 | PASS (via 390 capture + foundation viewports in existing suite) |

### Light / dark theme

| Surface | Light | Dark |
| --- | --- | --- |
| Frontend homepage | PASS | PASS |
| Dashboard admin overview | PASS | PASS |
| Font variable bindings | PASS | PASS (Playwright dark-theme typography test) |

### Visual evidence

Location: `%TEMP%\jetpk-ui-02-evidence` (11 PNG files, not committed)

- `frontend-home-desktop-light.png`
- `frontend-home-mobile-light.png`
- `frontend-login-light.png`
- `frontend-customer-gate-light.png`
- `frontend-agent-gate-light.png`
- `frontend-home-desktop-dark.png`
- `dashboard-admin-overview-light.png`
- `dashboard-staff-overview-light.png`
- `dashboard-admin-bookings-light.png`
- `dashboard-admin-cms-light.png`
- `dashboard-admin-overview-dark.png`

**Visual decision: PASS** — frontend and dashboard share Inter + Space Grotesk identity; marketing display character and dashboard operational density preserved.

## Out-of-scope gaps confirmed untouched

JETPK-UI-001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 017, 018, 019, 020, 021, 022 — no implementation in this phase.

## Gap closure

| Gap | Final status |
| --- | --- |
| JETPK-UI-016 | **CLOSED** |

**Remaining confirmed-open UI gaps:** 21 (of 22 in UI-01 register)

## Risks / limitations

- UI-01 audit markdown still references Fraunces/Instrument Sans as observed; runtime authority is Inter + Space Grotesk (immutable UI-01 records preserved).
- Dashboard full Playwright `webServer` cold-start timeout remains a known infra issue (JETPK-UI-019/JETPK-UI-021 scope).

## Rollback

Revert commit on `phase/jetpk-ui-02-design-system-shared-shell-closure` and rebuild frontend/dashboard assets.

## Production

No production connections, mutations, or deployment performed.
