# MOBILE-ACCESSIBILITY-REPORT (final)

**Baseline** `6fbfae4` · static analysis. **Contrast, screen-reader output and focus order cannot be
measured here** — §4.

## 1. Focus — the notable correction

The dashboard programme's Phase 12 established that the cyan-glow defect
(`ota-design-system.css:244`, `outline:none` + `rgba(14,165,233,.2)`) lives on `.ota-auth-input` /
`.ota-login-input` — the **desktop** auth views, which JetPakistan overrides. **However the mobile
shell DOES load `ota-design-system.css`**, so rather than rely on cascade order, MA-6 gives
`.ota-mobile-auth__input` an **explicit tokenised brand ring**:

```css
body.jp-app .ota-mobile-auth__input:focus-visible {
  border-color: var(--app-brand); box-shadow: var(--app-ring);
  outline: 2px solid transparent; outline-offset: 2px;
}
```
No blue/cyan can reach the JetPK app under any cascade order. The shell layer applies the same
tokenised `:focus-visible` to all interactive elements, with pointer focus suppressed
(`:focus:not(:focus-visible)`) — the pattern already proven correct in
`themes/frontend/jetpakistan/css/forms.css`.

## 2. Status

| Check | Result |
|---|---|
| Tokenised `:focus-visible`, no bare `outline:none` | **Pass** — shell + auth layers |
| Tap targets ≥44px | **Pass** — tabs, buttons, inputs, checkbox rows, CTAs |
| **iOS auto-zoom on focus** | **Fixed** — auth inputs set `font-size:16px` (below 16px, Safari zooms) |
| Images without `alt` | **0** repo-wide |
| Viewport meta | Present (shell preserved) |
| Safe-area (notch / home indicator) | Handled on header, main, tab bar, sheet |
| Financial data hidden on mobile | **No** — amounts wrap; tables scroll |
| Reduced motion | Respected |
| Labels | Mobile auth uses `.ota-mobile-auth__label` + fields; **not re-audited for `for`/`id`** — see §4 |

## 3. Content that stays reachable

The dashboard programme's rule — *never hide critical operational or financial information* — is
carried through: agent amounts use `white-space: normal` + `overflow-wrap: anywhere` +
`text-overflow: clip`, and any row containing an amount is forced `overflow: visible`. Refs and names
truncate; **money never does.**

## 4. Not claimed / follow-ups

- **Contrast ratios, screen-reader output, focus order, real tap sizes** — require a browser.
- **Label association on mobile auth/forms was not audited** (the dashboard programme found ~300
  unlabelled controls in legacy *desktop* admin forms). A `for`/`id` sweep of `mobile/**` is a
  sensible follow-up — it is a **shared** tree, so it needs the white-label decision.
- `:has()` used once (agent amount rows) — fine on current mobile browsers.
