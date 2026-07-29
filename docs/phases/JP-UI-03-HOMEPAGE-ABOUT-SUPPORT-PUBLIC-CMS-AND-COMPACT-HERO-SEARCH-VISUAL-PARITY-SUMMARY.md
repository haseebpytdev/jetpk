# JP-UI-03 — Homepage, About, Support, Public CMS, and Compact Hero Search Visual Parity

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-03-HOMEPAGE-ABOUT-SUPPORT-PUBLIC-CMS-AND-COMPACT-HERO-SEARCH-VISUAL-PARITY |
| Branch | `phase/jetpk-ui-03-public-pages-visual-parity` |
| Baseline | `2d95890` (JP-UI-02 main HEAD) |
| Objective | Rebuild public marketing pages with canonical mockup layout and CMS-driven content |

## Pre-implementation audit

- Homepage used oversized stacked `SearchModule` below two-column SVG hero
- `features/home/fixtures/*` imported directly in production path
- `HomepageContentService` unused stub returning fixtures
- About/Support already Laravel-wired; visual templates needed parity
- No `GET /api/public/content/homepage` endpoint

## Laravel additive changes

- `HomepagePublicContentPresenter` + `GET /api/public/content/homepage`
- Published homepage sections only; `source: empty` when no CMS home page
- Tests: `PublicContentApiTest` (+2 homepage cases)

## Frontend architecture

- `features/public-visual/` — hero, sections, FAQ, homepage service
- `PublicHero` + `SearchModule layout="compact"` overlapping hero
- `HomepageContentService` fetches Laravel API; fixtures only under `allowContentFixtures()`
- Shared `PublicFaq`, `BenefitStrip`, `PublicSectionHeader`

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run audit:visual:jp-ui-03` | 6/6 PASS |
| `npx playwright test tests/homepage.spec.ts tests/public-content.spec.ts` | 24/24 PASS |
| `php artisan test tests/Feature/Jetpk/PublicContentApiTest.php` | 13/13 PASS |

## Visual scores

Minimum **4** achieved on homepage, about, support, CMS templates (see `frontend/docs/visual/JP-UI-03-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`).

## Known limitations

- Travel inspiration hidden without CMS article collection
- Multi-city/group compact row wraps below xl breakpoint (by design)
- Dark-theme visual capture subset in harness (light primary matrix captured)
- Legacy `features/home/components/*` retained for reference; not used in production path

## Git SHAs

| Item | SHA |
|------|-----|
| Feature commit | `2badb32` |
| Docs commit | `c864b0f` |
| Merge commit | `09ded8c` |
| Final docs SHA | `3e93ad5` |

## Final status

COMPLETE — production untouched; Backup Safe read-only.
