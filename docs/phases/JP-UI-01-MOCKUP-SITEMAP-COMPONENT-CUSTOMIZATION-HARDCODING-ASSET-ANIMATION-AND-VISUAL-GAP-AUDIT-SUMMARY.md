# JP-UI-01 — Mockup, Sitemap, Component, Customization, Hardcoding, Asset, Animation, and Visual Gap Audit

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-01-MOCKUP-SITEMAP-COMPONENT-CUSTOMIZATION-HARDCODING-ASSET-ANIMATION-AND-VISUAL-GAP-AUDIT |
| Branch | `phase/jetpk-ui-01-mockup-sitemap-audit` |
| Baseline | `5fad262` (JP-FE-13A final SHA documentation) |
| Feature commit | `fc47444` |
| Docs commit | `58d5979` |
| Merge commit | `4614824` |
| Final SHA documentation | `d096215` |
| Final status | COMPLETE |

## Objective

Establish the complete visual source of truth for the JetPakistan public frontend and booking journey before broad visual redesign or JP-OPS operational closure. Audit-only — no production redesign, no Laravel behavior changes.

## Included scope

- Verified all 13 canonical mockups in `C:\Users\khadi\Backup Safe` (SHA-256, dimensions, page mapping)
- Route and sitemap inventory (Next.js + Laravel)
- Sitemap-to-mockup matrix with match ratings (0–5)
- Mockup vs actual mismatch register (119 items)
- Hardcoding and content-ownership audit
- Asset, image, placeholder, and animation audit
- Design system, theme, typography, and component gap audit
- Responsive and accessibility findings + acceptance criteria
- Operational feasibility matrix for mockup controls
- JP-UI-02 through JP-UI-06 and JP-OPS roadmap
- Deterministic visual capture harness (`npm run audit:visual:jp-ui-01`)
- 92 screenshots captured locally (gitignored)

## Excluded scope

- Broad CSS or layout redesign
- Mockup PNGs copied to runtime assets
- Laravel operational changes
- Supplier/booking/payment internals
- Seat map implementation (classified future capability)
- Visual parity claims (no page rated 5)
- Production deployment

## Investigation findings

1. **Mockups** — All 13 files exist at 1122×1402 px; desktop-only; Backup Safe untouched.
2. **Homepage** — `SearchModule` is multi-row stacked below a two-column hero; mockup requires compact single-row search overlapping photographic hero (**High** severity).
3. **Results** — Functional filters/sort exist but layout diverges from mockup (no hero band, sort dropdown vs tabs, card density).
4. **Theme** — Light mode only; no `ThemeProvider` or dark tokens; mockup family assumes day/night.
5. **Hardcoding** — Homepage `features/home/fixtures/*` used in production for destinations/offers (**F** classification).
6. **Seat selection** — `seat_map_available: false`; types scaffolded only; no route.
7. **Progress stepper** — Shared `BookingProgress` exists but style/labels differ from mockup checkout family.
8. **Component naming** — `SiteHeader`/`SiteFooter`/`SearchModule` map to mockup PublicHeader/Footer/CompactFlightSearch.

## Root causes

| Gap | Root cause |
|-----|------------|
| Homepage search layout | `HomepageHero` grid places search below hero; `SearchModule` not built for compact overlap |
| Results visual | JP-FE phases prioritized function over mockup density |
| Dark mode | Not scoped in JP-FE; no token strategy |
| Fixture homepage content | CMS wiring deferred; temporary fixtures remain in production path |
| Seat page absent | Laravel contract explicitly disables seat map for standard booking |

## Files changed

### Audit tooling
- `frontend/tests/visual-audit/jp-ui-01-scenarios.ts`
- `frontend/tests/visual-audit/jp-ui-01-fixtures.ts`
- `frontend/tests/visual-audit/jp-ui-01.visual-audit.spec.ts`
- `frontend/scripts/capture-jp-ui-01.mjs`
- `frontend/package.json` (script `audit:visual:jp-ui-01`)
- `frontend/.gitignore` (`.visual-audit/`)

