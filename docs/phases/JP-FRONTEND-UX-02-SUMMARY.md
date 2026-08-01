# JP-FRONTEND-UX-02 Phase Summary

## Phase

JP-FRONTEND-UX-02 — Motion, AJAX, Loading, Popups, Journey Progress
**Sub-phase UX-02A:** Authority proof, interaction evidence, commit readiness
**Sub-phase UX-02B:** Scroll-reveal hardening, evidence correction, push readiness

## Branch

`phase/jetpk-frontend-motion-ajax-ux`

## Commits

| SHA | Message |
|---|---|
| `89bc831` | Starting baseline |
| `a0854fd` | feat: add frontend motion and asynchronous UX |

## UX-02B Scroll-Reveal Issue (manual evidence review)

### Issue found

`01-homepage-scroll-reveal.png` showed a large blank region between hero and footer while `02-homepage-reduced-motion.png` showed all marketing sections.

### Root cause

1. CSS hid all `.jp-scroll-reveal` targets with `opacity: 0` whenever `data-revealed="false"`, including immediately after hydration.
2. Below-fold homepage sections (Destinations, Featured Offers, Why JetPakistan, Support) are wrapped in `ScrollReveal`.
3. The evidence capture took a full-page screenshot immediately after `page.goto("/")` without scrolling targets into view.
4. IntersectionObserver never fired for below-fold elements, so they remained invisible in the screenshot.
5. Reduced-motion screenshot worked because `.jp-scroll-reveal--reduced` bypasses the hidden state.

This was primarily an **evidence-script timing/scroll omission**, compounded by CSS that hid content before the `--armed` enhancement gate existed.

### Correction

Production:
- Content is visible by default (SSR, no-JS, pre-hydration).
- Hiding applies only after JS arms `.jp-scroll-reveal--armed`.
- `observeRevealElement()` reveals on intersection, immediately when IO is unavailable, or via a 600ms in-viewport failsafe.
- Unmount clears failsafe timers.

Evidence/tests:
- Scroll each reveal target and assert `data-revealed="true"` before full-page capture.
- Assert marketing section headings are visible.
- Separate dark Login (fresh context) from dark Agent dashboard captures.
- Portal dashboard shots classified as backend-error evidence; loading skeletons captured separately.

## Interaction evidence (local, not committed)

Capture: `npx playwright test -c playwright.jp-frontend-ux-02-evidence.config.ts`

| File | Classification |
|---|---|
| `01a-homepage-initial-viewport.png` | Initial viewport before scroll reveal |
| `01-homepage-scroll-reveal.png` | Full homepage after all targets revealed |
| `02-homepage-reduced-motion.png` | Reduced motion — all sections visible |
| `03-route-navigation-about.png` | Route navigation |
| `04-results-loading-or-content.png` | Results |
| `05-fare-selection.png` | Fare Selection |
| `06-login.png` | Login |
| `07-payment-status-polling.png` | Payment pending/poll |
| `08-customer-dashboard-backend-error.png` | **Backend-unavailable error state** (not successful dashboard content) |
| `09-agent-dashboard-backend-error.png` | **Backend-unavailable error state** (not successful dashboard content) |
| `10-dark-theme-login.png` | **Actual dark Login page** (fresh context, no agent session) |
| `11-dark-theme-agent-dashboard.png` | Dark Agent dashboard (error/unavailable state when backend down) |
| `12-customer-bookings-loading-skeleton.png` | Customer bookings loading state (route skeleton or client loading text) |
| `13-agent-bookings-loading-skeleton.png` | Agent bookings loading state (route skeleton or client loading text) |

## Manual visual review (UX-02B)

Manual review of the first evidence set found the scroll-reveal blank-region defect and mislabeled dark Login capture. After UX-02B corrections, automated evidence capture re-run confirms:
- Homepage marketing sections visible after guided scroll-reveal.
- Reduced-motion homepage shows all sections without scroll.
- Dark Login screenshot contains login fields on dark surfaces.
- Portal dashboard captures honestly labeled as backend-error states.

Automated screenshot existence alone does **not** constitute manual visual approval; the corrected set addresses the defects found during review.

## Tests (UX-02B final)

| Command | Result |
|---|---|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run test:jp-frontend-ux-02` | **17/17 PASS** |
| Integration regression (`--grep-invert "capture "`) | **119/119 PASS** |

## Blocking defects

**0** — ready for correction-commit authorization review.

## Final status

**STOPPED FOR CORRECTION-COMMIT AUTHORIZATION** — do not push/merge/deploy until approved.
