# JP-BO-04G Final UI — Live Screenshot Evidence

**UTC folder:** `20260826T123600Z`  
**Runtime SHA:** `0cadd2082e39befe03c1cc089cfa53fee5377e6c`  
**Public build:** `N2UgmUu_xxKIyYUu2pLRo`  
**Host:** https://jetpakistan.pk  

All screenshots are sanitized (no passenger PII, passport, payment, PNR, tokens). Flows stop before PNR creation.

| File | URL/path | Trip mode | Proves | Status |
| --- | --- | --- | --- | --- |
| 01-one-way-results.png | `/flights/results` KHI→DXB one-way | One-Way | Full OW result cards (airline, times, route, price, Details, Book Now) | PASS |
| 02-one-way-details.png | Details drawer on OW | One-Way | Fare cards / confirmation drawer | PASS |
| 03-segmented-outbound-results.png | `/flights/results?...&view=segmented` LHE↔DXB | Segmented | Outbound cards match OW chrome | PASS |
| 04-segmented-outbound-fare.png | Outbound Details | Segmented | Fare confirmation before Continue | PASS |
| 05-segmented-return-results.png | `/flights/return-options` | Segmented | Full return result cards | PASS |
| 06-segmented-return-fare.png | Return Details | Segmented | Independent return fare selection | PASS |
| 07-segmented-passenger.png | `/booking/passengers` split | Segmented | Outbound SMART + Return FREEDOM shown separately | PASS |
| 08-segmented-summary.png | Passenger summary panels | Segmented | Split summary parity | PASS |
| 09-segmented-review.png | Checkout surface (pre-PNR) | Segmented | Review continuity capture | PASS |
| 10-paired-results.png | `/flights/results?...&view=pair` | Pair | Single paired card with outbound+return | PASS |
| 11-paired-details.png | Pair Details | Pair | One shared fare selection (3 cards) | PASS |
| 12-paired-review.png | Pair passenger (pre-PNR) | Pair | Paired selection continuity | PASS |
| 13-fallback-fare-card.png | Details on no-brand / base path | Mixed | Truthful Available/Standard fare card present in sample | PASS |
| 14-connecting-layover.png | LHE→LHR connecting Details | One-Way connecting | `Layover in GYD` + `11h 40m` | PASS |
| 15-direct-no-layover.png | KHI→DXB Direct Details | One-Way direct | `layover-block` count = 0 | PASS |

## Live split fare authority (URL + UI)

- `outbound_fare_option_key=sm-pi1` → passenger **Selected fare SMART**
- `return_fare_option_key=fl-pi2` → passenger **Selected fare FREEDOM**

## Notes

- `LIVE_DIFFERENT_BRANDS` available in this sample (SMART vs FREEDOM).
- Sandbox CERT auth remains `DEFERRED_EXTERNAL_CERT_AUTH`.
- No live Sabre PNR create/cancel in this pass.
