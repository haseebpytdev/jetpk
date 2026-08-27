# JP-COMBINED-01-R2 SUMMARY

## SHAs
| Role | Value |
|---|---|
| BRANCH | `phase/jp-grp-return-closeout-01` |
| START_DOCS_SHA | `e9996f85d81c2a2b683b1a3d28206f31f4568b7a` |
| START_DEPLOYED_SHA | `6057f8d9344dd54e1f52c95879ad178f7cdd6f0a` |
| FINAL_ENGINEERING_SHA | `460cdae0441d0e07c563e636280c0e552481ac92` |
| Prior eng in R2 | `b75bc9a5` (soft-warning filter + passenger JSON submit + route OD) |

## Engineering changes (R2)
1. Suppress fatal pipeline warnings when soft inventory path is active (`FlightResultsPage`).
2. Round-trip fare route label prefers commercial OD / seeded route (`route-label.ts`, `PairReturnCard` route seed).
3. Passenger SPA submit uses `?format=json` (`standard-booking-api.ts`).

## Live gates exercised
- Paired fare tabs + brand sync (BASIC/FLEX/SMART): baggage/policy/details update with brand.
- Soft warning only; no dual fatal+soft mix after deploy.
- Paired QA draft created (review, `booking_reference=null`, `pnr=null`), then abandoned.
- Full segmented outbound brand → return options (outbound fare key preserved) → return brand → checkout → draft → abandon.
- One-way branded fare tabs + Book Now handoff.
- Change Flight / abandon → fresh results.
- Browser back/forward from checkout.
- Groups landing smoke.
- Mobile paired/segmented screenshots.

## Commercial
- SABRE_PNR_CREATED=NO
- PAYMENT_EXECUTED=NO
- TICKET_ISSUED=NO
- ALHAIDER_BOOKING_MUTATION=NO
- QA_DRAFT_SIDE_EFFECTS=0 (abandoned via `/booking/abandon-selected-offer`)

## Evidence
`docs/evidence/jp-combined-01/` (05–10, 12–18, 26–29 + JSON proofs)

## Deploy
- FINAL_ENGINEERING_SHA / DEPLOYED_RUNTIME_SHA = `460cdae0441d0e07c563e636280c0e552481ac92`
- PUBLIC_BUILD_ID = `cFI8u0BMqusPSE84bxubP`
- LIVE_SOURCE_DRIFT=0 ACTIVATE=PASS LIVE_DEPLOYABLE_FILE_COUNT=12

