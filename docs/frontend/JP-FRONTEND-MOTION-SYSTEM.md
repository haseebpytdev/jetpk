# JP-FRONTEND Motion System

## Tokens

| Token | Duration | Use |
|---|---|---|
| `--jp-motion-instant` | 100ms | Micro feedback |
| `--jp-motion-fast` | 160ms | Tooltips, toasts |
| `--jp-motion-standard` | 230ms | Scroll reveal, overlays |
| `--jp-motion-emphasized` | 340ms | Drawers |
| `--jp-motion-route` | 240ms | Route navigation bar |

Easing: `--jp-ease-standard`, `--jp-ease-emphasized`, `--jp-ease-decelerate`.

## Location

- Tokens: `frontend/styles/tokens.css`
- Utilities: `frontend/features/motion/`
- Global CSS: `frontend/app/globals.css`

## Scroll reveal

- `ScrollReveal` uses one shared `IntersectionObserver`
- Reveal once; content visible without JS
- Marketing sections only (homepage routes, offers, Why JetPakistan, support)
- Stagger up to 4 cards (60ms steps)

## Reduced motion

`prefers-reduced-motion: reduce` removes translation, stagger, decorative animation. Loading text and status remain.

## Route navigation

`RouteNavProgress` is separate from booking progress. Thin top bar for internal Next navigations only.
