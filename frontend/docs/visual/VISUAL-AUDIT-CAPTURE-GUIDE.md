# Visual Audit Capture Guide

Phase: **JP-UI-01**  
Command: `npm run audit:visual:jp-ui-01` (from `frontend/`)

## Purpose

Deterministic, reproducible screenshots of the **current** Next.js implementation for comparison against read-only mockups in `C:\Users\khadi\Backup Safe`. This phase captures evidence; it does **not** claim visual parity.

## Prerequisites

- Node.js and npm
- `npm install` in `frontend/`
- Playwright Chromium: `npx playwright install chromium`

## Capture command

```bash
cd frontend
npm run audit:visual:jp-ui-01
```

This script:

1. Runs `npm run build` (production Next.js)
2. Starts `scripts/playwright-server.mjs` on port **3002** (via Playwright `webServer`)
3. Executes `tests/visual-audit/jp-ui-01.visual-audit.spec.ts`
4. Writes artifacts to `frontend/.visual-audit/jp-ui-01/` (**gitignored**)

### Environment (test-only)

Set automatically by Playwright config:

- `NODE_ENV=production`
- `NEXT_PUBLIC_SESSION_PREVIEW=logged-out`
- `OTA_ALLOW_SESSION_FIXTURE=true`
- `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true`

## Output artifacts

| File | Description |
|------|-------------|
| `capture-manifest.json` | Route, viewport, timestamp, fixture scenario per capture |
| `{scenario}__{viewport}.png` | Full-page or viewport screenshots |
| `{scenario}__desktop-1280-zoom-125.png` | 125% zoom captures |
| `{scenario}__desktop-1280-zoom-150.png` | 150% zoom captures |

## Scenarios captured

| Scenario ID | Route | Fixture setup |
|-------------|-------|---------------|
| `homepage` | `/` | public |
| `about-us` | `/about-us` | public |
| `support` | `/support` | public |
| `login` | `/login` | auth |
| `register` | `/register` | auth |
| `flight-results` | `/flights/results?…` | results API mock |
| `fare-selection` | `/flights/results?…` | branded fares mock |
| `passengers` | `/booking/passengers?…` | passengers JSON mock |
| `review` | `/booking/review` | review JSON mock |
| `payment-manual` | `/booking/payment/manual` | checkout-state mock |
| `confirmation` | `/booking/confirmation` | confirmation JSON mock |
| `lookup-booking` | `/lookup-booking` | turnstile disabled |

### Not captured (documented only)

- **Seat selection** — no route; `seat_map_available: false`

## Viewports

| Name | Size |
|------|------|
| `desktop-1440` | 1440 × 1200 |
| `desktop-1280` | 1280 × 900 |
| `desktop-1024` | 1024 × 900 |
| `mobile-390` | 390 × 844 |
| `mobile-375` | 375 × 812 |
| `mobile-320` | 320 × 700 |

Plus 125% and 150% zoom on `desktop-1280` for non-auth scenarios.

## Mockup reference (hashes)

See `JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md` for all 13 SHA-256 hashes. Mockups remain in Backup Safe only — never copied to `public/`.

## Regeneration

```bash
cd frontend
rm -rf .visual-audit/jp-ui-01   # optional clean
npm run audit:visual:jp-ui-01
```

Exit code non-zero on capture failure.

## Manual alternative

```bash
cd frontend
npm run build
npx playwright test tests/visual-audit/jp-ui-01.visual-audit.spec.ts -c playwright.config.ts
```

## Comparing to mockups

1. Open mockup from Backup Safe (read-only).
2. Open matching capture from `.visual-audit/jp-ui-01/`.
3. Record gaps in `MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md` (do not edit mockups).

## Git policy

- **Commit:** capture script, spec, scenarios, fixtures, this guide, metrics/manifest schema.
- **Do not commit:** full-resolution PNG captures (gitignored).
- Optional: small compressed contact sheets if &lt;500KB total.

## Related files

- `tests/visual-audit/jp-ui-01-scenarios.ts` — route definitions
- `tests/visual-audit/jp-ui-01-fixtures.ts` — deterministic API mocks
- `tests/visual-audit/jp-ui-01.visual-audit.spec.ts` — Playwright spec
- `scripts/capture-jp-ui-01.mjs` — npm script entry

## Production safety

- No production deployment
- No mockup assets in runtime
- Fixture data uses `audit@example.com`, `Audit Airline`, etc. — never copied from mockup literals

---

## JP-UI-02 foundation capture

Phase: **JP-UI-02**  
Command: `npm run audit:visual:jp-ui-02` (from `frontend/`)

Captures representative routes in **light** and **dark** themes, desktop and mobile viewports, plus 150% zoom on homepage.

### Output

`frontend/.visual-audit/jp-ui-02/` (gitignored) + `capture-manifest.json`

### Related files

- `tests/visual-audit/jp-ui-02-scenarios.ts`
- `tests/visual-audit/jp-ui-02-foundation.visual.spec.ts`
- `scripts/capture-jp-ui-02.mjs`

---

## JP-UI-03 public pages capture (partial)

Phase: **JP-UI-03**  
Command: `npm run audit:visual:jp-ui-03` (from `frontend/`)

**Limitation:** Six light-desktop scenarios only. Superseded by JP-UI-03A for parity evidence.

---

## JP-UI-03A complete visual matrix (authoritative)

Phase: **JP-UI-03A**  
Command: `npm run audit:visual:jp-ui-03a` (from `frontend/`)

### What it does

1. Runs `npm run build`
2. Starts production Next.js on port **3002** via Playwright `webServer`
3. Executes **119** serial scenarios in `tests/visual-audit/jp-ui-03a-visual-matrix.spec.ts`
4. Writes `frontend/.visual-audit/jp-ui-03a/capture-manifest.json` (**gitignored**)
5. Verifies manifest count, duplicates, overflow, hydration, and page errors via `scripts/verify-jp-ui-03a-manifest.mjs`
6. Exits non-zero on any failure

### Environment (test-only)

Same as JP-UI-01/02 plus `JP_UI_03A_EXPECTED_COUNT=119`.

### Committed artifacts

- Scenario registry, fixtures, helpers, spec, capture/verify scripts
- `frontend/docs/visual/jp-ui-03a-capture-result.json` (lightweight summary)
- QA docs under `frontend/docs/visual/JP-UI-03A-*.md`

### Related files

- `tests/visual-audit/jp-ui-03a-scenarios.ts`
- `tests/visual-audit/jp-ui-03a-fixtures.ts`
- `tests/visual-audit/jp-ui-03a-helpers.ts`
- `tests/visual-audit/jp-ui-03a-visual-matrix.spec.ts`
- `tests/jp-ui-03a-theme-matrix.spec.ts`
- `scripts/capture-jp-ui-03a.mjs`
- `scripts/verify-jp-ui-03a-manifest.mjs`

