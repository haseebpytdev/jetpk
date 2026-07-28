# SABRE-REVALIDATION-BFM-APPLICATION-WARNING-AND-RESPONSE-LINKAGE-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-APPLICATION-WARNING-AND-RESPONSE-LINKAGE-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-application-warning-and-response-linkage-correction-1` (create from `claude/ui-master` before commit)

## Objective
Fix HTTP 200 BFM revalidation failures caused by treating informational application/statistics messages as blocking errors and by selecting fare linkage from the first grouped-itinerary candidate instead of the uniquely matching exact offer.

## Production reference
- `run_id=25997adc-680d-430a-a047-d8fb64cf9dad`
- HTTP 200, 31 candidates, `fare_basis_complete=true`
- Prior failure: `scenario_revalidation_supplier_application_error` / `application_warning`, `usable_fare_linkage=false`, `revalidated_total=null`

## Investigation findings

### Warning/error extraction methods
| Flag / artifact | Method |
|---|---|
| `application_errors_present` | `SabreGdsRevalidationApplicationMessageDiagnostics::analyze()` |
| `application_warnings_present` | `SabreGdsRevalidationApplicationMessageDiagnostics::analyze()` |
| `blocking_application_error_present` | `SabreGdsRevalidationApplicationMessageDiagnostics::analyze()` |
| `blocking_application_warning_present` | `SabreGdsRevalidationApplicationMessageDiagnostics::analyze()` |
| `informational_warning_present` | `SabreGdsRevalidationApplicationMessageDiagnostics::analyze()` |
| Legacy digest adapter | `SabreRevalidationPayloadBuilder::extractHttp200ApplicationWarningDigest()` |
| Blocking gate (pre-linkage) | `SabreGdsRevalidationApplicationMessageDiagnostics::hasBlockingMessages()` via `SabreBookingService::runRevalidationBeforeBooking()` |
| GIR fatal offer-unavailable | `SabreRevalidationPayloadBuilder::evaluateGroupedItineraryMessages()` (unchanged; MIP5053 etc.) |

### Why HTTP 200 was classified as failure
1. **Application warning gate (primary):** `SabreBookingService::runRevalidationBeforeBooking()` called `http200ApplicationWarningDigestNonEmpty()` on any HTTP 200 message/statistics node and returned `application_warning` before linkage/pricing could succeed.
2. **Over-broad error contract:** `SabreGdsRevalidationSanitizedOutcomeContract` treated `application_warnings_present` as `application_errors_present` when `failure_class=application_warning`.
3. **Scenario mapper:** `SabreGdsLiveScenarioRevalidationOutcomeMapper::classifyScenarioReasonCode()` mapped any `application_warnings_present` to `scenario_revalidation_supplier_application_error`.
4. **Linkage selection (secondary):** `SabreRevalidationPayloadBuilder::selectGroupedItineraryForRevalidation()` preferred `currentItinerary` or **first itinerary**, not exact-offer match — so `usable_fare_linkage=false` even with 31 candidates.
5. **Pricing extraction gap:** `SabreGdsRevalidationService::enrichOutcome()` read `revalidated_fare_total` keys while linkage exposed `revalidated_total`, yielding `revalidated_total=null` in artifacts.

### Linkage method and prior failure point
- Candidate enumeration: `SabreGdsRevalidationResponseCandidateLinker::enumerateCandidates()`
- Exact-offer comparison: `SabreGdsRevalidationResponseCandidateLinker::analyze()` / `evaluateCandidate()`
- Selected candidate linkage extraction: `SabreGdsRevalidationResponseCandidateLinker::extractLinkageForSelectedCandidate()` → `SabreRevalidationPayloadBuilder::extractFareLinkage($json, $selectedItinerary)`
- Prior failure point: informational statistics/message present → early `application_warning` return **before** unique candidate linkage and pricing extraction.

