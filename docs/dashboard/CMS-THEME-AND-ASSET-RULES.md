# CMS Theme and Asset Rules — DASH-08-09

## Theme modes

automatic | day | night | dualAsset | neutral

## Theme treatments

default | brand | muted | elevated | imageOverlay | transparent

## Spacing tokens

compact | standard | spacious

## Width tokens

narrow | standard | wide | fullBleed

## Hero asset rules

- Desktop 16:9, mobile 4:5 (confirmed from homepage hero)
- Optional day/night asset pairs
- Required alt text; focal point recommended
- Overlay strength via token, not raw CSS

## Support-callout rules

- Desktop ~21:9 intent, mobile 16:9
- WhatsApp CTA via approved `whatsapp_action` link type
- Synthetic preview contact labels only

## Banner-family rules

See `CMS_BANNER_FAMILY_RULES` in section registry — hero, support, offer, destination, airline, campaign, promotion, notice.

## Desktop/mobile assets

Separate asset metadata per breakpoint; CMS stores asset IDs only.

## Day/night assets

Optional dayVariant/nightVariant on `CmsAsset`; validation warns when night asset missing for dualAsset sections.

## Focal point

Normalized 0–1 coordinates; used for crop preview.

## Alt text

Required for visual assets; weak alt text warns.

## Aspect ratio

Enforced per banner family and section definition.

## Safe area

Text-safe region metadata for overlay sections.

## Approval states

approved | pending | rejected | unapproved — unapproved assets block publication.

## Asset validation

`validateAsset()` in `dashboard/features/cms/validation/cms-validation.ts`

## Prohibited arbitrary styling

No raw color picker, hex values, font families, CSS classes, inline styles, animations, z-index, or JavaScript in CMS fields.

## Prompt 03 — implemented validation behavior

Dashboard UI surfaces validation via `CmsValidationSummary` in drawers and the overview attention queue (`buildCmsAttentionQueue`). Implemented checks include: missing alt text, unapproved assets, invalid banner placement, publication window conflicts, unsupported section placement, duplicate singleton sections, carousel requirement (>3 offers), unsafe URL protocols, external URL review markers, and aspect-ratio mismatch warnings for hero banners.
