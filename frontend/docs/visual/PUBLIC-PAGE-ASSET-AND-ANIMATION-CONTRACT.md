# Public Page Asset and Animation Contract (JP-UI-03)

## Image policy

- `ImageSlot` for hero, route cards, support CTA imagery
- CMS URLs from Laravel homepage presenter
- Branded SVG fallback for missing hero: `/images/home/hero-fallback.svg`
- No mockup screenshots in runtime

## Motion

| Animation | Trigger | Reduced motion |
|-----------|---------|----------------|
| Hero flight path | viewport / mount | static path |
| Search tab transition | tab change | instant |
| FAQ expand | button click | no height animation blocking content |
| Card hover | hover/focus | none required |

Forbidden: fake loading timers, infinite high-frequency loops, `transition-all`.
