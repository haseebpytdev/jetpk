# Phase SABRE-LIVE-SCENARIO-REVALIDATION-DIAGNOSTIC-AND-OUTCOME-MAPPING-CLOSURE-1 — Summary

## Phase name
SABRE-LIVE-SCENARIO-REVALIDATION-DIAGNOSTIC-AND-OUTCOME-MAPPING-CLOSURE-1

## Branch name
*(set when committing — not committed in this pass)*

## Objective
Close observability loss when scenario revalidation fails by preserving sanitized service outcomes, mapping fixed scenario reason codes, correlating normalizer warnings safely, and exposing read-only diagnostic replay — without PNR/booking mutation.

## Included scope
- Sanitized revalidation outcome contract on all `runRevalidationBeforeBooking()` return paths
- `SabreGdsLiveScenarioRevalidationOutcomeMapper` with fixed scenario reason codes
- Rich scenario evidence + failure slice persistence in runner JSON
- Per-run search/revalidation correlation IDs
- Normalizer safe log correlation fields (`scenario_search_correlation_id`, `offer_id_hash`)
- Read-only `sabre:gds-scenario-revalidation-diagnostic` command
- Unit + feature tests for mapping, correlation, safety, and no-mutation guarantees

## Excluded scope
- No live book-mode scenario runs during implementation/verification
- No PNR create, cancel, ticketing, or supplier revalidation resend
- No generic migrations
- JetPK OTP patch unchanged
- Fail-closed revalidation behavior preserved

## Investigation findings

### Production run `952d8cfe-793f-48d2-a535-ca923a67311e`
- Exact-offer linkage passed; `selected_total=520.83 USD`
- `revalidation_attempted=true`, `revalidation_success=false`, `freshness_satisfied=false`
- No Booking / SupplierBooking / SupplierBookingAttempt mutation (correct fail-closed)
- Persisted artifact only had sparse fields + `error=scenario_revalidation_failed`
- Production logs at same timestamp contain `sabre.normalizer.offer_rejected` / `route_continuity_failed` — **not proven** as selected-offer revalidation root cause (search-phase rejections for other offers)

### Root cause of observability loss
1. **`SabreGdsLiveScenarioRevalidationGate::buildEvidenceFromOutcome()`** mapped only attempt/success/totals/fingerprint and **dropped** HTTP status, endpoint, style, supplier call flags, response structure, failure class, and retry safety from the underlying service outcome.
2. **`SabreGdsLiveScenarioRunner`** failure slice manually copied a short field list and omitted `revalidation_reason_code`, diagnostics, and supplier transport fields.
3. **Inconsistent failure shapes** — transport/HTTP paths omitted `revalidation_failure_class` and sanitized contract fields that gatekeeper/GIR paths had.
4. **`SabreGdsRevalidationService::enrichOutcome()`** referenced non-existent `response_structure.itinerary_count`, which could incorrectly flip successes when `candidate_count` was the real signal (fixed to use `response_candidate_count` / `candidate_count` with usable-linkage guard).

### Underlying outcome fields now available (sanitized)
`revalidation_attempted`, `supplier_call_attempted`, `supplier_response_received`, `success`, `reason_code`, `safe_error_code`, `failure_category`, `http_status`, `endpoint_path`, `operation`, `revalidation_style`, `duration_ms`, `transport_error_category`, `exception_class_category`, `response_json_valid`, `response_empty`, `response_top_level_keys`, `response_candidate_count`, `grouped_itinerary_errors_present`, `application_errors_present`, `application_warnings_present`, `fare_basis_complete`, `usable_fare_linkage`, `fare_comparison`, `offer_unavailable`, `retry_safe`, `response_structure_summary`, `revalidation_correlation_id`

## Exact safe mapping added

