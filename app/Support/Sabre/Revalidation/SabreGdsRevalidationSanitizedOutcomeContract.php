<?php

namespace App\Support\Sabre\Revalidation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Normalizes Sabre revalidation HTTP outcomes into a consistent sanitized diagnostic contract.
 * Never includes raw supplier bodies, tokens, credentials, or passenger/contact data.
 */
final class SabreGdsRevalidationSanitizedOutcomeContract
{
    public const OPERATION_REVALIDATE_BEFORE_BOOKING = 'revalidate_before_booking';

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public static function wrap(
        array $outcome,
        bool $supplierCallAttempted,
        bool $supplierResponseReceived,
        ?Throwable $transportException = null,
        ?string $correlationId = null,
        string $operation = self::OPERATION_REVALIDATE_BEFORE_BOOKING,
    ): array {
        $responseStructure = is_array($outcome['response_structure'] ?? null) ? $outcome['response_structure'] : [];
        $errorDigest = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $linkageDigest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];
        $failureClass = trim((string) (
            $outcome['revalidation_failure_class']
            ?? $errorDigest['revalidation_failure_class']
            ?? ''
        ));

        $jsonValid = ($responseStructure['json_valid'] ?? 'false') === 'true';
        $emptyBody = ($responseStructure['empty_body'] ?? 'false') === 'true';
        $candidateCount = (int) ($responseStructure['candidate_count'] ?? 0);
        $topLevelKeys = self::parseCommaSeparatedKeys((string) ($responseStructure['top_level_keys'] ?? ''));

        $groupedItineraryPresent = self::groupedItineraryErrorsPresent($failureClass, $errorDigest);
        $applicationDiagnostics = is_array($outcome['application_message_diagnostics'] ?? null)
            ? $outcome['application_message_diagnostics']
            : [];
        $linkageDiagnostics = is_array($outcome['response_linkage_diagnostics'] ?? null)
            ? $outcome['response_linkage_diagnostics']
            : [];
        $applicationWarningsPresent = ($applicationDiagnostics['application_warnings_present'] ?? null) === true
            || $failureClass === 'application_warning'
            || $failureClass === 'application_informational'
            || self::digestHasWarningCodes($errorDigest);
        $applicationErrorsPresent = ($applicationDiagnostics['application_errors_present'] ?? null) === true
            || self::applicationErrorsPresent($failureClass, $errorDigest, $applicationWarningsPresent);
        $blockingApplicationErrorPresent = ($applicationDiagnostics['blocking_application_error_present'] ?? false) === true
            || $failureClass === 'application_error';
        $blockingApplicationWarningPresent = ($applicationDiagnostics['blocking_application_warning_present'] ?? false) === true
            || $failureClass === 'application_warning';
        $informationalWarningPresent = ($applicationDiagnostics['informational_warning_present'] ?? false) === true
            || $failureClass === 'application_informational';

        $canonicalNormalization = is_array($linkageDiagnostics['canonical_linkage_normalization'] ?? null)
            ? $linkageDiagnostics['canonical_linkage_normalization']
            : (is_array($outcome['canonical_linkage_normalization'] ?? null)
                ? $outcome['canonical_linkage_normalization']
                : (is_array($outcome[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY] ?? null)
                    ? $outcome[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY]
                    : []));

        $priorStale = [
            'fare_basis_complete' => $outcome['fare_basis_complete'] ?? null,
            'usable_fare_linkage' => $outcome['usable_fare_linkage'] ?? null,
            'failure_category' => $failureClass !== '' ? $failureClass : null,
            'linkage_digest_per_segment_fare_basis_complete' => $linkageDigest['per_segment_fare_basis_complete'] ?? null,
        ];

        $aggregates = app(SabreGdsRevalidationLinkageAggregateContract::class)->normalize(
            $linkageDiagnostics,
            $canonicalNormalization,
            $priorStale,
        );

        $fareBasisComplete = $aggregates['fare_basis_complete'];
        $usableFareLinkage = $aggregates['usable_fare_linkage'];
        $pricingComplete = $aggregates['pricing_complete'];

        if ($linkageDiagnostics === [] && ($outcome['success'] ?? false) === true) {
            $legacyFareBasisComplete = ($linkageDigest['per_segment_fare_basis_complete'] ?? false) === true;
            $legacyUsable = $legacyFareBasisComplete
                && ($linkageDigest['has_revalidated_fare'] ?? false) === true
                && ($linkageDigest['has_revalidated_currency'] ?? false) === true;
            if ($legacyUsable) {
                $fareBasisComplete = true;
                $usableFareLinkage = true;
                $pricingComplete = true;
            }
        }

        if ($failureClass === 'fare_basis_incomplete' && $fareBasisComplete === true) {
            $linkageReason = trim((string) ($linkageDiagnostics['linkage_failure_reason_code'] ?? ''));
            $failureClass = $linkageReason !== '' ? $linkageReason : 'unusable_linkage';
        }

        if ($usableFareLinkage && in_array($failureClass, ['unusable_linkage', 'fare_basis_incomplete', 'pricing_tripwire'], true)) {
            $failureClass = '';
        }

