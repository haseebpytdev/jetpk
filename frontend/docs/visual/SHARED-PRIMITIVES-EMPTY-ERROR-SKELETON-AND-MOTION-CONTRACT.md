# Shared Primitives, Empty/Error/Skeleton, and Motion Contract

Phase: **JP-UI-02**

## Primitive inventory

| Primitive | Path |
|-----------|------|
| Button (+ Primary/Secondary aliases) | `components/ui/Button.tsx` |
| LinkButton | `components/ui/LinkButton.tsx` |
| IconButton | `components/ui/IconButton.tsx` (existing) |
| Form controls | `components/ui/FormControls.tsx` |
| Surface / Card / Divider | `components/ui/Surface.tsx` |
| Badge | `components/ui/Badge.tsx` (existing) |
| StatusBadge | `components/ui/StatusBadge.tsx` |
| Skeleton | `components/ui/Skeleton.tsx` |
| EmptyState | `components/ui/EmptyState.tsx` |
| ErrorState / RetryState | `components/ui/ErrorState.tsx` |
| ImageSlot | `components/ui/ImageSlot.tsx` |
| SkipLink | `components/ui/SkipLink.tsx` |
| VisuallyHidden | `components/ui/VisuallyHidden.tsx` |
| PageContainer | `components/layout/PageContainer.tsx` |

## State foundations

| State | Behavior |
|-------|----------|
| Skeleton | Shimmer with reduced-motion static fallback |
| Empty | Honest copy; optional operational CTA |
| Error | Safe public message; no stack traces |
| Retry | `RetryState` with busy/disabled protection |

Dashboard shells now delegate to shared `EmptyState` / `ErrorState` wrappers.

## Motion

- Token durations in `tokens.css`
- `.jp-skeleton-shimmer`, `.jp-image-fade-in` in `globals.css`
- No `transition: all`; prefer opacity/transform
- `prefers-reduced-motion` disables shimmer and decorative animation

## Deferred migration

Route-specific state cards (results, booking, group) remain until JP-UI-03–06; new work should compose these primitives.
