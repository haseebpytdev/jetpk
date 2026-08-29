# JP-FINAL-CLOSURE-01-R4 — ChatGPT visual review pack

## Runtime authority

- ENGINEERING=`6e3ea4e69bbd2d463aaabfe2f53d93388e29b3f9`
- BUILD=`OUwL6VdIoWW07Xli8W_KB`
- HOST=`https://jetpakistan.pk`

## Flight card comparison (canonical One-Way)

Compare side-by-side CTAs `[ Details ] [ Book Now ]`:

| Surface | Desktop | Mobile |
|---|---|---|
| One-Way | `live-final/r4/oneway-card-desktop.png` | `oneway-card-mobile.png` |
| Return Pair | `return-pair-card-desktop.png` | `return-pair-card-mobile.png` |
| Segmented Outbound | `segmented-outbound-card-desktop.png` | `segmented-outbound-card-mobile.png` |
| Segmented Return | `segmented-return-card-desktop.png` | `segmented-return-card-mobile.png` |

Code parity JSON: `live-final/flight-card-parity.json`

## Portals / Groups

- `groups-results-desktop.png` / `groups-results-mobile.png`
- `customer-dashboard-desktop.png` (may be login redirect as guest)
- `agent-dashboard-desktop.png`
- `admin-dashboard-desktop.png`

## Email

- Fresh hardcode reaudit: `email/email-hardcode-audit-r4.json` → EMAIL_HARDCODE_REAUDIT=PASS
- Semantic previews/shots: `email/previews-r4/` + `email/preview-shots-r4/` (generated in R4 closeout)

## Group local E2E

- `live-final/r4-group-local-e2e.json` — JFZZT2DJ / WZBJCK6Z still `manual_local`, supplier_reservation_id NULL, payment unexecuted

## Performance

- Primary metrics from instrumented `jp-book-now-timing` marks (not inflated Playwright `waitForURL(load)` wall clock)
- `live-final/r4/book-now-timing-breakdown.json`
- SHELL p50=`5267`ms p95=`38921`ms
- USABLE p50=`8206`ms p95=`40879`ms
- REVALIDATION p50=`1640`ms p95=`1996`ms
- **PERFORMANCE=FAIL** (usable p95 still ~40s / high variance; revalidate not dominant)

## Reviewer checklist

1. Actions never vertically stack as link-over-button on standard cards
2. Pair keeps OUTBOUND|RETURN middle layout with One-Way outer shell
3. Email ticket/refund/group detail rows show useful context when fixture/live scalars exist
4. Confirm engineering SHA ≠ docs tip after evidence commit
5. Do not convert PERFORMANCE=FAIL into PASS
