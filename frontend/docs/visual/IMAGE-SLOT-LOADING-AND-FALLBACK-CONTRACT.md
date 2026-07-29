# Image Slot, Loading, and Fallback Contract

Phase: **JP-UI-02**

## Component

`components/ui/ImageSlot.tsx`

## Required behavior

| Capability | Implementation |
|------------|----------------|
| Aspect ratio / dimensions | `width` + `height` props → CSS `aspect-ratio` |
| CLS prevention | Fixed aspect container before image load |
| Lazy loading | Default `loading="lazy"`; `priority` when justified |
| Loading state | `Skeleton` overlay until `onLoad` |
| Error / missing | Themed fallback panel with icon |
| Decorative mode | `decorative` → empty `alt` |
| Semantic mode | Required meaningful `alt` |
| Object fit | `cover` (default) or `contain` |

## Fallback policy

- No mockup screenshots in runtime assets
- No external hotlink domains beyond existing Next image config
- Generic vector placeholder icon on neutral surface

## Dark theme

Fallback uses `bg-jp-surface-muted` and `text-jp-muted`; operational airline/CMS images are not CSS-filtered.

## Migration (JP-UI-03+)

Replace direct `next/image` usage in home sections and airline identity with `ImageSlot` incrementally.
