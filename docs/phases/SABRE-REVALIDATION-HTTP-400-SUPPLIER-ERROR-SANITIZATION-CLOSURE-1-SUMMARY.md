# SABRE-REVALIDATION-HTTP-400-SUPPLIER-ERROR-SANITIZATION-CLOSURE-1

## Phase name
SABRE-REVALIDATION-HTTP-400-SUPPLIER-ERROR-SANITIZATION-CLOSURE-1

## Branch name
`claude/sabre-revalidation-http-400-supplier-error-sanitization-closure-1` (create from `claude/ui-master` before commit)

## Objective
Preserve safe structured Sabre HTTP 4xx/5xx revalidation supplier rejection details (type, errorCode, message, additionalMessages) in sanitized outcomes, scenario evidence, probe artifacts, and correlated logs — without raw bodies, credentials, PII, or another supplier call.

## Included scope
- New `SabreGdsRevalidationHttpSupplierErrorSanitizer` with bounded redaction and classification hints
- HTTP non-success wiring in `SabreBookingService::runRevalidationBeforeBooking()`
- Contract retry semantics: `automatic_retry_allowed`, `same_payload_retry_recommended`, `retry_idempotency_safe`
- Scenario mapper/evidence/probe artifact enrichment
- Correlated log `sabre.revalidate.http_supplier_error`
- Read-only diagnostic `--sanitizer-fixture` replay with representative HTTP 400 fixture
- Unit + feature tests (56 assertions in targeted suites)

## Excluded scope
- No live Sabre HTTP calls, probe reruns, bookings, PNRs, cancel/ticket/void/refund
- No migration or ticketing/cancellation config changes
- No recovery/reconstruction of historical run `bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1` supplier text (not safely persisted)
- No `--send` production probe execution

## Investigation findings
Controlled probe run `bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1` / revalidation correlation `3253487a-0475-4c4d-bccb-aae6c9d01fd6`:
- Exactly one supplier revalidation call, HTTP 400, valid JSON, `candidate_count=0`, application error/warning flags set, no DB/booking/PNR mutation.

## Root causes
1. **`SabreBookingService::runRevalidationBeforeBooking()` HTTP failure branch** decoded JSON and built `error_digest` via `extractSafeErrorDigest()`, but merged only generic `reason_code` / `revalidation_failure_class=http_rejected` into the wrapped outcome. Top-level `type`, `errorCode`, `message`, and `additionalMessages` were never copied into sanitized outcome fields.
2. **`SabreGdsRevalidationSanitizedOutcomeContract::wrap()`** consumed `error_digest` only for boolean flags (`application_errors_present`, etc.), not structured supplier fields.
3. **`SabreGdsLiveScenarioRevalidationOutcomeMapper::classifyScenarioReasonCode()`** returned `scenario_revalidation_http_rejected` for all HTTP ≥400 before inspecting supplier codes/messages.
4. **`SabreGdsLiveRevalidationOnlyProbe::richOutcomeSlice()`** omitted supplier error fields from persisted artifacts.
5. **Logging** used sparse `sabre.revalidate.http_failed` without correlation-rich supplier summary.

### Fields available in raw decoded response (not previously persisted)
Per probe evidence: `status`, `timeStamp`, `server`, `type`, `errorCode`, `message`, `additionalMessages` (plus response structure metadata). `extractSafeErrorDigest()` partially captured codes/messages internally but `additionalMessages` array was not surfaced.

## Safe sanitization added
New sanitizer produces when available:
- `supplier_error_type`, `supplier_error_code`, `supplier_error_message_safe`
- `supplier_additional_messages_summary`, `supplier_additional_message_codes`
- `supplier_validation_paths`, `supplier_error_count`, `supplier_warning_count`
- `supplier_http_failure_classification` (maps to scenario reason when supported)
- `automatic_retry_allowed=false`, `same_payload_retry_recommended=false`
- `retry_idempotency_safe` (separate from `retry_safe`)

Redaction: tokens, credentials, emails, passport/document patterns, passenger names, opaque IDs; bounded message/array/depth lengths.

## Classification behavior
Supported when supplier code/message/path evidence supports it:
- `scenario_revalidation_request_validation_failed`
- `scenario_revalidation_schema_rejected`
- `scenario_revalidation_endpoint_style_mismatch`
- `scenario_revalidation_invalid_reference_linkage`
- `scenario_revalidation_unsupported_element`
- Fallback: `scenario_revalidation_http_rejected`

## Exact files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationHttpSupplierErrorSanitizer.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`
- `tests/fixtures/sabre/revalidation/http-400-supplier-error-representative.json` (new)
- `tests/Unit/Support/Sabre/Revalidation/SabreGdsRevalidationHttpSupplierErrorSanitizerTest.php` (new)
- `tests/Feature/SabreGdsScenarioRevalidationDiagnosticPhaseTest.php`

## Routes changed
None.

## Database changes
None.

## Backend changes
Sanitizer + contract/mapper/probe enrichment; correlated log emitter; optional `correlatedLogContext` on revalidation draft path for probe run/search IDs.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter="SabreGdsRevalidationHttpSupplierErrorSanitizerTest|SabreGdsLiveScenarioRevalidationOutcomeMapperTest|SabreGdsScenarioRevalidationDiagnosticPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest"
```
Result: **56 passed**, 236 assertions.

Coverage includes: HTTP 400 type/code/message, additionalMessages, validation paths, token/credential/PII redaction, bounds, malformed/non-JSON bodies, supported vs generic classification, retry flags, correlated log shape, diagnostic sanitizer replay, probe regression suite.

## Assertion counts
- Sanitizer unit: 15 tests
- Targeted suites total: 56 tests / 236 assertions

## Screenshots
N/A (backend/logging only).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Historical probe artifact for run `bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1` cannot be enriched retroactively; use `--sanitizer-fixture=representative` to prove logic locally.
- Classification heuristics are conservative; unsupported patterns remain `scenario_revalidation_http_rejected`.

## Risks
- Low: broader diagnostic payloads in probe artifacts/logs (still redacted/bounded).

## Rollback instructions
Revert the files listed above; redeploy prior versions. No DB rollback required.

## Commit SHA
Pending user commit.

## Final status
Implementation and targeted tests **PASS**. Production verification: read-only fixture replay only; do not execute `--send`.

## Production verification (read-only)
```bash
php -l app/Support/Sabre/Revalidation/SabreGdsRevalidationHttpSupplierErrorSanitizer.php
php artisan sabre:gds-scenario-revalidation-diagnostic --sanitizer-fixture=representative
php artisan sabre:gds-scenario-revalidation-diagnostic --run-id=bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1  # inspect only; expect sparse legacy fields
```
Do **not** run `sabre:gds-live-revalidation-only-probe --send`.

## Files to upload (SFTP)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationHttpSupplierErrorSanitizer.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`

## Server SSH clears
```bash
php artisan optimize:clear
```

## Historical 400 reason recovery
**No** — the exact supplier `type` / `errorCode` / `message` / `additionalMessages` for run `bb9c6e5c-ed67-45bb-bd6b-34b3cfee65a1` were not safely persisted in the artifact and cannot be recovered without another supplier call (explicitly prohibited).
