# Phase SABRE-LIVE-SCENARIO-END-TO-END-CLOSURE-2 — Summary

## Phase name
SABRE-LIVE-SCENARIO-END-TO-END-CLOSURE-2

## Branch name
*(set when committing — not committed in this pass)*

## Objective
Execute one controlled production acceptance run for the full Sabre GDS lifecycle: fresh search → mandatory revalidation → single unticketed PNR create → dual retrieve (idempotent comms proof) → single unticketed cancel → local reconciliation → idempotent reconciliation proof. Produce a sanitized final report with no ticketing and no duplicate supplier mutations.

## Included scope
- Scenario runner closure evidence: double retrieve, revalidation slice on success, reconciliation idempotency re-run, `closure_verification` counts/statuses
- Production operator runbook (this document)
- Automated test regression for lifecycle + scenario runner (35 tests / 174 assertions)

## Excluded scope
- No new Sabre HTTP mutation types
- No ticketing
- No PIA NDC mutations
- No generic migrations
- No changes to Sabre cancellation env flags or JetPK OTP patch
- No automatic retry after ambiguous create/cancel results

## Investigation findings
- Local workstation (`APP_ENV=local`, inactive Sabre connection id=2) cannot execute live shop/revalidate/PNR; `shop_request_failed` on plan probe
- SSH alias `hostinger-ota` (`145.223.77.132`) exists but key auth is not configured from this machine (`Permission denied (publickey,password)`)
- Prior pilot PNR `FEZJFP` already completed lifecycle closure-1 fixes; this phase requires **one new** PNR only
- Runner previously performed only one retrieve and one reconciliation pass; acceptance criteria require duplicate-proof verification

## Root causes (tooling gaps closed in this pass)
1. Scenario runner did not perform second canonical retrieve after create
2. Success path omitted explicit revalidation evidence fields in output slice
3. Reconciliation idempotency (`already_reconciled=true`) was not proven in-runner
4. Final report fields (comm counts, audit/status-log counts, attempt rows, `is_ticketed`) required manual DB inspection

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `app/Console/Commands/SabreGdsLiveScenarioRunnerCommand.php`
- `docs/phases/SABRE-LIVE-SCENARIO-END-TO-END-CLOSURE-2-SUMMARY.md` *(new)*

## Routes changed
None

## Database changes
None

## Backend changes
- `book-and-retrieve` / `book-retrieve-and-cancel` now sync twice; comm count must remain stable
- `book-retrieve-and-cancel` reconciles twice; second pass must return `already_reconciled=true`
- Output JSON + CLI summary include `closure_verification`, `offer_identifiers`, revalidation totals

## Frontend changes
None

## Production pre-flight (mandatory)
On the **pilot production server** (SSH), confirm before any mutation:

```bash
cd ~/domains/jetpakistan.com/public_html   # adjust if deploy root differs
php artisan about | grep -E 'Environment|Queue|Mail'
# Expect: APP_ENV=production, QUEUE_CONNECTION=sync, MAIL_MAILER=smtp

php artisan tinker --execute="
\$c = \\App\\Models\\SupplierConnection::query()->where('provider','sabre')->where('is_active',1)->first(['id','base_url']);
echo json_encode(\$c?->toArray());
"
# Confirm active Sabre connection id (typically 2) and production host api.platform.sabre.com

grep -E '^SABRE_.*CANCEL|^SABRE_TICKETING_ENABLED|^SABRE_BOOKING_' .env | sort
# Sabre cancellation flags: unchanged from current production
# SABRE_TICKETING_ENABLED=false (or equivalent gate off)
```

**Passenger JSON:** use the existing private production fixture (never commit). Example path pattern:
`/home/.../private/sabre-scenario-passenger.json`

**Departure date:** choose a fresh date ≥ 14 days out with known QR LHE→DOH→JED availability.

## Step 0 — Optional plan probe (no booking)
```bash
php artisan sabre:gds-live-scenario-runner \
  --mode=plan \
  --preset=qr-connecting \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --connection=2 \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER \
  --production-ops-approval=APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER
```
Stop if `eligible_offer_count=0` or `shop_request_failed`.

