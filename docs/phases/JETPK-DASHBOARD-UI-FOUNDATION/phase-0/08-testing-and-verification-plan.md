# Phase 0 · Document 08 — Testing & Verification Plan

> **REVISION 1** — Coverage expanded to **all** authenticated roles (Customer,
> Agent, Agent Staff, Internal Staff, Admin) and Authenticated-Entry surfaces; the
> viewport list is aligned to the nine required breakpoints; and the **production
> HTML ValidationException fix (being repaired separately in Cursor) is a
> prerequisite gate** before any dashboard integration.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> Favour the repo's **native gates** over ad-hoc checks. **Local/deterministic
> only** — no production, no live suppliers, no real payments/emails. Environment:
> the repo may use **SQLite** for local/testing defaults while **production uses
> MySQL**; tests must be **database-agnostic** and must not alter schema/queries.

---

## 0. Prerequisite gate (blocks integration)

**Production HTML ValidationException issue** is being repaired **separately in
Cursor**. Dashboard integration for **any** phase must **not** proceed until that
fix has landed and login/validation renders correctly. This is a hard
pre-integration gate, independent of the per-phase gates below.

---

## 1. Native route-parity & no-500 gate (primary)

```bash
php artisan ota:route-page-health-audit --all      # must report fail=0
```

Per AGENTS.md / `docs/audits/OTA_PRE_DEPLOY_NO_500_RULE.md`, a pass is invalid when
any of: `fail > 0`, `server_errors > 0`, mojibake grep hits, or a new
`production.ERROR`. This must hold for **every** role surface touched
(Customer, Agent, Staff, Admin) — not a subset.

Route-name parity (no renamed/removed routes across all 447):
```bash
php artisan route:list --json > routes.after.json
# diff route names vs Document 01 appendices → expect zero removals/renames
```

---

## 2. PHPUnit (feature + unit)

```bash
php artisan test
```
Existing feature/unit tests must stay green across all roles (proves no
controller/route/policy/permission regression). Do not weaken or delete tests.
Update markup-coupled assertions minimally and note them.

---

## 3. Playwright E2E (local only) — per role

Scripts: `npm run e2e:desktop`, `npm run e2e:mobile`, `npm run e2e:ui-qa`.

| Config | Role coverage |
|---|---|
| `playwright.config.ts` | Base |
| `playwright.responsive.config.ts` | Responsive (desktop shell — Staff/Admin + Customer/Agent desktop) |
| `playwright.responsive.agent.config.ts` | Agent responsive |
| `playwright.desktop-range.config.ts` | Desktop widths 1024–1920 |
| `playwright.agent-critical.config.ts` | Agent (and Agent Staff permission paths) |
| `playwright.ota-critical.config.ts` | Core OTA flows |
| `playwright.accounting-ledger.config.ts` | Staff/Admin ledger surfaces |
| `playwright.admin-v1-visual.config.ts` | Admin visual normalization |
| `playwright.public-critical.config.ts` / `…-dropdown.config.ts` | Public + auth entry regressions |
| `playwright.jetpk-parity.config.ts` / `jetpk-9h*.config.ts` | JetPakistan parity/suites |

**Agent Staff:** exercise permission-scoped runs — a limited `agent_staff` user
sees gated nav and hits the **permission-denied** state on denied deep-links
(Document 05 §6).

**EXCLUDE — live configs (never run):** `playwright.jetpk-live.config.ts`,
`playwright.jetpk-9h-b-live.config.ts`.

---

## 4. Responsive verification — nine viewports, correct shell per role

Exercise every redesigned/normalized page at: **360×800, 390×844, 430×932,
768×1024, 1024×768, 1280×800, 1366×768, 1440×900, 1920×1080.**

- **Customer / Agent:** mobile shell at phone widths + desktop shell at ≥1024.
- **Internal Staff / Admin:** **desktop shell at every width** (no mobile tree) —
  verify the responsive desktop shell does not overflow at 360–430 px.
- **Auth entry:** login/OTP/reset/verify usable at 360–430 px (mobile login
  parity).

Acceptance per page: no horizontal page overflow, no clipped controls, tables
usable (scroll or card mode) with **no hidden financial/booking columns**, nav
reachable, tap targets ≥ 44 px. Capture screenshots as evidence.

---

## 5. Accessibility verification (all roles + auth)

Keyboard reachable/operable; visible focus via `:focus-visible`; **no** global
outline suppression; **no** persistent blue/cyan glow; every input has an
associated visible label (no placeholder-as-label); brand green (`#00843D`) meets
WCAG AA on surfaces; shell landmarks/`aria-label` preserved. Includes auth-entry
forms and the permission-denied state.

---

## 6. Branding verification (all roles + auth)

Run the Document 07 gate against changed files + dashboard/mobile/**auth**/email
trees: no new `Parwaaz | YoursDomain | YD Travel | haseeb` hits; brand never in
URLs/`alt`/`title`/`meta`; logos from `clients/jetpk/branding.json`; anti-leakage
CSS preserved. Re-confirm the 4 known `haseeb` auth-layout hits remain
non-visible.

---

## 7. Build, style, compile

```bash
npm install && npm run build          # Vite build succeeds
vendor/bin/pint --dirty               # style-only, changed files
php artisan optimize:clear            # clear caches before verification
```
Blade must compile with no errors (surfaced as 500s by the page-health audit).

---

## 8. Cache-bust verification

For every shell CSS/JS edit, confirm the matching `?v=` bump shipped
(Documents 04 §6 / 06 §6). A "fix that didn't work" is frequently a missed
cache-bust. Record: changed asset, old `?v=`, new `?v=`, every Blade reference to
update.

---

## 9. Evidence to capture (feeds the phase summary)

Per CLAUDE.md: tests executed, **assertion counts**, screenshots (per role +
per viewport class), responsive verification, accessibility verification. Do not
report `FINAL_FAIL=0` unless every acceptance criterion passes, the route-page
-health audit is `fail=0`, and the §0 prerequisite gate is satisfied.

---

## 10. Phase-0 disposition

Documentation only. **No test is written, run, or modified in Phase 0** (the
sandbox cannot run Artisan/PHPUnit/Playwright — no `vendor/`, Packagist
unreachable). This plan is the verification contract executed by Cursor locally
during implementation; its gates (prerequisite ValidationException fix →
route-page-health `fail=0` → green PHPUnit → both-shell/desktop-only responsive →
a11y → branding, across all roles) are the acceptance bar.
