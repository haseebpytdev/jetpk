# Dashboard Visual Consistency Audit — JETPK-DASH-11 Prompt 01

**Date:** 2026-07-27 (closure pass)  
**Branch:** `phase/jetpk-dash-11-laravel-read-only-integration-foundation`  
**Baseline HEAD:** `1b971e9` (committed); Prompt 01 changes uncommitted  
**Browser URL:** `http://localhost:3001/testdash`  
**Dev command:** `npm run dev` from `C:\Users\khadi\ota-jetpk-dash01\dashboard` (port **3001**)  
**Playwright smoke URL:** `http://127.0.0.1:3002/testdash` (`npm run start:smoke`)

## Browser process verification

| Check | Result |
|-------|--------|
| Command | `npm run dev` in `ota-jetpk-dash01\dashboard` |
| Port | 3001 |
| URL | `http://localhost:3001/testdash` |
| `/testdash` loaded | Yes (HTTP 200) |
| JetPakistan navigation | Yes (`aria-label="Dashboard navigation"`) |
| Worktree | Confirmed via terminal cwd `ota-jetpk-dash01\dashboard` |

## Routes inspected

| Route | 1280 | 1024 | 768 | 390 | 360 |
|-------|------|------|-----|-----|-----|
| `/testdash` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/bookings` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/payments` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/customers` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/suppliers` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/agents` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/pnrs` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/tickets` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/reports` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/cms` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/users` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/users/roles` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/users/permissions` | ✓ | — | ✓ | ✓ | ✓ |
| `/testdash/settings` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `/testdash/audit` | ✓ | ✓ | ✓ | ✓ | ✓ |

1024px inspection: Playwright `visual-system.foundation.spec.ts` route render + overflow loops for nine representative routes; manual dev-server spot-check on `localhost:3001`.

## Prior P2 issues — disposition

### P2-01 — Overview inline header/breadcrumb

| Field | Detail |
|-------|--------|
| Route | `/testdash` |
| Source | `features/overview/overview-page-content.tsx` |
| Was | Inline `<nav>` + `<h1>` outside `PageHeader` |
| Expected | Shared `PageHeader` + `Breadcrumb` |
| **Status** | **Fixed** — uses `PageContainer`, `PageHeader`, `Breadcrumb` |
| Evidence | `overview uses shared page shell at 1024px` test |

### P2-02 — Overview raw Card preview banner

| Field | Detail |
|-------|--------|
| Route | `/testdash` |
| Source | `features/overview/overview-page-content.tsx` |
| Was | Custom emerald `Card` with overview-specific copy |
| Expected | Shared `PreviewDataBanner` |
| **Status** | **Fixed** — `PreviewDataBanner` inserted after `PageHeader` |
| Evidence | Overview preview text matches shared banner; visual test at 1024px |

### P2-03 — Users duplicate notice styling

| Field | Detail |
|-------|--------|
| Route | `/testdash/users` (+ roles/permissions via same shell) |
| Source | `features/users/users-module-shell.tsx` |
| Was | Emerald `PreviewDataBanner` + ad-hoc blue inline notice div |
| Expected | Shared notice components with consistent spacing/typography |
| **Status** | **Fixed** — `AccessControlPreviewNotice` in `data-source-status.tsx` |
| Rationale | Dual notices remain **technically necessary** (fixture data vs access-control boundary); styling now uses shared `Notice` primitive |
| Evidence | `users access-control notice uses shared component` test |

### P2-04 — PreviewDataBanner bookings-specific copy

| Field | Detail |
|-------|--------|
| Route | All modules using `PreviewDataBanner` |
| Source | `components/ui/page-layout.tsx` |
| Was | Text referenced “synthetic bookings” |
| Expected | Module-neutral preview copy |
| **Status** | **Fixed** — “synthetic records for layout and workflow testing” |
| Evidence | Existing `/Preview data/i` assertions pass on reports, bookings, users |

## Issue counts (final)

| Severity | Open |
|----------|------|
| P0 | **0** |
| P1 | **0** |
| P2 | **0** |
| P3 | 2 (optional; documented in visual system doc) |

## Fixes completed (closure pass)

1. Overview aligned to shared page shell and `PreviewDataBanner`
2. `PreviewDataBanner` copy generalized
3. `AccessControlPreviewNotice` shared component for users/RBAC shell
4. 1024px responsive tests added to `visual-system.foundation.spec.ts`
5. Bookings/payments table↔card breakpoint moved to `xl` (1280px) so 1024px with sidebar uses mobile cards — avoids `min-w-[720px]` table overflow
6. `MetricCard` grid and table wrapper containment (`min-w-0`, `truncate`) for adaptive layouts

## Test inventory reconciliation

Per-spec counts (`npx playwright test <spec> --list`):

| Spec | Tests |
|------|------:|
| `audit-security.foundation.spec.ts` | 22 |
| `critical-regression.smoke.spec.ts` | 21 |
| `rbac-matrix.foundation.spec.ts` | **26** |
| `read-only-integration.foundation.spec.ts` | **21** |
| `reports-cms.foundation.spec.ts` | 36 |
| `users-access.foundation.spec.ts` | 21 |
| `visual-system.foundation.spec.ts` | 72 (after 1024px closure tests) |
| **Targeted total** | **219** |

**193 vs 195 explanation:** The prior completion report under-counted two specs:

- `rbac-matrix.foundation.spec.ts`: reported **25**, actual **26** (+1)
- `read-only-integration.foundation.spec.ts`: reported **20**, actual **21** (+1)

Arithmetic: 22+21+**26**+**21**+36+21+48 = **195** (pre-closure visual count). **195 is correct** for that run; the per-spec table in the report was wrong by −2.

After 1024px closure tests (+24 visual tests vs pre-closure 48), targeted total = **219**.

## Verification status

| Check | Result |
|-------|--------|
| Typography consistent | Pass |
| Page shell consistent (incl. overview) | Pass |
| Fixture/read-only notice consistency | Pass |
| 1024px responsive layout | Pass (automated) |
| Mobile overflow (360/390) | Pass |
| Drawer consistency | Pass |
| Table/mobile-card switch | Pass (`xl` breakpoint; cards at 1024px) |
| Loading/empty/error parity | Pass |
| Focus-visible | Pass |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass |
| `npm run build` | Pass (31 routes) |
| Targeted Playwright suite | **219/219** pass (`--retries=0`) |
| 1024px overflow/drawer/notice stability | Pass (`--repeat-each=2`) |
