# JP-UI-06 Comparison Mask Governance

Phase: **JP-UI-06**  
Manifest: `frontend/tests/visual-audit/jp-ui-06-masks.json`

## Rules

**Permitted masks:** dynamic literal text glyph interiors, prices, booking references, PNR values, supplier-returned values, unavailable original image pixels.

**Never mask:** container edges, panel shapes, spacing, section boundaries, curves, overlaps, card geometry, input geometry, dividers, CTA geometry, headers, footers, progress, order summaries, alignment, line-height boxes.

## Governance thresholds

| Metric | Threshold | Action |
|--------|-----------|--------|
| Masked area % | ≤ 20% | Auto gate pass |
| Masked area % | > 20% | `verify-jp-ui-06.mjs` warns; manual review required |
| Seat-map region | N/A | Must not be masked to fake capability parity |

`compare-jp-ui-06.mjs` writes `maskedAreaPercent` per family into `comparison-summary.json` and the HTML evidence index.

## Per-family comparison modes

| Mode | Families |
|------|----------|
| `exact` | homepage, about, support, flight-results, passenger-details, review, booking-success, login, signup, manage-booking |
| `exact_with_operational_substitution` | fare-selection, payment |
| `capability_exception` | seat-selection-capability-unavailable |

Each mask record includes: `page`, `region`, `x`, `y`, `width`, `height`, `reason`, `data_authority`, `approved_comparison_mode`.
