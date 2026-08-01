# JP-FULL-NEXT-FRONTEND-UI-KIT-INTEGRATION-MAP

Phase: **JP-FULL-NEXT-FRONTEND-01C**  
Visual status: **MANUALLY ACCEPTED WITH DEFERRED VISUAL POLISH** — kit mapping frozen; no further composition rebuild in this phase.

## Source → target

| UI kit source | Target |
|---|---|
| `app/globals.css` tokens | `frontend/styles/tokens.css` + `kit-public.css` |
| `components/PublicShell` | `components/layout/PublicShell.tsx` |
| `components/SiteHeader` / `SiteFooter` | `components/layout/SiteHeader.tsx` / `SiteFooter.tsx` + `lib/navigation.ts` |
| `components/Homepage` | `features/home` + `features/public-visual` |
| `components/FlightSearch` | `features/search` |
| `components/PageTemplates.tsx` | Per-route pages under `frontend/app/**` |
| `components/CmsRenderer.tsx` | `features/cms-theme-v2` via `public-content/utils/cms-v2-bridge.ts` |
| `data/fixtures.ts` | Tests/dev only |
| `/preview` | **Not ported** |

## Excluded kit routes

- `/preview` — dev-only catalog; retired
- `/booking/seats` — forbidden (`seat_map_available=false`)

## Added routes

- `/flights/fare-selection` — `features/flight-details/components/FareSelectionPage.tsx`
- `/verify-email` — `app/verify-email/page.tsx`
