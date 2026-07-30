# Responsive, Accessibility, and Visual Acceptance Criteria

Phase: **JP-UI-01**

## Viewports validated (capture harness + code review)

| Viewport | Width × height | Captured |
|----------|----------------|----------|
| Desktop large | 1440 × 1200 | Yes |
| Desktop | 1280 × 900 | Yes |
| Tablet landscape | 1024 × 900 | Yes |
| Mobile | 390 × 844 | Yes |
| Mobile | 375 × 812 | Yes |
| Mobile narrow | 320 × 700 | Yes |
| Zoom 125% | 1280 × 900 @ 1.25 | Yes |
| Zoom 150% | 1280 × 900 @ 1.5 | Yes |

## Responsive findings (evidence-based)

| Issue | Pages | Severity | Phase |
|-------|-------|----------|-------|
| Homepage search stacks to tall multi-row form | `/` | High | JP-UI-03 |
| Results filter sidebar hidden below `lg` — drawer used | Results | OK (by design) | JP-UI-04 |
| Progress stepper wraps on narrow widths | Checkout | Low (compact mode) | JP-UI-04 ✓ |
| Footer 5-column grid stacks | All public | Low | JP-UI-02 |
| Horizontal overflow at 320px | Most pages | **Not observed** (JP-UI-03A: 0 failures) | JP-UI-03A |
| Sticky header covers focused elements | Rare | Low | JP-UI-02 |
| Mobile keyboard on date fields | Search | Medium | JP-UI-03 |

## Accessibility findings

| Check | Status | Notes |
|-------|--------|-------|
| `:focus-visible` on interactive elements | Pass (project rule) | No broad suppression |
| Heading hierarchy | Pass on audited pages | |
| Form labels | Pass on auth/booking | |
| FAQ accordion keyboard | Tested (`public-content.spec.ts`) | Pass |
| Turnstile on lookup | Laravel-authoritative | Pass |
| Empty `imageAlt` on some offers | Fail | JP-UI-03 |
| Color contrast (light) | Generally pass on `jp-*` tokens | Pass |
| Color contrast (dark) | Verified JP-UI-03A matrix | Pass |
| Reduced motion | About + homepage tests | Pass (JP-UI-03A extended) |
| Touch targets login mobile | auth.spec ≥200px button | Pass |

---

## Visual acceptance criteria (later phases)

### JP-UI-02 — Foundation

- [ ] Shared theme tokens cover all listed semantic roles.
- [ ] Header theme toggle switches light/dark without flash on SSR hydration.
- [ ] `ThemeSwitch` in `SiteHeader`; persisted preference + system fallback.
- [ ] `ImageSlot` component handles loading, error, CMS, and fallback paths.
- [ ] No page-specific hardcoded hex outside token file.
- [ ] Focus ring uses `shadow-jp-focus` consistently.

### JP-UI-03 — Homepage / public CMS

- [ ] At 1440×900, compact search **single horizontal row** visible above fold.
- [ ] Search module overlaps hero by documented offset (target: 40–64px).
- [ ] Section order matches mockup #1.
- [ ] Header/footer geometry matches mockup column structure.
- [ ] No horizontal scroll at 320px on `/`, `/about-us`, `/support`.
- [ ] Homepage marketing content from CMS/API — no `features/home/fixtures/*` in production.
- [ ] Light and dark themes pass contrast on homepage.

### JP-UI-04 — Results & checkout

- [x] Filter sidebar : results column ratio ≈ 1:3 at 1280px.
- [x] Sort tabs row matches mockup (not dropdown-only on desktop).
- [x] Result card density supports outbound+return in one card where data exists.
- [x] Branded fare cards contained; price + CTA hierarchy stable.
- [x] Mobile uses filter drawer; sticky action for primary CTA.
- [x] **One** shared `BookingProgress` on all checkout routes.
- [x] Sidebar order summary sticky without covering content.
- [x] Seats step omitted cleanly when `seat_map_available: false`.
- [x] No fake supplier/fare data in UI.
- [x] AbhiPay redirect preserved; no embedded card form (`forbiddenTestIds` gate).
- [x] Light and dark themes on checkout surfaces (28-scenario matrix).

**JP-UI-04 evidence:** `npm run audit:visual:jp-ui-04` (28 scenarios) · `frontend/docs/visual/JP-UI-04-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md` · Visual scores ≥4 all families (pending audit run).

### JP-UI-05 — Auth & lookup & dashboards

- [x] Login/register split-screen layout with illustration slot.
- [x] Social buttons only when Laravel providers exist.
- [x] Lookup hero + card geometry matches mockup #9.
- [x] No unsupported post-lookup actions (change flight, etc.).
- [x] Customer/agent shell visual parity with public tokens.
- [x] Session expired notice via `?reason=session-expired`.
- [x] OTP shell parity (logic unchanged).
- [x] Turnstile preserved on lookup (`lookup-turnstile`).
- [x] Shared `PortalShell` primitives for customer and agent dashboards.
- [x] Dashboard theme bootstrap without flash.
- [x] 132-scenario visual matrix (`npm run audit:visual:jp-ui-05`).

**JP-UI-05 evidence:** `npm run audit:visual:jp-ui-05` (132 scenarios) · `frontend/docs/visual/JP-UI-05-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md` · Visual scores ≥4 all families (pending audit run).

### JP-UI-06 — Assets, motion, closure

- [ ] Production photo/illustration assets in approved slots.
- [ ] Animated flight path between homepage sections (reduced-motion safe).
- [ ] Playwright visual diff vs JP-UI-01 baseline captures; no regressions.
- [ ] 125%/150% zoom: no clipped primary CTAs on homepage and results.
- [ ] Screenshot parity rating ≥4 on canonical pages where operational.

---

## Measurement method

1. Regenerate captures: `npm run audit:visual:jp-ui-01`
2. Compare side-by-side with read-only mockups in Backup Safe.
3. Optional contact sheets in `.visual-audit/jp-ui-01/` (gitignored).
4. Do not rate **5** without capture + mockup comparison evidence.
