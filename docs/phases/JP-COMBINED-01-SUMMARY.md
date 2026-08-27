# JP-COMBINED-01 SUMMARY

## Branch / SHAs
| Role | Value |
|---|---|
| COMBINED_BRANCH | `phase/jp-grp-return-closeout-01` |
| COMBINED_START_SHA | `b43e38a79b46f4b191ed7c7dc476daa9dc92dc6f` |
| GROUP_REPORTED_ENGINEERING_SHA | `4d2f2e7fee82a1fc6b7e7f6fa897cf1b3423c1fd` |
| GROUP_REPORTED_DOCS_SHA | `b43e38a79b46f4b191ed7c7dc476daa9dc92dc6f` |
| FINAL_ENGINEERING_SHA | `6057f8d9344dd54e1f52c95879ad178f7cdd6f0a` |
| Prior eng commit | `efdfa0b3f2dfb48368cd13a8991cc5dc0a819923` |

## Git reconciliation
- Remote `jetpk/phase/jp-grp-ui-01` was at `8426b128`; local had 4 unpushed commits through `b43e38a7`.
- Fast-forward push completed (no force). `GROUP_REMOTE_DOCS_HEAD_VALID=YES`.
- Readonly parity vs `4d2f2e7f`: `LIVE_SOURCE_DRIFT=0`, `OLS=PASS`, `PUBLIC_BUILD_ID=1jOTXlcR6qXjGGiqBnnd-`.

## Temp customer cleanup
- USER_ID=13 deleted after FK check (`group_bookings=0`, commercial count=0).
- `TEMP_CUSTOMER_CLEANUP=PASS`, `TEMP_CUSTOMER_CREDENTIAL_DISABLED=YES`.

## Group smoke (live, no CMS mutation)
- Landing / results / detail polished; modern selects; seat truth â€œAvailability refreshed (read-only)â€; supplier booking gated copy present.
- Auth required for checkout; public browse allowed.

## Return flight root causes fixed
1. Stale cards + fatal banner mix â†’ hide list on hard error/failed; clear data on view change / HTTP error.
2. Journey field mismatch (`origin`/`stops_count` vs FE aliases) â†’ Laravel enricher + FE `normalizeJourneyDisplay`.
3. Pair drawer missing seed â†’ `pairedOptionToOffer` + soft fallback for pair/return.
4. Mixed-carrier pairs excluded â†’ same-carrier gate removed for index.
5. Pipeline `status=failed` with usable `paired_options` â†’ treat as ready + soft warning (not fatal banner).
6. `return=` query alias accepted as `return_date`.

## Tests
- `ReturnSplitComboServiceTest` PASS (7)
- `ReturnSplitSelectFlowTest` PASS (9)

## Deploy
- Protected stage/backup/activate for SHA `6057f8d9` (manifest count 10).
- DEPLOYED_RUNTIME_SHA=6057f8d9344dd54e1f52c95879ad178f7cdd6f0a  
PUBLIC_BUILD_ID=_KhVpL3OfrrgX19SM8Fi8  
LIVE_SOURCE_DRIFT=0 OLS=PASS ACTIVATE=PASS

## Evidence
`docs/evidence/jp-combined-01/`

## Hard stop
STOP for owner/ChatGPT. No Sabre PNR / payment / ticketing / Al-Haider booking mutation.

