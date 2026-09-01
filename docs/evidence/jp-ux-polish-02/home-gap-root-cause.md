# Home gap — root cause & measurement

## HOME_HERO_GAP_ROOT_CAUSE

`PublicHero` reserved an oversized overlap spacer (`h-10 sm:h-12 lg:h-14`) beneath a search module that already used negative margin, AND rendered `AnimatedFlightPath` (green dotted SVG) between the search/benefits block and Trending Routes. The path sat in pale empty band as orphan decoration.

## Fix

- Removed `AnimatedFlightPath` from homepage hero composition.
- Reduced overlap spacer to `h-6 sm:h-8` (`data-testid=homepage-hero-overlap-spacer`).
- Slightly tightened Trending Routes section vertical padding.

## Geometry (desktop 1440)

| Metric | Before (live) | After (live) |
|---|---|---|
| HOME_HERO_BOTTOM_Y | 767 | 648 |
| HOME_NEXT_SECTION_TOP_Y | 824 | 697 |
| HOME_GAP_PX | 57 | 49 |
| Orphan flight-path SVG | present (h≈96) | absent |

HOME_HERO_TO_TRENDING_GAP=PASS (49px within ~48–72 desktop target)
HOME_ORPHAN_DECORATIVE_ARTIFACTS=0
