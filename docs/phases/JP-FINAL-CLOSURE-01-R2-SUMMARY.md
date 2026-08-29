# JP-FINAL-CLOSURE-01-R2 SUMMARY

## Phase name
JP-FINAL-CLOSURE-01 (R2 continuous completion)

## Branch name
`phase/jp-flight-perf-01`

## Objective
Restore exact engineering runtime parity after mixed 9-file mail patch; prove NEW_QA verification via temporary MessageSending sink; finish email preview pack; certify Groups hero + manual_local; portal/flight/responsive/perf gates; remove sink and activate FINAL_CLEAN.

## Included scope
- Exact-SHA deploy of QA_E2E engineering object `57d23ded…` (30 runtime paths + public/dashboard rebuilds)
- Temporary QA mail sink enable → NEW_QA customer E2E → sink disable
- Email preview pack (≥16 HTML + screenshots)
- Groups hero recapture + manual_local visibility/package/price/seats
- Customer / Agent / Admin portal smoke
- Flight results regression smoke
- Responsive + light performance matrices
- FINAL_CLEAN sink code removal deploy `0500486e…`

## Excluded scope
- Supplier PNR / payment / ticketing mutations
- Changing Sabre cancel production flags (observed true; left unchanged)
- PIA NDC (known `SUPPLIER_AUTH_REJECT`)

## Investigation findings / root causes
- R1 mixed runtime claimed as full SHA authority was incorrect (9-file patch only).
- Checkout HTTP 500 root cause (R1): `Registered` inside DB transaction + stock verification mailer; fixed with BestEffort + post-commit event (retained in FINAL_CLEAN).

## Exact files changed (FINAL_CLEAN)
- `app/Providers/AppServiceProvider.php` (remove MessageSending sink listen)
- Deleted: `app/Support/Qa/JetpkQaMailbox.php`
- Deleted: `app/Listeners/Mail/CaptureJetpkQaMailboxMessage.php`
- Deleted: `app/Console/Commands/JetpkQaMailLatestCommand.php`
- Deleted: `config/jetpk_qa_mail.php`
- Deleted tests for sink

## Routes / DB
- No route or migration changes in FINAL_CLEAN.
- Phase QA: temporary `OTA_GROUP_QA_VIEWER_EMAILS` + two `manual_local` inventory rows (deactivated on FINAL_CLEAN).

## Tests executed
- Mail BestEffort + security isolation: **4 passed / 27 assertions**
- GroupManualLocalInventoryTest: **4 passed / 13 assertions**
- Earlier R2 MessageSending integration (pre-clean): **9 passed** on eng SHA `57d23ded`

## Screenshots / evidence
Under `docs/evidence/jp-final-closure-01/live-final/` and `docs/evidence/jp-final-closure-01/email/`.

## Known limitations
- Guest deep-link to manual_local package may still show checkout chrome without full title (search/API hide ML for guests).
- Email previews can show agency sample phone from local DB.

## Rollback
- Backup ID: `jp-final-closure-01-r2-20260829T052059Z`
- Restore AppServiceProvider from backup; restore deleted sink files only if intentionally re-enabling phase harness (not recommended).

## SHAs
- QA_E2E_ENGINEERING_SHA: `57d23dedf3082416d53d9d36650b763913bfb156`
- FINAL_CLEAN_ENGINEERING_SHA: `0500486e102d498d55e32ad4871f1495695b7a0d`
- Public build during QA gates: `itomiK6_faLRMh81yE9mu` (unchanged by FINAL_CLEAN Laravel-only)

## Final status
`RUN_COMPLETED=YES` — FINAL_CLEAN `ACTIVATE=PASS` (`0500486e…`), sink removed, BestEffort retained, public build `itomiK6_faLRMh81yE9mu`.
