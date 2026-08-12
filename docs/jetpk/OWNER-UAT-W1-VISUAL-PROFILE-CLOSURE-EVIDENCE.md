# OWNER UAT W1 — Final visual / profile closure evidence

## Branch / HEAD

`phase/jetpk-owner-uat-wave-1-portals-public-shell`

## Included in this pass

- Currency removed from all public/portal headers; compact footer currency + localStorage persistence
- Theme control icon-only (sun/moon/system)
- Login CTA restrained green gradient
- Full-card rails for routes + destinations (1–4 cards, arrows only when needed)
- Branded image fallback; homepage spacing/hero/support polish
- Footer ~35% denser; social icons (existing Facebook/Instagram hrefs); meta row with currency
- Portal layouts use canonical public branding
- Profile chip wider name; Agent hero badges removed
- Agency display formatting (no `[object Object]`); Next agency edit + logo upload via existing Laravel API
- LEGACY_AGENT_PROFILE_HANDOFF removed
- Customer profile alignment; empty states / sidebar / button tertiary / search label contrast

## Agency logo schema

`OWNER_W1_AGENCY_IMAGE_SCHEMA` — **not required**. Existing `agents.meta.logo_path` + multipart `logo` on `PATCH /agent/agency`.

## Regression

`node frontend/tests/regression/jp-w1-agency-display.test.cjs` → PASS

## Social icons note

Footer keeps **exact existing** `socialLinks` destinations (Facebook + Instagram). Icons are real SVG marks for those labels; no invented LinkedIn URL.