### Scenario reason codes
| Underlying signal | Scenario code |
|---|---|
| Transport (non-timeout) | `scenario_revalidation_transport_failure` |
| Timeout | `scenario_revalidation_timeout` |
| HTTP 4xx/5xx | `scenario_revalidation_http_rejected` |
| GIR / MIP errors | `scenario_revalidation_grouped_itinerary_error` |
| HTTP 200 application errors/warnings | `scenario_revalidation_supplier_application_error` |
| Empty/unmapped 200 body | `scenario_revalidation_response_mapping_failed` |
| MIP 5053 / offer unavailable | `scenario_revalidation_offer_unavailable` |
| Fare basis incomplete | `scenario_revalidation_fare_basis_incomplete` |
| Unusable linkage | `scenario_revalidation_fare_linkage_missing` |
| Price mismatch | `scenario_revalidation_price_changed` |
| Currency mismatch | `scenario_revalidation_currency_changed` |
| Pre-call draft/linkage/unavailable | `scenario_revalidation_unsupported_context` / `scenario_revalidation_fare_linkage_missing` |
| Uncaught exception | `scenario_revalidation_internal_exception` |
| Fallback | `scenario_revalidation_failed` |

### Normalizer correlation (no timestamp inference)
- `selected_offer` — matching `revalidation_correlation_id` or segment signature hash
- `unrelated_offer_same_response` — same search correlation, different segment/route signature
- `separate_search` — different `scenario_search_correlation_id`
- `unknown_not_correlated` — default; production `route_continuity_failed` warnings remain **unproven** for selected offer

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsScenarioCorrelationRegistry.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationGate.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioOfferCatalog.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php` *(new)*
- `app/Console/Commands/SabreGdsLiveScenarioRunnerCommand.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapperTest.php` *(new)*
- `tests/Feature/SabreGdsScenarioRevalidationDiagnosticPhaseTest.php` *(new)*

## Routes changed
None

## Database changes
None

## Backend changes
- All revalidation HTTP outcomes normalized through sanitized contract wrapper
- Scenario gate generates `revalidation_correlation_id` before supplier call; logs safe correlation context
- Search starts `scenario_search_correlation_id` for normalizer log correlation
- Scenario failure JSON includes `revalidation_diagnostics`, reason codes, HTTP/transport fields

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreGdsLiveScenarioRevalidationOutcomeMapperTest|SabreGdsScenarioRevalidationDiagnosticPhaseTest"
php artisan test --filter="SabreGdsLifecycleClosurePhaseTest|SabreGdsLiveScenarioRunnerTest|SabreGdsLiveScenarioPlanExactOfferEvidenceTest"
```
**New tests:** 19 passed (45 assertions) in mapper/diagnostic suite  
**Regression suite:** 59/60 passed in combined run before fix; **60/60** after `response_structure_summary` derivation fix

### Assertion coverage (new)
- Transport / timeout / HTTP 4xx/5xx mapping
- HTTP 200 GIR, application errors, fare basis incomplete, unusable linkage, valid linkage
- Price / currency change
- Selected vs unrelated route-continuity correlation
- Raw token/PII exclusion from scenario evidence
- Diagnostic command fixture replay + stored run inspection
- No Booking/SupplierBooking/Attempt mutation in stub-gate check

## Production verification (after deploy)

**Do not run book mode.**

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php -l app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php
php artisan sabre:gds-scenario-revalidation-diagnostic --run-id=952d8cfe-793f-48d2-a535-ca923a67311e
```

Confirm:
- Mapper replays stored sparse run (legacy note + derived reason code)
- Production DB booking/attempt counts unchanged
- No supplier HTTP from diagnostic command

## Is another supplier revalidation call necessary?
**No** for observability closure. This phase restores diagnostics on future runs and allows read-only replay/inspection. The production failure for run `952d8cfe` cannot be re-derived with full HTTP detail from the sparse stored JSON alone; a **future explicitly authorized revalidation-only probe** (no PNR) may be proposed separately if operators need live reconfirmation.

## Known limitations
- Legacy runs stored before deploy retain sparse fields; diagnostic command replays with `note=legacy_run_replayed_with_sparse_outcome`
- Normalizer correlation cannot prove selected-offer `route_continuity_failed` without segment-signature match in logs

## Risks
- Low: additional JSON fields in scenario output (still passed through `assertOutputSafe`)

## Rollback instructions
Revert changed files listed above; clear config/cache; no DB rollback required.

## Commit SHA
*(pending user commit)*

## Final status
Implementation complete; tests passing; ready for deploy + read-only production inspection.
