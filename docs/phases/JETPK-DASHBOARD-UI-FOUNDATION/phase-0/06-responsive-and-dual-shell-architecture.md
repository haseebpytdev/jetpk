# Phase 0 · Document 06 — Responsive & Dual-Shell Architecture

> **REVISION 1** — Two corrections: (1) the viewport matrix is expanded to the nine
> required breakpoints; (2) **Internal Staff and Platform Admin have NO `mobile.*`
> tree** — their phone/tablet parity is delivered solely by the responsive
> **desktop** shell (the decomposed `dashboard.blade.php`). Only **Customer** and
> **Agent** have dedicated mobile trees. **Mobile login parity** is added under the
> Authenticated-Entry dependency.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> **The single most important architectural fact for this phase:** "responsive"
> here is **not** pure CSS breakpoints. JetPakistan runs a **server-side
> dual-shell system** — a desktop Blade tree and a separate mobile Blade tree —
> selected per request. Treating the redesign as "add Tailwind breakpoints to one
> template" would be wrong and would miss half the surfaces.

---

## 1. How the shell is chosen (verified in controllers)

Every dashboard page controller does:

```php
if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
    return view('mobile.<role>.<page>', $viewData);          // mobile shell
}
return view(client_view('<page>', '<role>'), $viewData);      // desktop shell
```

- Decision service: **`App\Support\Ui\MobileViewPreference`**
  (`shouldUseMobileShell()`), with session + cookie persistence
  (`rememberInSession()`, `makePreferenceCookie()`).
- User toggle: **`Frontend\MobileViewController`** actions `mobile`, `desktop`,
  `previewMobile`, `previewDesktop`, plus the `desktop-mobile-link` /
  `mobile-app-desktop-link` partials in the shells.
- Desktop views resolve through **`client_view($name, $role)`** (tenant-aware);
  mobile views are the literal `mobile.*` tree.

---

## 2. The two shells

| | Desktop shell | Mobile shell |
|---|---|---|
| Views | `resources/views/dashboard/<role>/…` | `resources/views/mobile/<role>/…` |
| Layouts | `customer-account`, `agent-portal`, `dashboard` | `mobile-app` |
| CSS | `ota-portal-console.css`, `ota-admin-console.css`, `ota-design-system.css` | `ota-mobile-app.css` (140 KB) |
| JS | layered (`public/js/layers`, `v2`) | `ota-mobile-app.js` |
| Nav | role sidebar partials | `mobile-app-top-bar` + `mobile-app-bottom-nav` |
| Cache-bust | bump `ota-public.css` `?v=` in `frontend.blade.php` (and verify portal/admin) | bump **both** links (CSS+JS share one integer) in `mobile-app.blade.php` |

Mobile view tree present: `mobile/{customer,agent}/…`,
`mobile/dashboard/{customer,agent}`, plus `mobile/{auth,bookings,flights,public,support,guest,components}`.

---

## 3. Viewport matrix (the redesign's acceptance target)

No horizontal page overflow and usable layouts are required across the nine
viewports below. Customer/Agent may be served by the **mobile shell** at phone
widths; **Staff/Admin are always desktop shell** (no mobile tree) and must remain
usable via responsive desktop layout.

| Viewport | Class | Customer / Agent | Staff / Admin | Focus |
|---|---|---|---|---|
| 360 × 800 | small phone | mobile shell | **desktop shell (responsive)** | tables → scroll/cards; single column |
| 390 × 844 | phone | mobile shell | desktop shell (responsive) | verify no overflow |
| 430 × 932 | large phone | mobile shell | desktop shell (responsive) | verify no overflow |
| 768 × 1024 | tablet portrait | crossover (`shouldUseMobileShell`) | desktop shell | sidebar collapses; confirm crossover |
| 1024 × 768 | tablet landscape | desktop shell | desktop shell | sidebar visible/collapsible |
| 1280 × 800 | small laptop | desktop shell | desktop shell | comfortable density |
| 1366 × 768 | laptop | desktop shell | desktop shell | comfortable density |
| 1440 × 900 | desktop | desktop shell | desktop shell | comfortable density |
| 1920 × 1080 | large desktop | desktop shell | desktop shell | max-width content; no over-stretch |