        $hasRevalidatedFare = ($linkageDigest['has_revalidated_fare'] ?? false) === true
            || $pricingComplete === true;
        $hasRevalidatedCurrency = ($linkageDigest['has_revalidated_currency'] ?? false) === true
            || $pricingComplete === true;

        $offerUnavailable = in_array($failureClass, ['mip_5053', 'offer_unavailable', 'gir_message'], true)
            || in_array('mip_5053', $errorDigest['response_error_codes'] ?? [], true);

        $outcome['revalidation_attempted'] = array_key_exists('revalidation_attempted', $outcome)
            ? ($outcome['revalidation_attempted'] === true)
            : $supplierCallAttempted;
        $outcome['supplier_call_attempted'] = $supplierCallAttempted;
        $outcome['supplier_response_received'] = $supplierResponseReceived;
        $outcome['operation'] = $operation;
        $outcome['revalidation_style'] = (string) ($outcome['payload_style'] ?? '');
        $outcome['safe_error_code'] = (string) ($outcome['reason_code'] ?? '');
        $outcome['failure_category'] = $failureClass !== '' ? $failureClass : null;
        $outcome['response_json_valid'] = $jsonValid;
        $outcome['response_empty'] = $emptyBody;
        $outcome['response_top_level_keys'] = $topLevelKeys;
        $outcome['response_candidate_count'] = $candidateCount;
        $outcome['grouped_itinerary_errors_present'] = $groupedItineraryPresent;
        $outcome['application_errors_present'] = $applicationErrorsPresent;
        $outcome['application_warnings_present'] = $applicationWarningsPresent;
        $outcome['blocking_application_error_present'] = $blockingApplicationErrorPresent;
        $outcome['blocking_application_warning_present'] = $blockingApplicationWarningPresent;
        $outcome['informational_warning_present'] = $informationalWarningPresent;
        $outcome['application_error_count'] = $applicationDiagnostics['application_error_count'] ?? null;
        $outcome['application_warning_count'] = $applicationDiagnostics['application_warning_count'] ?? null;
        $outcome['application_message_categories'] = $applicationDiagnostics['application_message_categories'] ?? null;
        $outcome['application_message_codes'] = $applicationDiagnostics['application_message_codes'] ?? null;
        $outcome['application_message_severity_types'] = $applicationDiagnostics['application_message_severity_types'] ?? null;
        $outcome['response_statistics_present'] = $applicationDiagnostics['response_statistics_present'] ?? null;
        $outcome['response_messages_present'] = $applicationDiagnostics['response_messages_present'] ?? null;
        $outcome['response_message_locations'] = $applicationDiagnostics['response_message_locations'] ?? null;
        $outcome['supplier_response_success_indicator_present'] = $applicationDiagnostics['supplier_response_success_indicator_present'] ?? null;
        $outcome['supplier_response_success_indicator_state'] = $applicationDiagnostics['supplier_response_success_indicator_state'] ?? null;
        $outcome['response_linkage_diagnostics'] = $linkageDiagnostics !== [] ? $linkageDiagnostics : null;
        $outcome['linkage_failure_reason_code'] = $linkageDiagnostics['linkage_failure_reason_code'] ?? null;
        $outcome['selected_response_candidate_ordinal'] = $linkageDiagnostics['selected_response_candidate_ordinal'] ?? null;
        $outcome['pricing_complete'] = $pricingComplete;
        $outcome['fare_basis_complete'] = $fareBasisComplete;
        $outcome['usable_fare_linkage'] = $usableFareLinkage;
        $outcome['selected_fare_basis_complete'] = $aggregates['selected_fare_basis_complete'];
        $outcome['draft_fare_basis_complete'] = $aggregates['draft_fare_basis_complete'];
        $outcome['candidate_fare_basis_complete'] = $aggregates['candidate_fare_basis_complete'];
        $outcome['overall_fare_basis_complete'] = $aggregates['overall_fare_basis_complete'];
        $outcome['linkage_aggregate_derivation'] = array_filter([
            'aggregate_derivation_inputs' => $aggregates['aggregate_derivation_inputs'],
            'aggregate_derivation_predicate' => $aggregates['aggregate_derivation_predicate'],
            'aggregate_derivation_source' => $aggregates['aggregate_derivation_source'],
            'prior_stale_values' => $aggregates['prior_stale_values'],
        ], static fn ($value) => $value !== null && $value !== []);
        $outcome['offer_unavailable'] = $offerUnavailable;
        $outcome['response_structure_summary'] = self::buildResponseStructureSummary($responseStructure);
        $outcome['retry_safe'] = self::computeRetrySafe(
            $outcome,
            $supplierCallAttempted,
            $transportException,
            $failureClass,
        );
        $outcome['retry_idempotency_safe'] = self::computeRetryIdempotencySafe(
            $outcome,
            $supplierCallAttempted,
            $transportException,
            $failureClass,
        );

