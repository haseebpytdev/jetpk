# CMS Frontend Section Map — DASH-08-09

Status legend: **confirmed** | *inferred* | **proposed** | **unresolved**

## Inspected homepage (`resources/views/frontend/home.blade.php`)

| Order | Section | Status | CMS key | Notes |
|-------|---------|--------|---------|-------|
| 1 | Hero banner + copy | confirmed | homepage.hero | `ota-hero--banner`, optional background image |
| 2 | Mobile trust bar | confirmed | homepage.trustBenefits | 24/7 support, secure booking |
| 3 | Flight search (embedded) | confirmed | homepage.flightSearchContext | `ota-hero-flight-search` — **not CMS logic** |
| 4 | Trust metrics (mobile) | confirmed | homepage.trustBenefits | `ota-home-trust-metrics` |
| 5 | Trust metrics (desktop) | confirmed | homepage.trustBenefits | Same partial |
| 6 | Umrah groups preview | confirmed | *proposed* | Gated by `public_umrah_groups` |
| 7 | Featured fares / offers | confirmed | homepage.featuredOffers | `ota-home-fares-preview`, carousel when >3 cards |
| 8 | Popular routes | confirmed | homepage.popularRoutes | `ota-popular-routes` |
| 9 | Why choose us | confirmed | homepage.trustBenefits | `ota-landing-why` |

## Other public pages (confirmed)

- About, contact, support, FAQ, policy pages via `cms-pages/show` and dedicated blades
- Support page hero: `ota-support-hero` → homepage.supportCallout / content.contactBlock

## Day/night behavior

- **confirmed**: Hero supports background image; CSS theme tokens in `ota-public.css` (not modified in this phase)
- **unresolved**: Full dual-asset day/night swap coverage across all sections

## Responsive behavior

- **confirmed**: `ota-mobile-home-only` / `ota-home-desktop-content` split
- **confirmed**: Mobile trust bar and metrics variants

## CMS mapping proposal

Each registry entry in `dashboard/features/cms/registry/section-registry.ts` maps to a `frontendComponentKey` for future Next.js resolution.

## Fields not CMS-controlled

Flight search mechanics, supplier APIs, fare calculation, booking/checkout, auth, PNR/ticket operations, Sabre gates, environment config.

## Legacy excluded

- `partials/tournest-home-main.blade.php` — legacy template, not target architecture
- Parwaaz / master-client fallbacks — excluded from CMS fixtures

## Unresolved / evolving

- Newsletter callout placement on current homepage (**proposed** for CMS)
- Destination landing visual polish (**unresolved**)
- Dynamic fare snapshots vs static CMS offers coexistence (**inferred** from fares preview partial)

## Prompt 03 — implemented dashboard CMS UI

| Route | Module | Status |
|-------|--------|--------|
| `/testdash/cms` | Overview | **implemented** — metrics, distributions, attention queue, revisions |
| `/testdash/cms/pages` | Pages | **implemented** — table/cards, drawer, composition, preview |
| `/testdash/cms/sections` | Sections | **implemented** — registry-driven list, local preview form, section previews |
| `/testdash/cms/banners` | Banners | **implemented** — family filters, family-specific drawer/preview |
| `/testdash/cms/notices` | Notices | **implemented** — severity filters, placement preview |
| `/testdash/cms/assets` | Assets | **implemented** — metadata-only, variant display, placeholder preview |

Homepage composition in CMS drawer mirrors registry order for `JP-CMS-PG-001` (hero → flight search context → featured offers → popular routes → trust → support → FAQ preview).
