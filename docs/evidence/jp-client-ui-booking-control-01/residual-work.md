# JP-CLIENT-UI-BOOKING-CONTROL-01 — Residual work

## Blockers for full R7 PASS

1. **Admin visual matrix incomplete** — Guest Booking ON/OFF Admin screenshots and registration-off matrix not captured (requires Admin session).
2. **Traveler header live screenshot missing** — containment shipped in `c3586929`; live Adult 1 / Lead capture not completed without entering transactional checkout beyond Account gate.
3. **Group Customer ON/OFF + Agent live UX screenshots missing** — server eligibility + tests pass; visual states not captured.
4. **Journey Details branded inter-leg separator live screenshot missing** — implementation present (`Thanks for Choosing JetPakistan` + logo for `destination_stay`); true layover path preserved in code; drawer capture incomplete.
5. **PIA logo sample not on first result pages** — Etihad square container captured; PK sample still needed.

## Not blockers for engineering deploy

- BookingControlMatrixTest: 10 passed
- Guest Account gate OLS-safe via `/login?booking_gate=account`
- Flight dates + paired Departure/Arrival strip live-confirmed
- Overnight/next-day dates live-confirmed (e.g. 18 OCT / 22 OCT)
- Commercial mutations: none executed