        $httpStatus = $outcome['http_status'] ?? null;
        if (is_int($httpStatus) && $httpStatus >= 400) {
            $outcome['automatic_retry_allowed'] = ($outcome['automatic_retry_allowed'] ?? false) === true;
            $outcome['same_payload_retry_recommended'] = ($outcome['same_payload_retry_recommended'] ?? false) === true;
            if (! array_key_exists('supplier_response_received', $outcome) || $outcome['supplier_response_received'] === null) {
                $outcome['supplier_response_received'] = $supplierResponseReceived;
            }
            if (! array_key_exists('response_json_valid', $outcome)) {
                $outcome['response_json_valid'] = $jsonValid;
            }
        }

        if ($transportException !== null) {
            $outcome['transport_error_category'] = self::classifyTransportError($transportException);
            $outcome['exception_class_category'] = self::classifyExceptionCategory($transportException);
            $outcome['block_reason'] = (string) ($outcome['message'] ?? 'transport_failure');
        }

        if ($correlationId !== null && trim($correlationId) !== '') {
            $outcome['revalidation_correlation_id'] = trim($correlationId);
        }

        return array_filter($outcome, static fn ($value) => $value !== null);
    }

    /**
     * @return list<string>
     */
    public static function parseCommaSeparatedKeys(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @param  array<string, mixed>  $responseStructure
     * @return array<string, mixed>
     */
    public static function buildResponseStructureSummary(array $responseStructure): array
    {
        return array_filter([
            'json_valid' => ($responseStructure['json_valid'] ?? 'false') === 'true',
            'empty_body' => ($responseStructure['empty_body'] ?? 'false') === 'true',
            'top_level_keys' => self::parseCommaSeparatedKeys((string) ($responseStructure['top_level_keys'] ?? '')),
            'candidate_count' => (int) ($responseStructure['candidate_count'] ?? 0),
            'candidate_fields_present' => trim((string) ($responseStructure['candidate_fields'] ?? '')) !== '',
        ], static fn ($value) => $value !== null && $value !== [] && $value !== false);
    }

    public static function classifyTransportError(Throwable $exception): string
    {
        if (self::isTimeoutException($exception)) {
            return 'timeout';
        }

        if ($exception instanceof ConnectionException) {
            return 'connection';
        }

        if ($exception instanceof RequestException) {
            return 'http_client';
        }

        return 'transport';
    }

    public static function classifyExceptionCategory(Throwable $exception): string
    {
        $class = $exception::class;
        if (str_contains($class, 'Timeout')) {
            return 'timeout';
        }
        if (str_contains($class, 'Connection')) {
            return 'connection';
        }

        return 'internal_exception';
    }

    /**
     * @param  array<string, mixed>  $errorDigest
     */
    protected static function groupedItineraryErrorsPresent(string $failureClass, array $errorDigest): bool
    {
        if (in_array($failureClass, ['gir_message', 'mip_5053'], true)) {
            return true;
        }

        foreach ($errorDigest['response_error_codes'] ?? [] as $code) {
            if (is_string($code) && str_starts_with(strtoupper($code), 'MIP')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $errorDigest
     */
    protected static function applicationErrorsPresent(
        string $failureClass,
        array $errorDigest,
        bool $applicationWarningsPresent,
    ): bool {
        if ($failureClass === 'application_warning' || $failureClass === 'application_error') {
            return $applicationWarningsPresent;
        }
        if ($failureClass === 'application_informational') {
            return false;
        }

        $codes = $errorDigest['response_error_codes'] ?? [];
        foreach ($codes as $code) {
            if (! is_string($code)) {
                continue;
            }
            if (in_array($code, ['gatekeeper_failed', 'fare_basis_incomplete', 'pricing_tripwire'], true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $errorDigest
     */
    protected static function digestHasWarningCodes(array $errorDigest): bool
    {
        return ($errorDigest['response_error_codes'] ?? []) !== []
            || ($errorDigest['response_error_messages'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected static function computeRetryIdempotencySafe(
        array $outcome,
        bool $supplierCallAttempted,
        ?Throwable $transportException,
        string $failureClass,
    ): bool {
        return self::computeRetrySafe($outcome, $supplierCallAttempted, $transportException, $failureClass);
    }

    protected static function computeRetrySafe(
        array $outcome,
        bool $supplierCallAttempted,
        ?Throwable $transportException,
        string $failureClass,
    ): bool {
        if (($outcome['success'] ?? false) === true) {
            return false;
        }

        if (! $supplierCallAttempted) {
            return true;
        }

        if ($transportException !== null && self::isTimeoutException($transportException)) {
            return true;
        }

        if (in_array($failureClass, ['gatekeeper_failed', 'fare_basis_incomplete', 'unusable_linkage', 'pricing_tripwire'], true)) {
            return false;
        }

        if (($outcome['offer_unavailable'] ?? false) === true) {
            return false;
        }

        $http = $outcome['http_status'] ?? null;
        if (is_int($http) && $http >= 500) {
            return true;
        }

        return ($outcome['success'] ?? false) !== true;
    }

    protected static function isTimeoutException(Throwable $exception): bool
    {
        $class = strtolower($exception::class);
        $message = strtolower($exception->getMessage());

        return str_contains($class, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout');
    }
}
