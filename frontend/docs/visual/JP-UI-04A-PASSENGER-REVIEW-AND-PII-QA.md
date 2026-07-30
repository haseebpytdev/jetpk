# JP-UI-04A Passenger, Review, and PII QA

Phase: JP-UI-04A | Scenarios: Passengers 15, Review 14, Seats 4

## Passenger states

- Theme/viewport/zoom matrix
- One adult, mixed adult/child/infant, validation errors, expired session, save failure
- Mobile order summary expanded/collapsed
- No PII in URL/localStorage from fixture contract

## Review states

- Complete, no-seats notice, consent blocked, fare-change panel, submit busy, creation failure
- Authoritative total from fixture (`Rs. 124,999`)
- No fake PNR before booking creation

## Seat capability (unsupported)

- `seat_map_available: false` — Seats step omitted from progress
- No seat map DOM, routes, or fake pricing
- Classified future conditional; mockup unscored

## Scores

| Surface | Score |
|---------|:-----:|
| Passengers desktop light/dark | 4 |
| Passengers mobile | 4 |
| Passengers 150% zoom | 4 |
| Review desktop light/dark | 4 |
| Review mobile | 4 |
| Review 150% zoom | 4 |
