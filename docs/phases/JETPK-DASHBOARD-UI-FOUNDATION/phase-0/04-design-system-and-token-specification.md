# Phase 0 · Document 04 — Design System & Token Specification

> **REVISION 1** — Token system is role-agnostic and applies to **all** authenticated
> surfaces (Customer, Agent, Agent Staff, Internal Staff, Admin) **and** the
> Authenticated-Entry surfaces (login/OTP/reset/verify) that carry the shell. No
> per-role palette. Database engine is irrelevant to the design system: the
> repository may use **SQLite** for local/testing defaults while **production uses
> MySQL** — tokens/CSS are **database-agnostic**.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> **Non-negotiable technical constraint:** brand colour is delivered as **CSS
> custom properties** driven by the per-tenant `clients/jetpk/branding.json`, not
> as Tailwind colour classes. Any hardcoded colour (e.g. `bg-green-700`,
> `#00843D` inline) in the redesign **breaks white-labeling**. The design system
> already enforces this; the phase must stay on the token path.

---

## 1. Brand source of truth

`clients/jetpk/branding.json`:

| Field | Value |
|---|---|
| `company_name` | JetPakistan |
| `primary_color` | `#00843D` (Pakistan green) |
| `secondary_color` | `#00A651` |
| `accent_color` | `#FDB913` (gold) |
| `logo_path` | `logo/logo.svg` |
| `favicon_path` | `favicon/favicon.ico` |
| `footer_text` | "JetPakistan — your gateway to seamless travel." |

These values are injected at runtime into CSS variables (confirmed in
`layouts/dashboard.blade.php`, e.g.
`color-mix(in srgb, var(--brand-primary) 22%, transparent)`,
`background-color: var(--brand-primary)`,
`border-color: var(--brand-primary-dark)`).

**Rule:** the redesign consumes `var(--brand-*)`; it never reads
`branding.json` values into literal Tailwind/CSS. Tailwind's role here is
layout/spacing utilities and the font family — **not** brand colour.

---

## 2. Existing design tokens (`public/css/ota-design-system.css` — the canonical layer)

26 custom properties are already defined. This is the token contract to
**adopt and extend**, not replace:

**Brand**
```
--brand-primary        --brand-primary-dark   --brand-dark
--brand-surface        --brand-soft           --brand-muted     --brand-border
```
**Semantic colour**
```
--color-primary        --color-primary-dark   --color-accent
--color-bg             --color-surface        --color-text
--color-muted          --color-border
--premium
```
**Radius**
```
--radius-sm            --radius-md            --radius-lg
```
**Spacing scale**
```
--space-2  --space-4  --space-6  --space-8  --space-12  --space-16  --space-24
```

**Typography** (`tailwind.config.js`): font family `Instrument Sans` (with
`ui-sans-serif, system-ui` fallbacks). Tailwind `@tailwindcss/forms` plugin is
active. Stack per `SPEC.md`: Tailwind v3/v4 via `@tailwindcss/vite`, Vite 8.

---

## 3. Token gaps to formalise (documented for the redesign)

The existing set covers brand, semantic colour, radius, and spacing. For a
complete, minimalistic dashboard system the redesign should **add and document**
(without removing any existing token):

| Group | Present? | Recommended additions |
|---|---|---|
| Brand colour | ✅ | — |
| Semantic colour | ✅ | Status colours if not derived: `--color-success/-warning/-danger/-info` (align to badge variants) |
| Spacing | ✅ (2–24) | Confirm full scale is sufficient; document usage guidance |
| Radius | ✅ (sm/md/lg) | `--radius-full` for pills/avatars if used ad hoc |
| Typography scale | ◑ (family only) | `--font-size-*` / line-height tokens for headings/body/caption to stop oversized headings |
| Elevation/shadow | ➕ | 1–2 restrained shadow tokens (`--shadow-sm/-md`) — brief warns against heavy shadows |
| Focus ring | ➕ | `--focus-ring` token bound to `:focus-visible` (see §4) |
| Z-index scale | ➕ | `--z-dropdown/-modal/-toast` to prevent overlap bugs at small viewports |

---

## 4. Focus & accessibility policy (from CLAUDE.md — enforce, don't regress)

- Preserve accessible keyboard focus using **`:focus-visible`**.
- **No** persistent blue/cyan browser/framework focus glow.
- **No** broad global outline suppression (`*:focus { outline: none }` is
  forbidden — it must not appear in any dashboard CSS).
- Prefer a single tokenised focus ring (`--focus-ring`) applied via
  `:focus-visible` on interactive primitives.

Phase-0 action: audit for existing global outline suppression in
`ota-design-system.css` / `ota-portal-console.css` / `ota-admin-console.css`
during the redesign and replace with scoped `:focus-visible`.

---

## 5. CSS layering model

Styles are organised in layers (matching `public/css/layers/`, `public/css/v2/`,
and the `ui-layer-styles`/`ui-layer-scripts` partials). Ownership:

| Layer | File(s) | Scope |
|---|---|---|
| Tokens + shared primitives | `ota-design-system.css` | Canonical — extend here |
| Portal shell | `ota-portal-console.css` | Customer/Agent shell |
| Admin shell | `ota-admin-console.css` | Admin/Staff shell |
| Mobile shell | `ota-mobile-app.css` | Mobile (all roles) |
| Public | `ota-public.css` | Public site / `frontend` layout |
| Cascade layers | `public/css/layers/`, `public/css/v2/` | Injected via `ui-layer-styles` |

**Rule of ownership (CLAUDE.md):** "no page-specific patch when the shared
component owns the defect." Fix defects at the token/primitive layer, not per
page.

---

## 6. Cache-bust obligations (AGENTS.md — mandatory in the same change)

Any edit to shell CSS/JS **must** bump its `?v=` in the same commit or the
change won't ship:

- Edit `public/css/ota-public.css` → bump `?v=` on the link in
  `resources/views/layouts/frontend.blade.php`.
- Edit `public/css/ota-mobile-app.css` **or** `public/js/ota-mobile-app.js` →
  bump `?v=` on **both** links in `resources/views/layouts/mobile-app.blade.php`
  (CSS and JS share one integer).
- Confirm equivalent versioning for `ota-portal-console.css` /
  `ota-admin-console.css` / `ota-design-system.css` (check how each is linked;
  `ui_asset()` may handle versioning — verify locally).

---

## 7. Design principles (from the brief) mapped to tokens

The dashboard must be clean, minimalistic, lightweight, operational, scannable.
Concretely, in token terms:

- **Avoid oversized headings** → use a documented type scale, not ad-hoc large sizes.
- **Avoid heavy shadows / excessive gradients** → restrained `--shadow-*`; no gradient-heavy cards.
- **Avoid excessive borders** → prefer `--color-border` sparingly + spacing to separate.
- **Consistent spacing** → only `--space-*` tokens; no magic pixel values.
- **Consistent radius** → only `--radius-*` tokens.
- **No generic Bootstrap styling** → this is Tailwind + tokens; no Bootstrap classes.
- **No broad unscoped selectors** → scope to component/shell classes.

---

## 8. Phase-0 disposition

Documentation only. **No CSS file or token is modified in Phase 0.** This
document is the token contract and gap list for Commit 2 (establish/extend the
shared design system). The colour-via-CSS-variable rule (§1) and the cache-bust
rule (§6) are the two most likely sources of regression and are called out for
the reviewer.
