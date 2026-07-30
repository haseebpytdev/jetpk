# JP-UI-03 Mockup Comparison and Acceptance Report

Baseline mockups: Backup Safe Jul 27, 2026 (#1 homepage, #2 about, #3 support).

> **Evidence note:** JP-UI-03 captured 6 light-desktop scenarios only. **JP-UI-03A** completed the full **119-scenario** matrix (light/dark/system/mobile/zoom/interaction). Scores below are recalculated from complete evidence.

| Page | Viewport / theme | Structure | Header | Hero | Search | Cards | Typography | Spacing | Footer | Theme | Score |
|------|------------------|-----------|--------|------|--------|-------|------------|---------|--------|-------|-------|
| Homepage | 1440 desktop light | 5 | 5 | 4 | 4 | 4 | 4 | 4 | 5 | 4 | **4** |
| Homepage | 1440 desktop dark | 5 | 5 | 4 | 4 | 4 | 4 | 4 | 5 | 4 | **4** |
| Homepage | 390 mobile light | 4 | 5 | 4 | 4 | 4 | 4 | 4 | 5 | 4 | **4** |
| Homepage | 390 mobile dark | 4 | 5 | 4 | 4 | 4 | 4 | 5 | 5 | 4 | **4** |
| Homepage | 1280 @ 150% zoom | 4 | 5 | 4 | 4 | 4 | 4 | 4 | 5 | 4 | **4** |
| About | 1440 desktop light | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| About | 1440 desktop dark | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| About | 390 mobile light | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| About | 390 mobile dark | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| Support | 1440 desktop light | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| Support | 1440 desktop dark | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| Support | 390 mobile light | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| Support | 390 mobile dark | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| CMS/legal template | 1440 light | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| CMS/legal template | 1440 dark | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |
| CMS/legal template | 390 mobile | 4 | 5 | 4 | n/a | 4 | 4 | 4 | 5 | 4 | **4** |

Rating scale: 0–5 per JP-UI-01. Minimum met: **4** on all required surfaces.

## Remaining measurable gaps (accepted)

- Hotels/Offers nav items omitted (unsupported routes)
- Travel inspiration hidden without CMS articles
- Group Ticketing tab retained (operational, not in mockup tabs)
- Literal CMS copy differs from illustration text (not a visual mismatch)
- Hero uses CMS/fallback imagery rather than mockup photograph (layout parity achieved)

## Evidence

| Phase | Command | Scenarios |
|-------|---------|----------:|
| JP-UI-03 (partial) | `npm run audit:visual:jp-ui-03` | 6 |
| JP-UI-03A (complete) | `npm run audit:visual:jp-ui-03a` | **119** |

Artifacts: `frontend/.visual-audit/jp-ui-03a/` (gitignored)  
Summary: `frontend/docs/visual/jp-ui-03a-capture-result.json`
