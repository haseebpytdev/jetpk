# OWNER UAT W2-21 / W2-22 — Shared shell + typography V2

## Branch reconciliation

| Source | Ref |
|---|---|
| Isolated worktree | `C:\Users\khadi\ota-jetpk-w2-shell` @ `153cfaa` (`phase/jetpk-owner-uat-w2-21-22-shell-typography`) |
| Implementation commits (worktree) | `e147367` → `6bfb497` → `a1e041a` (+ docs `153cfaa`) |
| Authoritative business branch | `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure` |
| Cherry-picks on business | `7e597fc` / `33db4c1` / `5885dec` (equivalent content) |

**No blind merge** of remote typography branch. **No force push.** Newer Wave-2 business work preserved.

Content parity (worktree vs business): SiteHeader, CurrencySelector, DesktopNavigation, Dropdown (plus post-reconcile remasure), layouts, typography tokens, Clash fonts, Blade frontend layout — MATCH (CRLF-only noise ignored).

## Objective

Shared-shell micro-polish + platform typography migration for Owner UAT Wave 2.

## Included

### W2-21 Shared header / footer micro-polish
- Footer currency menu → drop-up, compact single-line rows, code right-aligned, smaller type, selected ring state, keyboard open via ArrowUp, viewport-safe panel
- Desktop header → logo | geometrically centered Flights/Groups/Support | controls
- Nav weight → `font-semibold`
- Login CTA → ~106px min-width, restrained green gradient, hover/active/focus, no Signup/Book Now

### W2-22 Platform typography V2
- **Plus Jakarta Sans** = platform UI (public, auth, Agent, Customer, Admin, Staff)
- **Clash Display** = selective public marketing H1/H2 only (`font-display`)
- **IBM Plex Mono** = machine identifiers
- Inter removed from Next font authority + JetPakistan Blade theme tokens (`frontend.blade.php` / `tokens.css`)

## Production verification (authoritative branch)

| Gate | Result |
|---|---|
| OWNER_W2_CURRENCY_DROPUP | PASS |
| OWNER_W2_CURRENCY_MENU_COMPACT | PASS |
| OWNER_W2_HEADER_NAV_CENTERED | PASS |
| OWNER_W2_HEADER_NAV_CLARITY | PASS |
| OWNER_W2_LOGIN_CTA_POLISH | PASS |
| OWNER_W2_PLUS_JAKARTA_PLATFORM | PASS |
| OWNER_W2_CLASH_DISPLAY_MARKETING | PASS |
| OWNER_W2_INTER_RESIDUE | 0 |
| OWNER_W2_FONT_FALLBACK_DEFECTS | 0 |
| OWNER_W2_TYPOGRAPHY_RESPONSIVE | PASS |

Evidence:
- Worktree local accept: `tmp/jp-w2-shell-typography-accept.worktree-local.json` (commit `a1e041a`, pass=true)
- Production accept: `tmp/jp-w2-shell-typography-accept.json`
- Source parity: frontend shell/typography files MATCH production SHA256; OLS MATCH

## Acceptance script

```bash
# production
set JP_FRONTEND_ORIGIN=https://jetpakistan.pk
node tmp/jp-w2-shell-typography-accept.cjs
```

## Status

**RECONCILED_ON_BUSINESS_BRANCH** — gates PASS on production after recover/reconcile/verify/deploy.