### Were warnings informational or blocking?
For run `25997adc-680d-430a-a047-d8fb64cf9dad`, evidence indicates **informational statistics/diagnostic messages** on HTTP 200 (31 candidates, complete fare basis, no supplier HTTP error fields). The old path treated **any** warning/message digest as blocking.

## Included scope
- Evidence-based application-message classification (blocking vs informational vs statistics)
- Conservative exact-offer candidate linkage (unique match only; no index-0 / cheapest selection)
- Safe response-linkage and application-message diagnostics on outcomes/probe artifacts
- Pricing extraction fix (`revalidated_total` / `revalidated_currency`, fare delta fields)
- Read-only linkage replay diagnostic (`--linkage-fixture`)
- Unit/feature tests + sanitized replay fixture

## Excluded scope
- No live Sabre calls during implementation
- No rerun of production `run_id`
- No Booking/PNR/cancel/ticket/void/refund/communication mutation
- No migration / automatic retry / payload-style fallback
- No raw payload/response/transaction ID exposure

## Root causes
1. Informational BFM statistics/messages were fail-closed as `application_warning`.
2. Fare linkage used first/current grouped itinerary instead of exact selected-offer match.
3. Pricing fields were not propagated consistently into `fare_comparison`.

## Exact files changed
### App
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationApplicationMessageDiagnostics.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php` (new)
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`

### Tests / fixtures / docs
- `tests/Unit/SabreRevalidationBfmApplicationWarningAndResponseLinkageCorrectionPhaseTest.php` (new)
- `tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json` (new)
- `tests/Feature/SabreBookingRevalidatePhaseB13Test.php`
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapperTest.php`
- `docs/phases/SABRE-REVALIDATION-BFM-APPLICATION-WARNING-AND-RESPONSE-LINKAGE-CORRECTION-1-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Blocking-only application-message gate on HTTP 200
- Exact-offer candidate linker with aggregate safe diagnostics
- Outcome/probe/diagnostic artifact extensions
- `fare_comparison` pricing field correction

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfm|SabreGdsLiveScenarioRevalidationOutcomeMapperTest|test_run_revalidation_http_200"
```
- **121 passed** (11 new phase tests + regression)

## Assertion counts (new phase test file)
- 11 tests / 37 assertions

## Screenshots
N/A (backend-only phase)

## Responsive verification
N/A

## Accessibility verification
N/A

## Known limitations
- Linkage replay fixture is sanitized structural shape (not raw production body); production replay requires persisted sanitized structure only.
- Candidate linkage depends on draft segment context quality (booking class, fare basis, cabin, schedule resolution via leg/schedule desc tables).

## Risks
- If Sabre emits genuinely blocking warnings with non-standard severity text, classification may need additional stable codes.
- Multi-itinerary-group edge cases with identical signatures remain fail-closed as ambiguous (by design).

## Rollback instructions
Revert the files listed above and redeploy prior build. No migration rollback required.

## Commit SHA
Pending user-requested commit.

## Final status
Implementation complete locally; tests green for targeted regression set. Awaiting deployment authorization for read-only plan/replay verification.

## Files to upload (SFTP)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationApplicationMessageDiagnostics.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`

Post-upload:
```bash
php artisan optimize:clear
```

## Production verification commands (read-only)

### Plan mode (no supplier HTTP)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --plan \
  --connection=<ID> \
  --departure-date=<YYYY-MM-DD> \
  --origin=LHE \
  --destination=JED \
  --passenger-json=/path/to/safe-passenger-fixture.json
```

### Linkage/message replay (no supplier HTTP, no DB mutation)
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic \
  --linkage-fixture=tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json
```

### Stored run inspect (read-only)
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic \
  --run-id=25997adc-680d-430a-a047-d8fb64cf9dad
```

## Is another live revalidation-only call required?
**Not for validating this fix class.** Plan mode + sanitized linkage replay can verify classification/linkage logic without HTTP. One controlled `--send` revalidation-only probe may still be useful after deployment to confirm real supplier statistics/message shapes, but it is **not required** to validate the code path corrected here.
