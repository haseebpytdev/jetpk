# JP-UI-04A Payment and Success State QA

Phase: JP-UI-04A | Scenarios: Payment 17, Success 16

## Payment states verified

- Manual Payment + AbhiPay only; no embedded card form
- Initiating, pending, failed, canceled, provider unavailable, manual pending, expired session
- Authoritative amount from fixture

## Success states verified

- Confirmed, payment pending, PNR pending, ticketing pending, ticketed
- Invoice action conditional; not-found and unauthorized safe
- `noindex` metadata on confirmation page
- No fake PNR/ticket when pending

## Scores

| Surface | Score |
|---------|:-----:|
| Payment desktop light/dark | 4 |
| Payment mobile | 4 |
| Payment 150% zoom | 4 |
| Success desktop light/dark | 4 |
| Success mobile | 4 |
| Success 150% zoom | 4 |