### Documentation
- `docs/phases/JP-UI-01-MOCKUP-SITEMAP-COMPONENT-CUSTOMIZATION-HARDCODING-ASSET-ANIMATION-AND-VISUAL-GAP-AUDIT-SUMMARY.md`
- `frontend/docs/visual/JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md`
- `frontend/docs/visual/SITEMAP-TO-MOCKUP-MATRIX.md`
- `frontend/docs/visual/MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md`
- `frontend/docs/visual/FRONTEND-CONTENT-OWNERSHIP-AND-HARDCODING-AUDIT.md`
- `frontend/docs/visual/ASSET-IMAGE-PLACEHOLDER-AND-ANIMATION-AUDIT.md`
- `frontend/docs/visual/DESIGN-SYSTEM-THEME-TYPOGRAPHY-AND-COMPONENT-GAP-AUDIT.md`
- `frontend/docs/visual/RESPONSIVE-ACCESSIBILITY-AND-VISUAL-ACCEPTANCE-CRITERIA.md`
- `frontend/docs/visual/OPERATIONAL-FEASIBILITY-AND-MOCKUP-CONTROL-MATRIX.md`
- `frontend/docs/visual/JP-UI-IMPLEMENTATION-ROADMAP.md`
- `frontend/docs/visual/VISUAL-AUDIT-CAPTURE-GUIDE.md`

## Routes changed

None (audit-only).

## Database changes

None.

## Backend changes

None.

## Frontend changes

Audit tooling and documentation only — no production UI/CSS changes.

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run audit:visual:jp-ui-01` | **93 passed** (92 captures + 1 manifest assertion) |

### Capture summary

| Metric | Value |
|--------|------:|
| Scenarios captured | 12 |
| Unsupported (documented) | 1 (seat-selection) |
| Total PNG artifacts | 92 |
| Desktop viewports | 1440, 1280, 1024 |
| Mobile viewports | 390, 375, 320 |
| Zoom levels | 125%, 150% on 1280 |

## Assertion counts

- Playwright visual audit: **93** assertions (all pass)
- No new unit test files beyond visual audit spec

## Screenshots

- Full-resolution captures: `frontend/.visual-audit/jp-ui-01/` (**gitignored**)
- Manifest: `frontend/.visual-audit/jp-ui-01/capture-manifest.json`
- Mockup reference: read-only Backup Safe paths (not in repo)

## Responsive verification

Captured at 320, 375, 390, 1024, 1280, 1440 plus 125%/150% zoom. See mismatch register for known homepage/checkout issues.

## Accessibility verification

Code review + existing `public-content.spec.ts` / `auth.spec.ts` patterns. Empty offer alt text flagged. Focus-visible preserved.

## Known limitations

- Laravel not running during capture (expected); API routes mocked for booking/results
- Support/lookup may log ECONNREFUSED for uncaught Laravel proxy paths; captures still succeed
- No mockup mobile counterparts — responsive intent inferred
- No page rated visual parity 5

## Risks

- Homepage fixture content may be mistaken for live fares until JP-UI-03 CMS migration
- Implementing mockup Hotels/Offers nav without JP-OPS would create dead links

## Rollback instructions

```powershell
git revert <merge-commit-sha>
# Or reset branch to baseline 5fad262 if pre-merge
```

Remove `frontend/.visual-audit/` locally at any time; regenerable via `npm run audit:visual:jp-ui-01`.

## Per-page match ratings (desktop visual)

| Page | Rating |
|------|-------:|
| Homepage | 2 |
| About | 3 |
| Support | 3 |
| Login / Sign up | 3 |
| Results | 2 |
| Fare selection (inline) | 2 |
| Passengers | 3 |
| Seat selection | 0 (no route) |
| Review | 3 |
| Payment | 3 |
| Success | 3 |
| Manage booking | 3 |

## Mismatch severity counts

| Severity | Count |
|----------|------:|
| Blocker | 0 |
| High | 18 |
| Medium | 42 |
| Low | 35 |
| Informational | 24 |

## Next phase

**JP-UI-02-SHARED-DESIGN-SYSTEM-DAY-NIGHT-THEME-TYPOGRAPHY-SHELL-HEADER-FOOTER-IMAGE-SLOTS-AND-FOUNDATION**

## Production untouched

Confirmed — no deployment, no production data changes, Backup Safe read-only verified.