**Key consequence:** for Staff/Admin, every one of these widths is served by the
**responsive desktop shell**, so decomposing the 64.9 KB `dashboard.blade.php`
into a responsive shell is what makes Staff/Admin usable on phones/tablets — there
is no mobile fallback to lean on.

**Action for Phase 0 (documentation):** confirm the exact `shouldUseMobileShell`
breakpoint/heuristic locally (device vs width vs preference) for Customer/Agent so
the redesign knows whether 768 px is served by the mobile or desktop shell.
Viewport tests must exercise **both** Customer/Agent shells and the responsive
desktop shell for Staff/Admin (e.g. `playwright.responsive.config.ts`,
`playwright.responsive.agent.config.ts`, `playwright.desktop-range.config.ts`).

---

## 4. Responsive strategy per surface (documented plan)

- **Desktop shell:** the `ota-dashboard-shell__grid` (sidebar + content) collapses
  the sidebar at tablet and below; content stays single-column and fluid. Tables
  get the shared responsive wrapper (gap T-1, Document 03) — horizontal scroll or
  card-per-row at `< md`. No fixed pixel widths that force overflow.
- **Mobile shell:** already phone-optimised via `ota-mobile-app.css`; the phase
  ensures redesigned desktop pages have parity mobile views and that the two stay
  visually consistent (shared tokens). Preserve bottom-nav + top-bar patterns.
- **Crossover:** keep the toggle working; a user who forces desktop on a phone
  must still get a non-overflowing desktop layout (horizontal scroll acceptable
  for wide tables only).
- **Staff/Admin (desktop-only) surface:** because there is **no `mobile.*`
  tree**, the responsive desktop shell must itself be phone-safe — collapsed
  sidebar, T-1 table wrapper on every operational table, and no fixed-width
  panels. Dense data may use horizontal scroll, but critical financial/booking
  columns and actions must never be hidden (Document 03 table rules).
- **Auth entry (mobile login parity, Rev 1):** login, OTP, password reset,
  forced-password-change, and email-verification screens must render correctly at
  360–430 px (single column, ≥44 px targets, no overflow). Auth uses `layouts/auth`
  (there is also `mobile/auth/…` in the mobile tree) — parity means the entry
  experience is consistent across shells. **UI only; no auth-logic change.**

---

## 5. Parity gaps affecting responsiveness

- **G-1 (from Document 01):** customer traveler pages have **no `mobile.*`
  view** — at phone width they fall back to shared desktop `travelers/*` views
  inside the mobile shell. Verify these do not overflow at 360 px; if they do,
  the fix is a responsive wrapper, **not** a behavioural change. (Agent traveler
  pages *do* have `mobile/agent/travelers/*`.)
- **Tables without a mobile mode (T-1):** primary overflow risk at 360/390 px.
- **Wide admin tables:** admin is align-only, but if any admin table overflows at
  tablet, apply the shared wrapper without changing columns/actions.

---

## 6. Cache-bust rules (mandatory — repeated from AGENTS.md)

Because responsive fixes touch shell CSS/JS:

1. `ota-public.css` edit → bump `?v=` in `layouts/frontend.blade.php` (same commit).
2. `ota-mobile-app.css` **or** `ota-mobile-app.js` edit → bump **both** links in
   `layouts/mobile-app.blade.php` (shared integer, same commit).
3. Verify how `ota-portal-console.css` / `ota-admin-console.css` /
   `ota-design-system.css` are versioned (`ui_asset()` may handle it) and bump
   accordingly.

Missing a bump means the browser serves stale CSS and the "fix" appears not to
work — a common false-negative in QA.

---

## 7. Accessibility at small viewports

- Tap targets ≥ 44 px; nav reachable one-handed (bottom nav already does this).
- `:focus-visible` preserved on mobile too; no outline suppression.
- No content hidden behind horizontal overflow; no zoom-blocking viewport meta.

---

## 8. Phase-0 disposition

Documentation only. **No view, layout, or CSS is modified in Phase 0.** This
document defines the dual-shell contract, the viewport acceptance matrix, and the
per-surface responsive plan that Commit 5 (responsive pass) implements. The
dual-shell nature (§1) and the cache-bust rules (§6) are the two facts most
likely to be missed by a generic "make it responsive" approach.
