# Homepage Schema Parity Matrix — JP-ADMIN-CMS-03

Authoritative chain for JetPakistan public homepage:

**CMS editable field → stored field (`client_page_settings.content_json` / `client_page_assets`) → public API (`/api/public/content/homepage`) → public component**

Code authorities:
- Schema: `app/Support/Client/Homepage/HomepageCanonicalSchema.php`
- Display helpers: `app/Support/Client/JetpkHomepageSectionData.php`
- Public presenter: `app/Services/PublicContent/HomepagePublicContentPresenter.php`
- Dashboard editor: `dashboard/features/cms/components/homepage-settings-panel.tsx`
- Public consumer: `frontend/features/home/components/HomepageContent.tsx` + `frontend/features/public-visual/*`

| Section | CMS editable | Stored field | Public API field | Public component | Notes |
|---------|--------------|--------------|------------------|------------------|-------|
| Hero | Eyebrow | `hero.eyebrow` | `hero.eyebrow` | `PublicHero` | CMS text authority |
| Hero | Headline | `hero.headline` | `hero.headline` | `PublicHero` | |
| Hero | Highlighted text | `hero.headline_highlight` | `hero.headline_highlight` | `PublicHero` | |
| Hero | Description | `hero.subtitle` | `hero.subtitle` | `PublicHero` | |
| Hero | Alt text | `hero.image_alt` | `hero.image.alt` / mobile | `PublicHero` | |
| Hero | Focal point | `hero.focal_point` | `hero.focal_point` | `PublicHero` | |
| Hero | Overlay | `hero.overlay_strength` | `hero.overlay_strength` | `PublicHero` | |
| Hero | Desktop media | asset `hero_background` | `hero.image.url` | `PublicHero` | Media library / upload only |
| Hero | Mobile media | asset `hero_background_mobile` | `hero.image_mobile.url` | `PublicHero` | |
| Hero | CTA label/link | `hero.cta_text` / `hero.cta_link` | (hero CTA when rendered) | `PublicHero` | Optional |
| Trust chips | Chip labels | `trust_chips[].label` | `trust_chips[].label` | Homepage trust row | |
| Trending Routes | Enabled | `routes.enabled` | `routes.enabled` | `RoutesSection` | |
| Trending Routes | Eyebrow/Title/Subtitle | `routes.eyebrow/title/subtitle` | same | `RoutesSection` | |
| Trending Routes | CTA label/target | `routes.cta_text` / `routes.cta_url` | same | `RoutesSection` | |
| Trending Routes | Item origin/dest/title | `routes.items[].from/to/title` | `routes.items[]` | route cards | Stable `id` required |
| Trending Routes | Item enabled | `routes.items[].enabled` | filtered in display helper | | |
| Trending Routes | Item image | asset `route_<id>` / `image_asset_key` | `routes.items[].image` | `resolveRouteMedia` | CMS wins over city fallback |
| Trending Routes | Item image alt | `routes.items[].image_alt` | `routes.items[].image_alt` | | |
| Trending Routes | Search/CTA | `routes.items[].cta_url` | `search_url` | | System date offset when empty |
| Trending Routes | Fare label | — | `price_label` / `fare_source` | | **System-generated** from fare cache / neutral label |
| Destinations | Section headers + CTA | `destinations.*` | `destinations.*` | `DestinationsSection` | |
| Destinations | IATA/city/country/subtitle | `destinations.items[]` | items | dest cards | |
| Destinations | Image | asset `destination_<id>` | `image` + `media_source` | `resolveDestinationMedia` | Fallback SVG only when no CMS asset |
| Destinations | Image alt | `image_alt` / `alt` | `image_alt` | | |
| Destinations | Link | `link` / `cta_url` | `href` | | |
| Featured Deals | Section headers + CTA | `featured_deals.*` | same | `FeaturedOffersSection` | |
| Featured Deals | Airline/from/to/price/title/badge/description | `featured_deals.items[]` | items | offer cards | |
| Featured Deals | Image | asset `featured_deal_<id>` | `image`, `image_alt`, `media_source` | `resolveOfferMedia` | CMS URL prioritized; fallback only if absent |
| Why Book | Enabled + headers | `why_book.*` | same | Why Book section | |
| Why Book | Cards | `why_book.cards[]` | `why_book.cards[]` | | |
| Feature Board | Items | `feature_board.items[]` | `feature_board` | Feature board | |
| Support CTA | Enabled + copy + CTAs | `support_cta.*` | `support_cta.*` | Support CTA | Background asset when configured |

## Authority rules

1. When published CMS content exists (`source: cms`), fixtures must not override published text.
2. Fallback media is allowed only when no CMS asset URL exists; UI shows **MEDIA SOURCE: CMS | Fallback**.
3. No second editable public homepage source outside Client Page Settings + assets.
4. Public fetch uses `cache: 'no-store'` + tag `homepage-cms` so publish propagates in ≤10s.

## Gate

`HOMEPAGE_SCHEMA_PARITY=PASS` when this matrix matches shipped code paths above.
