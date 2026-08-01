# JP-PUBLIC-NEXT-THEME-03 — Asset Blocker Register

Phase: **JP-PUBLIC-NEXT-THEME-03**
Route: `/__dev/jetpk-homepage-v2`
Authority: Backup Safe Homepage mockup `ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` (1122×1402)

## Policy

- Preserve exact image-slot geometry in the composition.
- Use neutral branded development surfaces with `data-asset-state="missing"`.
- Do not crop or embed the Backup Safe mockup PNG into runtime UI.
- Do not use random stock imagery or claim photographic parity.

## Missing standalone assets

| ID | Slot | Region | Geometry | Status | Notes |
|----|------|--------|----------|--------|-------|
| A01 | Hero aircraft composite | Hero right column | Full hero art area (~56% width × 420px) | **Missing** | Mockup shows branded aircraft + city/mountain composite |
| A02 | Brand logo (header) | Header left | 34×34 mark + wordmark | **Partial** | Text mark only; no approved SVG/PNG logo asset |
| A03 | Brand logo (footer) | Footer brand column | White logo variant | **Partial** | Text mark only |
| A04 | Destination photo 1 | Destinations card 1 | 90px height, full card width | **Missing** | Gradient placeholder `image-slot--1` |
| A05 | Destination photo 2 | Destinations card 2 | 90px height | **Missing** | Gradient placeholder `image-slot--2` |
| A06 | Destination photo 3 | Destinations card 3 | 90px height | **Missing** | Gradient placeholder `image-slot--3` |
| A07 | Destination photo 4 | Destinations card 4 | 90px height | **Missing** | Gradient placeholder `image-slot--4` |
| A08 | Destination photo 5 | Destinations card 5 | 90px height | **Missing** | Gradient placeholder `image-slot--5` |
| A09 | Offer visual 1 | Featured offer card 1 right half | ~50% × 128px | **Missing** | Gradient wash placeholder |
| A10 | Offer visual 2 | Featured offer card 2 right half | ~50% × 128px | **Missing** | Gradient wash placeholder |
| A11 | Offer visual 3 | Featured offer card 3 right half | ~50% × 128px | **Missing** | Gradient wash placeholder |
| A12 | Inspiration photo 1 | Travel inspiration card 1 | 90px height | **Missing** | Gradient placeholder |
| A13 | Inspiration photo 2 | Travel inspiration card 2 | 90px height | **Missing** | Gradient placeholder |
| A14 | Inspiration photo 3 | Travel inspiration card 3 | 90px height | **Missing** | Gradient placeholder |
| A15 | Inspiration photo 4 | Travel inspiration card 4 | 90px height | **Missing** | Gradient placeholder |
| A16 | Airline mark 1–5 | Destination card footers | ~24px logo area | **Missing** | Neutral text label used |
| A17 | Currency flag icon | Header currency control | Inline icon | **Missing** | Neutral placeholder glyph |
| A18 | Social icons | Footer social row | 24×24 circles | **Partial** | Letter placeholders only |

## Approved when available

When standalone assets are delivered, wire through `PublicImageSlot` or approved CMS asset paths — not mockup crops.

## Geometry preserved

All slots retain mockup dimensions, border-radius, and placement. Missing pixels are masked only in comparison tooling, not hidden in the rendered composition.