## Step 1 — Single controlled end-to-end run (ONE PNR only)
```bash
php artisan sabre:gds-live-scenario-runner \
  --mode=book-retrieve-and-cancel \
  --preset=qr-connecting \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --connection=2 \
  --max-bookings=1 \
  --strategy=auto \
  --passenger-json=/ABS/PATH/TO/private-passenger.json \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER \
  --production-ops-approval=APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER \
  --cancel-approval=CANCEL-UNTICKETED-SABRE-GDS-TEST-PNRS
```

**Hard stops (do not retry create/cancel):**
- `freshness_satisfied=false`
- `revalidation_success=false`
- `fare_changed=true` / `scenario_fare_change_requires_acceptance`
- `booking_created=false` with any `error`
- `cancellation_success=false`
- `reconciliation_already_reconciled_on_second_run=false` after successful first reconciliation

## Acceptance criteria mapping

| Requirement | Evidence field |
|---|---|
| Fresh search + exact offer + revalidation | `revalidation_attempted=true`, `revalidation_success=true`, `freshness_satisfied=true`, `selected_total`, `revalidated_total`, `fare_changed=false`, `revalidation_at`, `offer_identifiers` |
| No booking when blocked | `booking_created=false`, `bookings_created=0` |
| HTTP PNR success, no ticket | `http_status`, `pnr`, `live_call_attempted=true`, `create_pnr_attempt_count=1`, `is_ticketed=false`, `ticketing_attempt_count=0` |
| One supplier-booking-created comm | `supplier_booking_created_comm_count=1`, statuses `sent` or `failed` (never stuck `queued` with sync queue) |
| Dual retrieve, no comm duplication | `retrieve_success=true`, `retrieve_success_2=true`, comm counts equal after create and after 2nd retrieve |
| Single cancel + getBooking proof | `cancellation_success=true`, `cancellation_classification=CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED`, `segment_count_after_cancel=0` |
| Local reconciliation | `closure_verification.booking_status=cancelled`, `supplier_booking_status=cancelled`, `cancelled_at_populated=true`, `reconciliation_audit_count=1`, `cancellation_status_log_count=1`, `cancellation_comm_count=1` |
| Idempotent reconciliation | `reconciliation_already_reconciled_on_second_run=true` |
| No duplicate audit/comms/attempts on re-run | unchanged counts in `closure_verification` |

## Post-run artifact
Copy `output_json_path` from CLI (under `storage/app/private/sabre-gds-scenario-runs/{run_id}.json`). Fill the final report table below from that JSON.

## Tests executed (local)
```bash
php artisan test --filter="SabreGdsLifecycleClosurePhaseTest|SabreGdsLiveScenarioRunnerTest"
```
**Result:** 35 passed, 174 assertions

## Assertion counts
- Lifecycle reconciliation idempotency: covered in `SabreGdsLifecycleClosurePhaseTest`
- Scenario runner regression: 35 tests unchanged pass after closure enhancements

## Production run status
**PENDING** — not executed from local agent environment (no production SSH key / inactive local Sabre connection). Operator must run Step 1 on pilot server and paste `output_json_path` JSON into the final report section.

## Final report template (fill after production run)

| Field | Value |
|---|---|
| scenario run ID | |
| booking ID / reference | |
| PNR / airline locator | |
| selected_total / revalidated_total | |
| revalidation evidence | |
| create HTTP status | |
| retrieve #1 / #2 success | |
| cancel HTTP / classification | |
| segments before / after cancel | |
| supplier-booking-created comm count + statuses | |
| cancellation comm count + statuses | |
| reconciliation audit / status-log counts | |
| create_pnr / cancel_booking / ticket attempts | |
| ticketing invoked | **must be false** |
| PNR creates / cancel mutations | **must be 1 / 1** |

## Known limitations
- Plan/book modes still use lowest fare pick; alternate QR routings require plan probe first
- `sabre:sync-pnr-itinerary` remains local/testing-only; production retrieve uses in-runner `SabrePnrItinerarySyncService`
- Communication `failed` is acceptable under sync SMTP when mail transport errors; `queued` without a worker is not

## Risks
- Low: second retrieve adds one extra read-only getBooking per acceptance run
- Low: second reconciliation pass is local-only (no supplier HTTP)

## Rollback
Revert:
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `app/Console/Commands/SabreGdsLiveScenarioRunnerCommand.php`

No schema rollback. Production PNR (if created) must be cancelled before rollback if still active.

## Commit SHA
*(pending user commit)*

## Final status
**PASS (automated tests). PRODUCTION ACCEPTANCE PENDING operator execution on pilot server.**
