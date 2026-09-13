# JetPakistan — Final Owner Media List

**Phase:** JETPAKISTAN-MEDIA-MANIFEST-SEMANTIC-CLOSURE-02  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Generated:** 2026-09-13  
**Supersedes:** `docs/audits/JP-MEDIA-OWNER-ACTION-LIST.{md,json}` (41 false-positive P1 entries)

## Executive summary

The prior owner manifest (`OWNER_ASSETS_REQUIRED_DEDUPED=41`) was **entirely false positives**. The audit script treated `href`, `route()`, `client_route()`, `mailto:`, anchor IDs, and `resolveDestination()` URL resolvers as image requirements whenever the source file path contained keywords like `hero`, `destination`, or `group`.

After semantic inspection of all 41 records **and** production verification on https://jetpakistan.pk/, the owner genuinely needs **2 media actions** (1 broken production image + 1 optional mobile hero enhancement).

| Gate | Result |
|------|--------|
| `FALSE_POSITIVE_IN_FINAL_OWNER_LIST` | **0** |
| `NON_IMAGE_URL_IN_FINAL_OWNER_LIST` | **0** |
| `DUPLICATE_OWNER_ASSET` | **0** |
| `UNKNOWN_MEDIA_CLASSIFICATION` | **0** |
| **STATUS** | **PASS** |

---

## Counts

| Metric | Value |
|--------|------:|
| `CURRENT_RAW_COUNT` | 84 |
| `CURRENT_DEDUPED_COUNT` | 41 |
| `SEMANTIC_RECORDS_REVIEWED` | 41 |
| `FALSE_POSITIVES_REMOVED` | 41 |
| `GENUINE_MEDIA_SLOTS` (image context in prior list) | 8 |
| `APPROVED_EXISTING` | 12 |
| `CMS_EXISTING` | 3 |
| `CMS_MISSING` | 0 |
| `PLACEHOLDER_REPLACE` | 0 |
| `GENERIC_FALLBACK_REPLACE` | 0 |
| `BROKEN_REPLACE` | 1 |
| `NEW_ASSET_REQUIRED` | 0 |
| `OPTIONAL_ENHANCEMENT` | 1 |
| `BRAND_AUTHORITY` | 2 |
| `DYNAMIC_RUNTIME` | 5 |
| `OWNER_ACTION_P0` | 1 |
| `OWNER_ACTION_P1` | 0 |
| `OWNER_ACTION_P2` | 1 |
| `OWNER_ACTION_TOTAL` | **2** |

---

## P0 — Broken / missing production image

### JP-FINAL-001 — Homepage route card (KHI–DXB seed)

| Field | Value |
|-------|-------|
| **Page / section** | Homepage → “Where Pakistan is flying.” → first route card |
| **Source** | CMS asset key `route_seed_khi_dxb` |
| **Current asset** | `route_seed_khi_dxb-20260910194137.png` (HTTP 200, **decodes 1×1 px**) |
| **Visible at** | https://jetpakistan.pk/ |
| **Why replace** | Corrupt/placeholder upload — card shows blank/stretched image while alt reads “Lahore to Dubai Flights” |
| **Recommended subject** | Route photography (aircraft + destination skyline) for KHI–DXB or LHE–DXB |
| **Aspect ratio** | 4:3 |
| **Min resolution** | 1536×1024 |
| **Desktop crop** | ~228×171 thumbnail, `object-cover` |
| **Mobile crop** | Full card width in carousel |
| **Format** | WebP or PNG |
| **Upload path** | Admin → Page Settings → Homepage → Routes → re-upload image for `seed_khi_dxb` |

---

## P1 — Required production-quality replacement

_None._ All other public homepage photography is approved and rendering correctly on production.

---

## P2 — Optional visual enhancement

### JP-FINAL-002 — Dedicated mobile hero image

| Field | Value |
|-------|-------|
| **Page / section** | Homepage hero (mobile) |
| **Source** | `hero_background_mobile` CMS slot |
| **Current** | Falls back to desktop `hero_background` (2048×768) — works but not ideal 4:5 crop |
| **Recommended subject** | Portrait Pakistan travel hero matching desktop brand |
| **Aspect ratio** | 4:5 |
| **Min resolution** | 1200×1500 |
| **Upload path** | Admin → Page Settings → Homepage → Hero image (mobile) |

---

## Inspected — no owner action required

### A. All 41 prior manifest entries reclassified

Every `JP-MEDIA-001` … `JP-MEDIA-041` record was a **non-image context** (link, route, anchor, mailto, URL resolver, airline logo, or CMS preview). See `JP-MEDIA-FINAL-OWNER-LIST.json` → `inspectedNoOwnerAction.falsePositivesFromPriorManifest` for per-record evidence.

**Common false-positive patterns removed:**

- `{{ $href }}` on `<a>` tags (dest-card, route-card, group-card, action-card)
- `{{ route(...) }}` / `{{ client_route(...) }}` navigation targets
- `mailto:`, `tel:`, `$loginUrl`, `$waUrl`, `$ctaUrl`, `$downloadUrlFor()`
- `#hotels`, `#ota-flight-search`, `#ota-home-hero`, `#sidebar-group-ticketing-submenu`
- `resolveDestination()` page URL resolvers (about, FAQ, support, agent landing, drawer, header)
- Airline logo `<img>` tags (checkout, group ticketing) — **supplier runtime, excluded**
- Admin CMS `<img>` preview of existing destination upload — **already satisfied**

### B. Production slots verified good (2026-09-13)

| Slot | Status | Natural size |
|------|--------|-------------|
| `hero_background` | APPROVED_EXISTING | 2048×768 |
| `support_cta_background` | APPROVED_EXISTING | 1672×941 |
| `destination_seed_{dxb,jed,lhr,ist}` | APPROVED_EXISTING | 1448×1086 each |
| `route_seed_lhe_jed` | APPROVED_EXISTING | 1536×1024 |
| `route_seed_isb_lhr` | APPROVED_EXISTING | 1536×1024 |
| `route_seed_khi_ruh` | APPROVED_EXISTING | 1536×1024 |
| Featured deals (×2) | APPROVED_EXISTING | 1448×1086 |
| Brand logo | BRAND_AUTHORITY | 400×85 |

### C. Not in scope / excluded

- FAB geometry, responsive layout, Lucide icons — **closed, not reopened**
- Legacy `tournest-home-main.blade.php` blog placeholders — **not mounted on production homepage**
- Group packages section — **not currently rendered on production homepage**; no broken images observed

---

## Root cause

`frontend/scripts/audit-jp-media-requirements.mjs` uses regex `src|href=["']...` and classifies by **file path keywords** (`hero`, `destination`, `group`) rather than inspecting whether the attribute is actually an image slot. This produced 84 raw / 41 deduped false owner requirements.

**Remediation tool added:** `frontend/scripts/semantic-jp-media-closure-02.mjs` (draft evidence in `JP-MEDIA-SEMANTIC-CLOSURE-02-DRAFT.json`).

---

## Deployment

Documentation-only change. **No production deployment required.**
