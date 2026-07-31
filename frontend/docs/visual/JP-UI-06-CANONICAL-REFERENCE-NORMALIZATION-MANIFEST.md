# JP-UI-06 Canonical Reference Normalization Manifest

Phase: **JP-UI-06**  
Branch: `phase/jetpk-ui-06-canonical-mockup-blueprint-parity`  
Script: `frontend/scripts/normalize-jp-ui-06-references.mjs`

## Authority

| Item | Value |
|------|-------|
| Source root (read-only) | `C:\Users\khadi\Backup Safe` (override: `JP_UI_06_BACKUP_SAFE`) |
| Source dimensions | 1122×1402 @ 96 DPI (all 13 families) |
| Browser chrome | Auto-detected top crop (default 72px) |
| Normalized output | `frontend/.visual-audit/jp-ui-06/reference/{family}-normalized.png` |
| Machine manifest | `frontend/.visual-audit/jp-ui-06/reference-manifest.json` |

## Effective viewport contract

After chrome crop, canonical desktop captures use **1122×1330** with `deviceScaleFactor: 1`. No non-proportional resizing.

## Capture run status (this branch)

| Field | Value |
|-------|-------|
| Families normalized | 13 |
| Source PNGs found | 0 |
| Synthetic references | 13 |
| Canonical viewport | 1122×1330 |

**Blocking asset gap:** Backup Safe on this workstation contains archives only (no Jul 27 mockup PNGs). Synthetic labelled references were generated so the compare/index pipeline can run. Restore the 13 SHA-256-verified PNGs from `JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md` before claiming final pixel parity.

## Per-family registry

| Family | Mockup file | Crop top | Effective size | Source |
|--------|-------------|----------|----------------|--------|
| homepage | ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png | 72px | 1122×1330 | synthetic |
| about | ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png | 72px | 1122×1330 | synthetic |
| support | ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png | 72px | 1122×1330 | synthetic |
| flight-results | 520bfb29-bc9c-432c-88f1-b53cdadb1592.png | 72px | 1122×1330 | synthetic |
| fare-selection | 6ea78679-e345-49ea-a4be-2e2f539940c6.png | 72px | 1122×1330 | synthetic |
| passenger-details | ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png | 72px | 1122×1330 | synthetic |
| seat-selection-capability-unavailable | 45f39a0b-e38f-4ad2-9077-f631217bd185.png | 72px | 1122×1330 | synthetic |
| review | 64460b63-9930-478c-96cb-e7a00345caea.png | 72px | 1122×1330 | synthetic |
| payment | ab903350-d59f-4b60-b254-9350e4da8f00.png | 72px | 1122×1330 | synthetic |
| booking-success | ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png | 72px | 1122×1330 | synthetic |
| login | 542ee36d-c542-4eec-b5d4-995d555f8ba6.png | 72px | 1122×1330 | synthetic |
| signup | 0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png | 72px | 1122×1330 | synthetic |
| manage-booking | 678318b0-28f6-4588-ad03-f405f361152e.png | 72px | 1122×1330 | synthetic |

## Measurement proposals

`frontend/scripts/measure-jp-ui-06-reference.mjs` writes row-edge proposals to  
`frontend/.visual-audit/jp-ui-06/measurement-proposals.json`. Curated landmarks are in `frontend/tests/visual-audit/jp-ui-06-blueprint-geometry.json`.
