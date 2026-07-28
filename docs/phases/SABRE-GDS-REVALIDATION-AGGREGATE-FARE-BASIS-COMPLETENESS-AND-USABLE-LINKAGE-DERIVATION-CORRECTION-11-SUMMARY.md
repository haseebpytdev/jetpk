# SABRE-GDS-REVALIDATION-AGGREGATE-FARE-BASIS-COMPLETENESS-AND-USABLE-LINKAGE-DERIVATION-CORRECTION-11

## Objective
Align aggregate booleans (`fare_basis_complete`, `usable_fare_linkage`, scoped completeness) with authoritative linkage diagnostics and canonical fare-basis evidence.

## Root cause
- `SabreGdsRevalidationSanitizedOutcomeContract` derived `fare_basis_complete` from stale `linkage_digest.per_segment_fare_basis_complete` and `usable_fare_linkage` from linker’s narrow `uniqueUsable === 1` flag.
- `SabreBookingService` required raw `linkageDigest.per_segment_fare_basis_complete` after linker had already proven compatibility, forcing `unusable_linkage` with contradictory detailed counts (probe `804b57cc`).
- Linkage fixture replay omitted `postResponseDiagnostics` and aggregate enrichment.

## Correction
- Introduced `SabreGdsRevalidationLinkageAggregateContract` as single authoritative aggregate derivation.
- Scoped booleans emitted from `postResponseDiagnostics`.
- Booking gates and sanitized wrap use aggregates; stale failure classes cleared when aggregates show usable linkage.
- Diagnostic linkage replay runs post-response canonical + aggregates.

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationLinkageAggregateContract.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`
- `tests/Unit/SabreGdsRevalidationAggregateFareBasisCompletenessAndUsableLinkageDerivationCorrectionPhaseTest.php` (new)

## Final status
Implementation complete locally; commit pending review.
