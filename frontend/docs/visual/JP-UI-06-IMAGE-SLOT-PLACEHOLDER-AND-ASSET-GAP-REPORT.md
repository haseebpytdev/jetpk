# JP-UI-06 Image Slot Placeholder and Asset Gap Report

Phase: **JP-UI-06**

## Image slot contract

All image regions use `ImageSlot` with preserved geometry (box, ratio, radius, object-fit, skeleton, dark semantic tokens). Missing originals → neutral placeholder + logged gap.

## Blocking gaps

| Slot | Page | Status | Notes |
|------|------|--------|-------|
| All 13 Backup Safe mockup PNGs | Reference normalization | **Blocking** | Workstation Backup Safe has archives only; synthetic refs generated |
| Homepage hero photograph | `/` | Medium | CMS/fallback SVG — exception D |
| Auth illustration | login/signup | Low | `auth-illustration.svg` placeholder |
| Lookup hero | `/lookup-booking` | Low | Shared auth illustration slot |

## Action

Restore Jul 27 2026 mockup PNGs to `C:\Users\khadi\Backup Safe` per `JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md`, re-run `npm run audit:visual:jp-ui-06`.
