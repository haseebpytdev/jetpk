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
| Progress stepper wraps on narrow widths | Checkout | Medium | JP-UI-04 |
| Footer 5-column grid stacks | All public | Low | JP-UI-02 |
| Horizontal overflow at 320px | Most pages | **Not observed in smoke**; verify in JP-UI-06 diff | JP-UI-06 |
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
| Color contrast (light) | Generally pass on `jp-*` tokens | Dark untested |
| Reduced motion | About page test exists | Extend globally JP-UI-06 |
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

- [ ] Filter sidebar : results column ratio ≈ 1:3 at 1280px.
- [ ] Sort tabs row matches mockup (not dropdown-only on desktop).
- [ ] Result card density supports outbound+return in one card where data exists.
- [ ] Branded fare cards contained; price + CTA hierarchy stable.
- [ ] Mobile uses filter drawer; sticky action for primary CTA.
- [ ] **One** shared `BookingProgress` on all checkout routes.
- [ ] Sidebar order summary sticky without covering content.
- [ ] Seats step omitted cleanly when `seat_map_available: false`.
- [ ] No fake supplier/fare data in UI.

### JP-UI-05 — Auth & lookup & dashboards

- [ ] Login/register split-screen layout with illustration slot.
- [ ] Social buttons only when Laravel providers exist.
- [ ] Lookup hero + card geometry matches mockup #9.
- [ ] No unsupported post-lookup actions (change flight, etc.).
- [ ] Customer/agent shell visual parity with public tokens.

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
