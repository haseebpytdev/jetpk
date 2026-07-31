# JP-UI-06 Homepage Hero and Floating Search Blueprint Contract

Phase: **JP-UI-06**

## Blueprint targets (1122×1330 desktop)

| Element | Target box (x,y,w×h) | Owner |
|---------|------------------------|-------|
| Hero band | 0,68 — 1122×420 | `PublicHero` |
| Search panel | 80,380 — 960×140 | `SearchModule` (`variant="blueprint"`) |
| Integrated tabs | 96,372 — 360×36 | `SearchTabs` |
| Benefit strip | 80,540 — 960×48 | `BenefitStrip` |
| Section curve | full width below hero | `SectionCurve` |
| Scroll affordance | centered below overlap | `ScrollToDiscover` |

## Implementation

- `PublicHero`: min-height 28–32rem, hero image + gradient, search dock with negative margin overlap (`-mb-14` … `-mb-[4.5rem]`).
- `SearchModule` blueprint variant: integrated tab row on panel top edge, card radius 1.25rem, elevated shadow, compact layout.
- Preserved behaviour: One Way / Return / Multi-City / Group Ticketing, airport API, swap, traveller/cabin rules, Direct-only, Nearby (origin-only), Flexible Dates, validation, keyboard tabs.

## Exceptions

- **D:** Hero photograph is CMS/fallback `ImageSlot`, not mockup PNG pixels.
- **E:** Hotels/Offers nav items only when present in `lib/navigation.ts` authoritative config.
