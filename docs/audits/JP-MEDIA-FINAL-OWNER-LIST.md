# JetPakistan — Final Owner Media List (V2)

**Phase:** JETPAKISTAN-PRODUCTION-VISUAL-MEDIA-PLACEHOLDER-AUDIT-03  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Supersedes:** Closure-02 semantic manifest (false-positive removal)  
**Evidence:** `docs/evidence/jp-media-visual-audit-03/`

## Summary

Closure-02 removed 41 false-positive owner entries. This pass is a **rendered visual audit** of production plus static asset inventory. Three owner actions remain.

| Priority | Count | Items |
|----------|------:|-------|
| **P0** | 1 | Broken homepage route card image |
| **P1** | 1 | Generic auth illustration (login/register/recovery/lookup) |
| **P2** | 1 | Optional dedicated mobile hero crop |
| **Total** | **3** | |

`UNKNOWN_MEDIA_CLASSIFICATION=0` · **STATUS=PASS**

---

## Auth illustration decision

| Field | Value |
|-------|-------|
| `AUTH_ILLUSTRATION_CURRENT` | `/images/auth/auth-illustration.svg` |
| `AUTH_ILLUSTRATION_PRODUCTION_VISIBLE` | **YES** |
| `AUTH_PAGES_USING_IT` | `/login`, `/register`, `/forgot-password`, `/login/otp`, `/agent/register`, `/verify-email`, `/reset-password`, `/lookup-booking` |
| `AUTH_ILLUSTRATION_CLASSIFICATION` | `GENERIC_ILLUSTRATION_REPLACE` |
| `AUTH_REPLACEMENT_REQUIRED` | **YES** |
| `AUTH_RECOMMENDED_ASPECT_RATIO` | 2:1 |
| `AUTH_RECOMMENDED_MIN_DIMENSIONS` | 1280×640 |

**Recommendation:** Replace with **one** shared production-quality illustration for all `AuthShell` flows. The current SVG is flat placeholder art (green hills + abstract plane). Do not mark approved merely because HTTP=200.

**Static vs CMS:** Static file at `frontend/public/images/auth/auth-illustration.svg` — owner replaces file, then deploy.

---

## P0 — Broken / visibly defective

### JP-FINAL-001 — Homepage route card (LHE→DXB)

| | |
|--|--|
| **Visible** | https://jetpakistan.pk/ → “Where Pakistan is flying.” first card |
| **Asset** | `route_seed_khi_dxb-20260910194137.png` (1×1 px) |
| **Appearance** | Solid pink/coral blank card (see `evidence/.../desktop/home.png`) |
| **Action** | Re-upload via CMS → Page Settings → Homepage → Routes |
| **Spec** | 4:3, min 1536×1024, WebP/PNG |

---

## P1 — Visible placeholder / generic temporary artwork

### JP-FINAL-003 — Auth + lookup illustration

| | |
|--|--|
| **Visible** | Auth panel on login/register/forgot-password/agent-register/verify-email; hero band on `/lookup-booking` |
| **Asset** | `/images/auth/auth-illustration.svg` |
| **Appearance** | Generic vector placeholder (see `evidence/.../desktop/login.png`) |
| **Action** | Owner creates/uploads production JetPakistan travel illustration |
| **Spec** | 2:1, min 1280×640, WebP/PNG preferred |
| **Deploy** | Replace `frontend/public/images/auth/auth-illustration.svg` + production deploy |

---

## P2 — Optional enhancement

### JP-FINAL-002 — Mobile hero crop

| | |
|--|--|
| **Visible** | Homepage mobile hero (desktop CMS image used as fallback) |
| **Action** | Upload `hero_background_mobile` in CMS (4:5, min 1200×1500) |

---

## Production approved (no owner action)

Verified rendering on 2026-09-13:

- Homepage hero (`hero_background` 2048×768) — **CMS_APPROVED**
- Support CTA background — **CMS_APPROVED**
- Destination cards (DXB, JED, LHR, IST) — **CMS_APPROVED**
- Route cards LHE→JED, ISB→LHR, KHI→RUH — **CMS_APPROVED**
- Featured deals (×2) — **CMS_APPROVED**
- Brand logos — **BRAND_AUTHORITY**
- Support page, groups search — no placeholder decorative imagery

---

## Static assets reviewed (not visible on production)

| Asset | Classification |
|-------|----------------|
| `frontend/public/images/home/hero-fallback.svg` | Latent placeholder fallback (CMS hero present) |
| `frontend/public/images/home/destination-*.svg` | Dev fixtures only |
| `frontend/public/images/home/inspiration-*.svg` | Dev fixtures only |
| `frontend/public/images/home/offer-*.svg` | Dev fixtures only |
| `public/themes/.../homepage-destination-fallback.svg` | Blade fallback (CMS destinations present) |

---

## Audit counts

```
PRODUCTION_PAGES_VISUALLY_REVIEWED=12
SOURCE_MEDIA_SLOTS_REVIEWED=47
STATIC_IMAGE_ASSETS_REVIEWED=13
CMS_MEDIA_SLOTS_REVIEWED=11

APPROVED_FINAL=10
CMS_APPROVED=10
BRAND_AUTHORITY=2
DYNAMIC_RUNTIME=5
DECORATIVE_FINAL=9

PLACEHOLDER_REPLACE=1 (latent hero-fallback, not visible)
GENERIC_ILLUSTRATION_REPLACE=1
FALLBACK_CURRENTLY_VISIBLE=0
BROKEN_REPLACE=1
OPTIONAL_ENHANCEMENT=1

OWNER_ACTION_P0=1
OWNER_ACTION_P1=1
OWNER_ACTION_P2=1
OWNER_ACTION_TOTAL=3
```

---

## Deployment

Documentation + evidence only in this commit. **No production deploy required** until owner supplies replacement media.
