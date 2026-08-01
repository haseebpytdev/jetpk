# JP-PUBLIC-NEXT-THEME-02 — DESIGN SYSTEM, CMS FOUNDATION, VISUAL LAB

## Phase name

**JP-PUBLIC-NEXT-THEME-02 — Isolated Public Design System, CMS Renderer, and Visual Lab**

## Branch name

`phase/jetpk-public-next-theme-02-design-system-cms-foundation`

## Objective

Create an isolated JetPakistan public-theme V2 component system, CMS rendering foundation, and development-only visual lab without wiring into existing production pages.

## Included scope

- `frontend/features/public-theme-v2/` — scoped tokens, primitives, header/footer prototypes, states
- `frontend/features/cms-theme-v2/` — block types, template registry, sanitizer, URL validation, renderer
- Visual lab at `/__dev/jetpk-theme-lab` (rewrite to `/dev/jetpk-theme-lab`)
- Phase 02 Playwright tests and four visual captures
- Mock Shell adaptation records and route inventory note

## Excluded scope

- Homepage, About, Support, Results, booking, auth, portals
- Laravel, Blade, dashboard, routes/web.php
- Global `tokens.css` / Tailwind `jp.*` changes
- Existing `public-content` renderers
- Destination, offer, article routes
- Commit, push, merge

## Investigation findings

- Existing production theme uses global `data-theme` and `components/ui/*`; V2 uses isolated `.jp-theme-v2` + `data-jp-theme`
- Mock Shell patterns adapted via scoped CSS, not wholesale `globals.css` import
- `npm run build` fails on pre-existing ESLint in `AccountMenu.tsx` (out of phase scope); compile succeeds with `next build --no-lint`

## Root causes addressed

- No isolated design-system namespace existed for the rebuild
- CMS contract (Phase 01) had no runtime implementation in an isolated module
- No gated visual lab for approving components before page work

## Files created

```
frontend/features/public-theme-v2/
  styles/tokens.css, theme.css
  components/*.tsx (22 components)
  lab/fixtures.ts, is-theme-lab-allowed.ts, ThemeLabContent.tsx
  index.ts
frontend/features/cms-theme-v2/
  lib/block-types.ts, page-template-registry.ts, normalize-cms-page.ts,
      sanitize-cms-html.ts, validate-cms-url.ts
  components/*.tsx (13 components)
  styles/cms-content.css
  index.ts
frontend/app/dev/jetpk-theme-lab/page.tsx
frontend/playwright.theme-02.config.ts
frontend/scripts/capture-jp-public-next-theme-02.mjs
frontend/tests/jp-public-next-theme-02.spec.ts
frontend/tests/visual-audit/jp-public-next-theme-02.visual.spec.ts
docs/phases/JP-PUBLIC-NEXT-THEME-02-DESIGN-SYSTEM-CMS-FOUNDATION-SUMMARY.md
```

## Files modified

```
frontend/next.config.ts          — rewrite /__dev/jetpk-theme-lab
frontend/package.json            — test:theme-02, audit script
docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md
docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md
```

## Routes changed

| Route | Change |
|---|---|
| `/__dev/jetpk-theme-lab` | New dev-only rewrite (noindex, gated) |
| `/dev/jetpk-theme-lab` | New internal page |

No production routes changed.

## Database / backend / Laravel / Blade

None.

## Component inventory (V2)

| Component | Location |
|---|---|
| PublicThemeV2Root | public-theme-v2 |
| PublicContainer, PublicSection, PublicSectionHeading | public-theme-v2 |
| PublicHeaderPrototype, PublicFooterPrototype | public-theme-v2 |
| PublicButton, PublicIconButton | public-theme-v2 |
| PublicTextField, PublicSelect, PublicCheckbox, PublicRadio | public-theme-v2 |
| PublicTabs, PublicBadge, PublicCard, PublicImageSlot | public-theme-v2 |
| PublicCallout, PublicAlert | public-theme-v2 |
| PublicStepper, PublicBookingSummary | public-theme-v2 |
| PublicEmptyState, PublicLoadingState, PublicErrorState | public-theme-v2 |

## Token inventory (`--jp-v2-*`)

Brand greens, surfaces, text hierarchy, borders, focus ring, success/warning/error/info, typography scale, spacing, content widths, radii, shadows, control heights, motion durations, reduced-motion overrides.

## CMS block inventory

`hero`, `richText`, `image`, `cardGrid`, `stats`, `timeline`, `faq`, `callout`, `gallery`, `section`

## CMS template inventory

`default-content`, `hero-content`, `landing`, `faq`, `contact`, `policy`, `destination`, `offer`, `article-index`, `article-detail` — unknown → `default-content`

## Sanitization and URL safety

- `sanitizeCmsHtml`: allowlist tags/attrs; strips script/style/form/iframe/on*/class/style; demotes h1→h2
- `validateCmsUrl`: http/https/mailto/tel/site-relative only
- `validateCmsImageSrc`: site-relative or allowlisted hosts (`CMS_ALLOWED_IMAGE_HOSTS`)
- Invalid URLs dropped; external links get `rel="noopener noreferrer"`

## Tests executed

| Command | Result |
|---|---|
| `npm run typecheck` | PASS (after `.next` clean) |
| `npm run lint` | FAIL — pre-existing `AccountMenu.tsx` (out of scope) |
| `npm run build` | FAIL lint step — compile PASS via `next build --no-lint` |
| `npx playwright test tests/jp-public-next-theme-02.spec.ts -c playwright.theme-02.config.ts` | **10/10 PASS** |
| `npx playwright test tests/public-content.spec.ts -c playwright.config.ts` | **14/14 PASS** |

## Screenshots

```
frontend/.visual-audit/jp-public-next-theme-02/lab-1440-light.png
frontend/.visual-audit/jp-public-next-theme-02/lab-1440-dark.png
frontend/.visual-audit/jp-public-next-theme-02/lab-390-light.png
frontend/.visual-audit/jp-public-next-theme-02/lab-390-dark.png
```

## Responsive / accessibility verification

- Lab covers desktop (1440) and mobile (390) in light/dark
- Focus-visible on interactive controls; FAQ keyboard operable; aria on fields/states

## Known limitations

- V2 not wired to any production page
- Custom sanitizer (no DOMPurify); sufficient for contract tests
- `npm run build` blocked by pre-existing lint outside phase scope

## Risks

- Phase C must migrate carefully without breaking existing CMS pages
- Production lab access requires explicit `JP_THEME_LAB_ENABLED=true`

## Rollback

Remove `frontend/features/public-theme-v2`, `frontend/features/cms-theme-v2`, `frontend/app/dev/`, rewrite in `next.config.ts`, and Phase 02 test/capture files.

## Commit SHA

Not committed — awaiting manual visual approval.

## Final status

**STOP GATE — awaiting manual visual review.** No commit or push.
