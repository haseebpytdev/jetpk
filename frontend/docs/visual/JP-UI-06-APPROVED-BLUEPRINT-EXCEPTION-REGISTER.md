# JP-UI-06 — Approved Blueprint Exception Register

Phase: **JP-UI-06**  
Status: **Active** — no additional exceptions without explicit user approval.

## Comparison modes

| Mode | Definition |
|------|------------|
| `exact` | Full blueprint geometry, composition, hierarchy |
| `exact_with_operational_substitution` | Blueprint geometry exact; one region replaced by authoritative implementation |
| `capability_exception` | Backend capability absent; real state shown, not fake workflow |

## Approved exceptions

### A. Fare-selection journey order

- **Mode:** `exact_with_operational_substitution`
- **Operational order:** Search → Results → Fare Selection → Travelers → Review → Payment → Success
- **Allowed deviation:** Step labels and active position may differ from mockup
- **Required:** Stepper geometry, spacing, styling and hierarchy must match

### B. Seat selection

- **Mode:** `capability_exception`
- **Condition:** `seat_map_available = false`
- **Capture:** `seat-selection-capability-unavailable` on `/booking/passengers`
- **Forbidden:** Fake route, aircraft, seats, prices, selections
- **Evaluation:** Dedicated capability-state contract, not seat-map pixels

### C. Payment card-details region

- **Mode:** `exact_with_operational_substitution`
- **Preserved:** Page composition, method selector, content-region dimensions, order-summary dimensions, CTA hierarchy, spacing, cards, progress
- **Substitution:** Direct-card form region → AbhiPay secure handoff panel in same measured container
- **Forbidden:** Card number, expiry, CVV fields

### D. Missing original standalone imagery

- Retain exact image-slot geometry, mask, crop, radius, placement
- Use approved neutral placeholder
- Record missing asset as blocking gap
- Do not claim final pixel parity for image pixels

### E. Unsupported claims, links or controls

- Do not reproduce unsupported navigation, support claims, promotional claims, social links, hotel controls, refund/change/baggage controls, operational buttons
- Use authoritative content where available; preserve blueprint geometry
- Record narrowly scoped approved deviation otherwise
