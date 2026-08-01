# JP-PUBLIC-NEXT-THEME-03B — Asset Blocker Register

Phase: **JP-PUBLIC-NEXT-THEME-03B**  
Route: `/__dev/jetpk-homepage-v2`  
Authority: Backup Safe Homepage mockup (normalized crop in `.visual-audit/jp-public-next-theme-03b/`)

## Policy

- Preserve exact image-slot geometry; quiet neutral gradients only.
- `data-asset-state="missing"` for tooling — no large visible labels.
- Do not embed or crop the Backup Safe mockup into runtime UI.

## Missing standalone assets

| ID | Slot | Geometry | Status |
|----|------|----------|--------|
| A01 | Hero aircraft composite | Hero right column, full height | Missing |
| A02 | Brand logo SVG (header/footer) | 34×34 mark + wordmark | Partial (text mark) |
| A03 | Destination photo ×5 | 90px × card width | Missing |
| A04 | Offer visual ×3 | Offer card right half | Missing |
| A05 | Inspiration photo ×4 | 90px × card width | Missing |
| A06 | Airline logos ×5 | Destination card footer | Missing |
| A07 | Currency flag icon | Header control | Missing |
| A08 | Social icon set | Footer row | Partial (letter glyphs) |

## Normalized reference

See `normalized-reference-meta.json` for browser-chrome crop coordinates and content viewport.
