# CMS Component Contracts — DASH-08-09

## Page model

`CmsPage` — id, brand (fixed JetPakistan), pageType, slug, locale, status, SEO fields (plain text), ordered sectionIds, publication window, revision, validation.

## Section registry

`CMS_SECTION_REGISTRY` — 18 section definitions with `frontendComponentKey` matching `sectionType`.

## Component contract

`CmsComponentContract` — allowed fields, prohibited controls, theme tokens.

## Field types

text, richText (structured), link, asset, number, boolean, enum — no raw HTML execution.

## Theme metadata

Modes: automatic, day, night, dualAsset, neutral
Treatments: default, brand, muted, elevated, imageOverlay, transparent
Widths: narrow, standard, wide, fullBleed
Spacing: compact, standard, spacious

## Asset references

Metadata-only `CmsAsset` with desktop/mobile/day/night variants, focal point, alt text, approval status.

## Publication metadata

Statuses: draft, inReview, approved, scheduled, published, expired, archived

## Validation contract

`CmsValidationIssue` — severity, code, message, fieldPath, blocking flag.

## Preview contract

Modes: desktop_day, desktop_night, tablet, mobile_day, mobile_night — labelled **Dashboard preview only**.

## frontendComponentKey rules

- Framework-agnostic dot notation (`homepage.hero`)
- Must match registry entry
- Resolved by trusted component registry in future Next.js app

## Example payload (hero)

```json
{
  "sectionType": "homepage.hero",
  "fields": {
    "eyebrow": "Fly smart",
    "heading": "Book flights with JetPakistan",
    "supportingText": "Search routes across Pakistan and beyond.",
    "altText": "Aircraft above clouds at sunset",
    "primaryCta": { "type": "internal_route", "label": "Search flights", "value": "/flights/search" }
  },
  "themeMode": "dualAsset",
  "assetIds": ["JP-CMS-AS-001"]
}
```

## Future API serialization

JSON API returns typed page + sections[]; no Blade names or PHP class references.

## Next.js trusted component resolution

```
page.sections → map(section.frontendComponentKey) → approved React component → design tokens
```

## Prompt 03 — current UI routes

| Route | Drawer | Local preview | Preview modes |
|-------|--------|---------------|---------------|
| `/testdash/cms` | — | — | Listed on overview |
| `/testdash/cms/pages` | Page identity, SEO, composition, revisions | Composition reorder (unsaved) | Desktop/tablet/mobile day/night |
| `/testdash/cms/sections` | Fields, assets, validation, Next.js mapping | `CmsLocalPreviewForm` (Apply to preview) | Per-section preview shell |
| `/testdash/cms/banners` | Family constraints, assets, validation | — | Family-specific banner preview |
| `/testdash/cms/notices` | Publication window, validation | — | Placement preview strip/card |
| `/testdash/cms/assets` | Metadata, usage refs | — | Placeholder variant preview |

URL state: `status`, `pageType`, `sectionType`, `themeMode`, `locale`, `assetStatus`, `bannerFamily`, `noticeSeverity`, `validationState`, `search`, `page`, `pageSize`, `sort`, `direction`, `selected`, `previewMode`, `previewLoading`, `previewEmpty`, `previewError`.
