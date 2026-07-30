# JP-UI-05A — Dashboard Hydration Root Cause and Fix

## Reproduction (before fix)

| Field | Value |
|-------|-------|
| Route | `/admin/dashboard/payments`, `/admin/dashboard/users`, `/staff/dashboard/bookings` |
| Fixture | `?dataSourcePreview=fixture` |
| Theme | `localStorage jp-theme-preference=light` (hydration tests) |
| Viewport | Desktop default |
| Console | `Minified React error #418` (`args[]=HTML`) |
| Page error | Same React #418 thrown as unhandled page error |

### Prior suppression (removed)

`frontend/tests/visual-audit/jp-ui-05-helpers.ts` contained `filterBenignPageErrors()` which stripped `Minified React error #418` from `pageErrors` when `application === "dashboard"`. This allowed JP-UI-05 to report 132/132 with zero hydration failures while React #418 still occurred. **Removed in JP-UI-05A.**

## Root causes

### 1. Invalid HTML nesting (`<p>` wrapping block elements)

`CardDescription` rendered a `<p>`. Mobile record cards placed `<div>`, `<dl>`, and badge rows inside `CardDescription`, producing invalid HTML. Browsers corrected DOM differently between SSR and client hydration → React #418 (`HTML` mismatch).

**Fix:** `CardDescription` now renders `<div>`. Mobile cards use plain `<div className="text-sm text-jp-muted">` instead of nesting blocks inside description.

**Files:** `dashboard/components/ui/card.tsx`, `dashboard/features/users/components/user-mobile-card.tsx`, `dashboard/features/roles/components/role-mobile-card.tsx`, `dashboard/features/permissions/components/permission-mobile-card.tsx`, `dashboard/features/cms/components/cms-data-table.tsx`, `dashboard/features/reports/components/report-data-table.tsx`

### 2. Table and Card primitives dropped children

`Table`, `TableHead`, `TableBody`, `TableRow`, `Th`, and `Td` were self-closing and did not render `{children}`. `Card`, `CardTitle`, and `CardDescription` had the same pattern. SSR emitted empty shells; client hydrated with full content → React #418 on payments, bookings, users, forbidden states, and all list modules.

**Fix:** All primitives now explicitly render `{children}`.

**Files:** `dashboard/components/ui/table.tsx`, `dashboard/components/ui/card.tsx`

### 3. Theme SSR/client parity

Root layout had hardcoded `data-theme="light"` on `<html>` while the bootstrap script and `ThemeProvider` could resolve dark/system from `localStorage`/`matchMedia` before hydration completed.

**Fix:**

- Remove hardcoded `data-theme` from server `<html>`
- Add dashboard `ThemeProvider` with stable server defaults; sync preference after mount
- Extend bootstrap script with `jpThemePref` and `jpAuditReset` query params for deterministic visual-audit themes (same pattern as frontend JP-UI-03A)

**Files:** `dashboard/app/layout.tsx`, `dashboard/components/theme/ThemeProvider.tsx`, `dashboard/lib/theme/theme-bootstrap-script.ts`

### 4. Deterministic date formatting

`formatDate` / `formatDateTime` used environment-local timezone without fixed zone.

**Fix:** Fixed `Asia/Karachi` timezone in `dashboard/lib/format.ts`.

## Verification

```bash
cd dashboard
npm run build
npx playwright test tests/jp-ui-05a-hydration.spec.ts tests/jp-ui-05a-rbac.spec.ts -c playwright.config.ts
```

**Result:** 12/12 passed, zero hydration warnings, zero React #418, zero page errors (JP-UI-05A run).

## `suppressHydrationWarning` on `<html>`

Retained only for `data-theme` / `color-scheme` attribute set by inline bootstrap before paint — not used to hide component-level mismatches.
