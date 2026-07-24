<?php

namespace App\Support\Sabre\Scenario;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSignatureRuntimePropagation;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationLinkageAggregateContract;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;

/**
 * Maps sanitized Sabre revalidation outcomes to scenario-safe diagnostics and fixed reason codes.
 */
final class SabreGdsLiveScenarioRevalidationOutcomeMapper
{
    public const REASON_TRANSPORT_FAILURE = 'scenario_revalidation_transport_failure';

    public const REASON_TIMEOUT = 'scenario_revalidation_timeout';

    public const REASON_HTTP_REJECTED = 'scenario_revalidation_http_rejected';

    public const REASON_SCHEMA_REJECTED = 'scenario_revalidation_schema_rejected';

    public const REASON_REQUEST_VALIDATION_FAILED = 'scenario_revalidation_request_validation_failed';

    public const REASON_ENDPOINT_STYLE_MISMATCH = 'scenario_revalidation_endpoint_style_mismatch';

    public const REASON_INVALID_REFERENCE_LINKAGE = 'scenario_revalidation_invalid_reference_linkage';

    public const REASON_UNSUPPORTED_ELEMENT = 'scenario_revalidation_unsupported_element';

    public const REASON_SUPPLIER_APPLICATION_ERROR = 'scenario_revalidation_supplier_application_error';

    public const REASON_GROUPED_ITINERARY_ERROR = 'scenario_revalidation_grouped_itinerary_error';

    public const REASON_RESPONSE_MAPPING_FAILED = 'scenario_revalidation_response_mapping_failed';

    public const REASON_OFFER_UNAVAILABLE = 'scenario_revalidation_offer_unavailable';

    public const REASON_FARE_BASIS_INCOMPLETE = 'scenario_revalidation_fare_basis_incomplete';

    public const REASON_DIAGNOSTICS_INCOMPLETE = 'scenario_revalidation_diagnostics_incomplete';

    public const REASON_FARE_LINKAGE_MISSING = 'scenario_revalidation_fare_linkage_missing';

    public const REASON_PRICE_CHANGED = 'scenario_revalidation_price_changed';

    public const REASON_CURRENCY_CHANGED = 'scenario_revalidation_currency_changed';

    public const REASON_UNSUPPORTED_CONTEXT = 'scenario_revalidation_unsupported_context';

    public const REASON_INTERNAL_EXCEPTION = 'scenario_revalidation_internal_exception';

    public const REASON_FAILED = 'scenario_revalidation_failed';

    public const REASON_SUCCESS = 'scenario_revalidation_success';

    public const CORRELATION_SELECTED_OFFER = 'selected_offer';

    public const CORRELATION_UNRELATED_OFFER_SAME_RESPONSE = 'unrelated_offer_same_response';

    public const CORRELATION_SEPARATE_SEARCH = 'separate_search';

    public const CORRELATION_CONCURRENT_REQUEST = 'concurrent_request';

    public const CORRELATION_UNKNOWN = 'unknown_not_correlated';

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function mapToScenarioEvidence(array $outcome, array $context = []): array
    {
        $selectedTotal = (float) ($context['selected_total'] ?? 0);
        $selectedCurrency = is_string($context['selected_currency'] ?? null)
            ? strtoupper(trim($context['selected_currency']))
            : null;
        $comparison = is_array($outcome['fare_comparison'] ?? null) ? $outcome['fare_comparison'] : [];
        $mismatches = is_array($comparison['mismatches'] ?? null) ? $comparison['mismatches'] : [];
        $fareChanged = in_array('price_change', $mismatches, true);
        $currencyChanged = in_array('currency_change', $mismatches, true);

        $attempted = array_key_exists('revalidation_attempted', $outcome)
            ? ($outcome['revalidation_attempted'] === true)
            : (($outcome['success'] ?? false) === true || array_key_exists('success', $outcome));
        $success = ($outcome['success'] ?? false) === true;
        $freshTotal = isset($comparison['fresh_total']) && is_numeric($comparison['fresh_total'])
            ? (float) $comparison['fresh_total']
            : null;
        $revalidatedCurrency = is_string($comparison['fresh_currency'] ?? null)
            ? strtoupper(trim((string) $comparison['fresh_currency']))
            : (is_string($comparison['currency'] ?? null) ? strtoupper(trim((string) $comparison['currency'])) : $selectedCurrency);

        $scenarioReasonCode = $this->classifyScenarioReasonCode($outcome, $context, $mismatches);
        if ($scenarioReasonCode === self::REASON_SUCCESS) {
            $success = true;
        }
        $freshnessSatisfied = $success && ! $fareChanged && ! $currencyChanged;

        $blockReason = $context['pre_block_reason'] ?? null;
        if ($blockReason === null) {
            if (! $success) {
                $blockReason = $scenarioReasonCode;
            } elseif (! $attempted) {
                $blockReason = null;
            } elseif ($fareChanged) {
                $blockReason = SabreGdsLiveScenarioRevalidationGate::REASON_FARE_CHANGE_REQUIRES_ACCEPTANCE;
            } elseif ($currencyChanged) {
                $blockReason = self::REASON_CURRENCY_CHANGED;
            }
        }

        $diagnostics = $this->buildDiagnostics($outcome);
        $responseStructureSummary = $this->resolveResponseStructureSummary($outcome);
        $linkageFields = $this->extractLinkageEvidenceFields($outcome, $diagnostics);
        $canonicalFields = $this->flattenCanonicalLinkageNormalizationFields($outcome);
        $canonicalBlock = $this->resolveCanonicalLinkageNormalizationDiagnosticsBlock(array_merge($outcome, $canonicalFields));
        if ($canonicalBlock !== []) {
            $canonicalFields[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY] = $canonicalBlock;
        }

        return array_filter([
            'revalidation_attempted' => $attempted,
            'revalidation_success' => $success,
            'freshness_satisfied' => $freshnessSatisfied,
            'selected_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'selected_currency' => $selectedCurrency,
            'revalidated_total' => $freshTotal,
            'revalidated_currency' => $revalidatedCurrency,
            'fare_changed' => $fareChanged,
            'revalidation_at' => $context['revalidation_at'] ?? now()->toIso8601String(),
            'selected_offer_fingerprint' => $context['selected_offer_fingerprint'] ?? null,
            'revalidation_linkage_ready' => ($context['revalidation_linkage_ready'] ?? true) === true,
            'offer_source' => $context['offer_source'] ?? null,
            'shop_captured_at' => $context['shop_captured_at'] ?? null,
            'reason_code' => is_string($outcome['reason_code'] ?? null) ? (string) $outcome['reason_code'] : null,
            'block_reason' => $blockReason ?? ($freshnessSatisfied ? null : SabreGdsLiveScenarioRevalidationGate::REASON_FRESHNESS_NOT_SATISFIED),
            'revalidation_reason_code' => $scenarioReasonCode,
            'revalidation_failure_category' => $this->resolveScenarioFailureCategory($outcome, $scenarioReasonCode),
            'revalidation_http_status' => $outcome['http_status'] ?? null,
            'revalidation_endpoint_path' => $outcome['endpoint_path'] ?? null,
            'supplier_call_attempted' => $this->triStateBool($outcome, 'supplier_call_attempted'),
            'supplier_response_received' => $this->triStateBool($outcome, 'supplier_response_received'),
            'revalidation_style' => $outcome['revalidation_style'] ?? $outcome['payload_style'] ?? null,
            'response_structure_summary' => $responseStructureSummary !== [] ? $responseStructureSummary : null,
            'retry_safe' => ($outcome['retry_safe'] ?? null) === true ? true : (($outcome['retry_safe'] ?? null) === false ? false : null),
            'revalidation_correlation_id' => $outcome['revalidation_correlation_id'] ?? $context['revalidation_correlation_id'] ?? null,
            'selected_segment_signature_hash' => $context['selected_segment_signature_hash'] ?? null,
            'selected_source_identifier_hash' => $context['selected_source_identifier_hash'] ?? null,
            'selected_route' => $context['selected_route'] ?? null,
            'selected_segment_count' => $context['selected_segment_count'] ?? null,
            'revalidation_diagnostics' => $diagnostics,
            'safe_error_code' => $outcome['safe_error_code'] ?? $outcome['reason_code'] ?? null,
            'supplier_error_type' => $outcome['supplier_error_type'] ?? null,
            'supplier_error_code' => $outcome['supplier_error_code'] ?? null,
            'supplier_error_message_safe' => $outcome['supplier_error_message_safe'] ?? null,
            'supplier_additional_messages_summary' => $outcome['supplier_additional_messages_summary'] ?? null,
            'supplier_additional_message_codes' => $outcome['supplier_additional_message_codes'] ?? null,
            'supplier_validation_paths' => $outcome['supplier_validation_paths'] ?? null,
            'supplier_error_count' => $outcome['supplier_error_count'] ?? null,
            'supplier_warning_count' => $outcome['supplier_warning_count'] ?? null,
            'automatic_retry_allowed' => array_key_exists('automatic_retry_allowed', $outcome) ? (bool) $outcome['automatic_retry_allowed'] : null,
            'same_payload_retry_recommended' => array_key_exists('same_payload_retry_recommended', $outcome) ? (bool) $outcome['same_payload_retry_recommended'] : null,
            'retry_idempotency_safe' => array_key_exists('retry_idempotency_safe', $outcome) ? (bool) $outcome['retry_idempotency_safe'] : null,
            'underlying_block_reason' => $outcome['block_reason'] ?? null,
        ] + $linkageFields + $canonicalFields, static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function flattenCanonicalLinkageNormalizationFields(array $outcome): array
    {
        $canonical = is_array($outcome['canonical_linkage_normalization'] ?? null)
            ? $outcome['canonical_linkage_normalization']
            : (is_array($outcome[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY] ?? null)
                ? $outcome[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY]
                : (is_array(data_get($outcome, 'response_linkage_diagnostics.canonical_linkage_normalization'))
                    ? data_get($outcome, 'response_linkage_diagnostics.canonical_linkage_normalization')
                    : (is_array($outcome['revalidation_canonical_linkage_normalization'] ?? null)
                        ? $outcome['revalidation_canonical_linkage_normalization']
                        : (is_array($outcome['pre_supplier_canonical_linkage_normalization'] ?? null)
                            ? $outcome['pre_supplier_canonical_linkage_normalization']
                            : []))));

        if ($canonical === []) {
            return [];
        }

        return array_filter([
            'canonical_signature_version' => $canonical['canonical_signature_version'] ?? null,
            'canonical_hash_tuple_schema_version' => $canonical['canonical_hash_tuple_schema_version'] ?? null,
            'canonical_hash_tuple_field_count' => $canonical['canonical_hash_tuple_field_count'] ?? null,
            'selected_canonical_tuple_segment_digests' => $canonical['selected_canonical_tuple_segment_digests'] ?? null,
            'draft_canonical_tuple_segment_digests' => $canonical['draft_canonical_tuple_segment_digests'] ?? null,
            'selected_canonical_hash_tuple_values' => $canonical['selected_canonical_hash_tuple_values'] ?? null,
            'draft_canonical_hash_tuple_values' => $canonical['draft_canonical_hash_tuple_values'] ?? null,
            'selected_endpoint_clock_evidence' => $canonical['selected_endpoint_clock_evidence'] ?? null,
            'draft_endpoint_clock_evidence' => $canonical['draft_endpoint_clock_evidence'] ?? null,
            'selected_segment_signature_digest' => $canonical['selected_segment_signature_digest'] ?? null,
            'draft_segment_signature_digest' => $canonical['draft_segment_signature_digest'] ?? null,
            'selected_draft_signature_equal' => array_key_exists('selected_draft_signature_equal', $canonical)
                ? (bool) $canonical['selected_draft_signature_equal']
                : null,
            'selected_segment_count' => $canonical['selected_segment_count'] ?? null,
            'draft_segment_count' => $canonical['draft_segment_count'] ?? null,
            'selected_segment_order_digest' => $canonical['selected_segment_order_digest'] ?? null,
            'draft_segment_order_digest' => $canonical['draft_segment_order_digest'] ?? null,
            'structurally_eligible_candidate_signature_digests' => $canonical['structurally_eligible_candidate_signature_digests'] ?? null,
            'candidate_mismatch_categories' => $canonical['candidate_mismatch_categories'] ?? null,
            'candidate_tuple_mismatch_field_names' => $canonical['candidate_tuple_mismatch_field_names'] ?? null,
            'candidate_canonical_tuple_segment_digests' => $canonical['candidate_canonical_tuple_segment_digests'] ?? null,
            'candidate_canonical_hash_tuple_values' => $canonical['candidate_canonical_hash_tuple_values'] ?? null,
            'candidate_tuple_field_comparisons' => $canonical['candidate_tuple_field_comparisons'] ?? null,
            'candidate_schedule_descriptor_resolution' => $canonical['candidate_schedule_descriptor_resolution'] ?? null,
            'candidate_operating_carrier_shape_categories' => $canonical['candidate_operating_carrier_shape_categories'] ?? null,
            'candidate_canonical_operating_carrier_slots' => $canonical['candidate_canonical_operating_carrier_slots'] ?? null,
            'normalized_flight_number_shapes' => $canonical['normalized_flight_number_shapes'] ?? null,
            'normalized_wall_clock_shapes' => $canonical['normalized_wall_clock_shapes'] ?? null,
            'marketing_carrier_shape_categories' => $canonical['marketing_carrier_shape_categories'] ?? null,
            'operating_carrier_shape_categories' => $canonical['operating_carrier_shape_categories'] ?? null,
            'fare_basis_presence_by_candidate' => $canonical['fare_basis_presence_by_candidate'] ?? null,
            'fare_basis_applicability_match_count' => $canonical['fare_basis_applicability_match_count'] ?? null,
            'booking_class_compatibility_count' => $canonical['booking_class_compatibility_count'] ?? null,
            'selected_segment_component_summaries' => $canonical['selected_segment_component_summaries'] ?? null,
            'draft_segment_component_summaries' => $canonical['draft_segment_component_summaries'] ?? null,
            'canonical_linkage_normalization' => $canonical,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $blockedContext
     * @return array<string, mixed>
     */
    public function mapBlockedEvidence(array $blockedContext): array
    {
        $blockReason = (string) ($blockedContext['block_reason'] ?? self::REASON_FAILED);
        $syntheticOutcome = [
            'success' => false,
            'revalidation_attempted' => ($blockedContext['attempted'] ?? false) === true,
            'supplier_call_attempted' => false,
            'supplier_response_received' => false,
            'reason_code' => $blockedContext['reason_code'] ?? $blockReason,
        ];

        $context = array_merge($blockedContext, [
            'pre_block_reason' => $blockReason,
        ]);

        return $this->mapToScenarioEvidence($syntheticOutcome, $context);
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $context
     * @param  list<string>  $mismatches
     */
    public function classifyScenarioReasonCode(array $outcome, array $context = [], array $mismatches = []): string
    {
        $preBlock = (string) ($context['pre_block_reason'] ?? '');
        if ($preBlock !== '') {
            return $this->classifyPreCallBlockReason($preBlock);
        }

        if (in_array('currency_change', $mismatches, true)) {
            return self::REASON_CURRENCY_CHANGED;
        }

        if (in_array('price_change', $mismatches, true)) {
            return self::REASON_PRICE_CHANGED;
        }

        $transportCategory = (string) ($outcome['transport_error_category'] ?? '');
        if ($transportCategory === 'timeout') {
            return self::REASON_TIMEOUT;
        }
        if ($transportCategory !== '') {
            return self::REASON_TRANSPORT_FAILURE;
        }

        if (($outcome['exception_class_category'] ?? null) !== null && ($outcome['success'] ?? false) !== true) {
            return self::REASON_INTERNAL_EXCEPTION;
        }

        $http = $outcome['http_status'] ?? null;
        if (is_int($http) && $http >= 400) {
            $supplierClassification = trim((string) ($outcome['supplier_http_failure_classification'] ?? ''));
            if ($supplierClassification !== '' && $supplierClassification !== self::REASON_HTTP_REJECTED) {
                return $supplierClassification;
            }

            return self::REASON_HTTP_REJECTED;
        }

        if (($outcome['grouped_itinerary_errors_present'] ?? false) === true) {
            return self::REASON_GROUPED_ITINERARY_ERROR;
        }

        $failureClass = (string) ($outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? '');

        if (($outcome['blocking_application_error_present'] ?? false) === true
            || ($outcome['blocking_application_warning_present'] ?? false) === true
            || (($outcome['application_errors_present'] ?? false) === true
                && ($outcome['informational_warning_present'] ?? false) !== true
                && in_array($failureClass, ['application_warning', 'application_error'], true))) {
            return self::REASON_SUPPLIER_APPLICATION_ERROR;
        }

        $http = $outcome['http_status'] ?? null;
        if (is_int($http) && $http === 200 && ($outcome['supplier_response_received'] ?? false) === true) {
            $http200Reason = $this->classifyHttp200SupplierOutcomeReason($outcome, $mismatches, $failureClass);
            if ($http200Reason !== null) {
                return $http200Reason;
            }
        }

        if ($failureClass === 'fare_basis_incomplete' && ($outcome['fare_basis_complete'] ?? null) === false) {
            return self::REASON_FARE_BASIS_INCOMPLETE;
        }

        if ($failureClass === 'unusable_linkage' || ($outcome['usable_fare_linkage'] ?? null) === false) {
            if (($outcome['supplier_response_received'] ?? false) === true) {
                return self::REASON_FARE_LINKAGE_MISSING;
            }
        }

        if (($outcome['offer_unavailable'] ?? false) === true || $failureClass === 'mip_5053') {
            return self::REASON_OFFER_UNAVAILABLE;
        }

        if (($outcome['supplier_response_received'] ?? false) === true
            && (($outcome['response_json_valid'] ?? true) === false
                || ($outcome['response_empty'] ?? false) === true
                || in_array('no_pricing', $mismatches, true)
                || $failureClass === 'pricing_tripwire')) {
            return self::REASON_RESPONSE_MAPPING_FAILED;
        }

        $reasonCode = (string) ($outcome['reason_code'] ?? '');
        if ($reasonCode === 'sabre_revalidation_gatekeeper_failed') {
            return self::REASON_UNSUPPORTED_CONTEXT;
        }

        if (($outcome['success'] ?? false) === true) {
            return self::REASON_SUCCESS;
        }

        return self::REASON_FAILED;
    }

    /**
     * @param  list<string>  $mismatches
     */
    protected function classifyHttp200SupplierOutcomeReason(array $outcome, array $mismatches, string $failureClass): ?string
    {
        if ($this->uniqueUsableLinkageSuccessGatesSatisfied($outcome)) {
            return self::REASON_SUCCESS;
        }

        if (($outcome['success'] ?? false) === true) {
            return null;
        }

        if (($outcome['response_empty'] ?? false) === true || ($outcome['response_json_valid'] ?? true) === false) {
            return self::REASON_RESPONSE_MAPPING_FAILED;
        }

        if (in_array('no_pricing', $mismatches, true) || $failureClass === 'pricing_tripwire') {
            return self::REASON_RESPONSE_MAPPING_FAILED;
        }

        $linkage = $this->mergedLinkageDiagnostics($outcome);
        if ($this->http200LinkageDiagnosticsMissing($outcome, $linkage)) {
            return self::REASON_DIAGNOSTICS_INCOMPLETE;
        }

        $structurallyEligible = (int) ($linkage['structurally_eligible_candidate_count'] ?? -1);
        $responseCandidates = (int) ($linkage['response_candidate_count'] ?? $this->resolveResponseCandidateCount($outcome) ?? 0);
        if ($structurallyEligible === 0 && $responseCandidates > 0) {
            return self::REASON_FARE_LINKAGE_MISSING;
        }

        $exactSignature = (int) ($linkage['exact_segment_signature_match_count'] ?? -1);
        if ($exactSignature === 0) {
            return self::REASON_FARE_LINKAGE_MISSING;
        }

        $exactItinerary = (int) ($linkage['exact_itinerary_match_count'] ?? -1);
        if ($exactItinerary === 0) {
            return self::REASON_FARE_LINKAGE_MISSING;
        }

        $pricingCompatible = (int) ($linkage['pricing_compatible_match_count'] ?? -1);
        if ($pricingCompatible === 0) {
            return self::REASON_RESPONSE_MAPPING_FAILED;
        }

        if ($this->fareBasisSegmentIncomplete($outcome, $linkage, 'selected')) {
            return self::REASON_FARE_BASIS_INCOMPLETE;
        }

        if ($this->fareBasisSegmentIncomplete($outcome, $linkage, 'draft')) {
            return self::REASON_FARE_BASIS_INCOMPLETE;
        }

        if ($this->fareBasisSegmentIncomplete($outcome, $linkage, 'candidate')) {
            return self::REASON_FARE_BASIS_INCOMPLETE;
        }

        $fareBasisCompatible = (int) ($linkage['fare_basis_compatible_match_count'] ?? -1);
        if ($fareBasisCompatible === 0) {
            return $this->mapLinkageFailureReasonCodeToScenarioReason(
                SabreGdsRevalidationResponseCandidateLinker::REASON_FARE_BASIS_INCOMPATIBLE,
            );
        }

        $bookingClassCompatible = (int) ($linkage['booking_class_compatible_match_count'] ?? -1);
        if ($bookingClassCompatible === 0) {
            return $this->mapLinkageFailureReasonCodeToScenarioReason(
                SabreGdsRevalidationResponseCandidateLinker::REASON_BOOKING_CLASS_INCOMPATIBLE,
            );
        }

        $ambiguous = (int) ($linkage['ambiguous_linkage_match_count'] ?? 0);
        if ($ambiguous > 0) {
            return self::REASON_FARE_LINKAGE_MISSING;
        }

        $uniqueUsable = (int) ($linkage['unique_usable_linkage_match_count'] ?? -1);
        if ($uniqueUsable === 0) {
            $linkageReason = trim((string) ($linkage['linkage_failure_reason_code'] ?? ''));
            if ($linkageReason !== '') {
                return $this->mapLinkageFailureReasonCodeToScenarioReason($linkageReason);
            }

            return self::REASON_FARE_LINKAGE_MISSING;
        }

        $linkageReason = trim((string) ($linkage['linkage_failure_reason_code'] ?? ''));
        if ($linkageReason !== '') {
            return $this->mapLinkageFailureReasonCodeToScenarioReason($linkageReason);
        }

        if ($failureClass === 'fare_basis_incomplete' && ($outcome['fare_basis_complete'] ?? null) === false) {
            return self::REASON_FARE_BASIS_INCOMPLETE;
        }

        return null;
    }

    protected function mapLinkageFailureReasonCodeToScenarioReason(string $linkageReason): string
    {
        return match ($linkageReason) {
            SabreGdsRevalidationResponseCandidateLinker::REASON_PRICING_INCOMPLETE => self::REASON_RESPONSE_MAPPING_FAILED,
            SabreGdsRevalidationResponseCandidateLinker::REASON_FARE_BASIS_INCOMPATIBLE,
            SabreGdsRevalidationResponseCandidateLinker::REASON_BOOKING_CLASS_INCOMPATIBLE,
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_STRUCTURALLY_ELIGIBLE,
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH,
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_ITINERARY_MATCH,
            SabreGdsRevalidationResponseCandidateLinker::REASON_AMBIGUOUS_EXACT_ITINERARY_MATCH => self::REASON_FARE_LINKAGE_MISSING,
            default => self::REASON_FARE_LINKAGE_MISSING,
        };
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function mergedLinkageDiagnostics(array $outcome): array
    {
        $fromLinkage = is_array($outcome['response_linkage_diagnostics'] ?? null)
            ? $outcome['response_linkage_diagnostics']
            : [];

        $countKeys = [
            'response_candidate_count',
            'structurally_eligible_candidate_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
            'pricing_compatible_match_count',
            'fare_basis_compatible_match_count',
            'booking_class_compatible_match_count',
            'unique_usable_linkage_match_count',
            'ambiguous_linkage_match_count',
        ];

        $merged = $fromLinkage;
        foreach ($countKeys as $key) {
            if (! array_key_exists($key, $merged) && array_key_exists($key, $outcome) && is_int($outcome[$key])) {
                $merged[$key] = $outcome[$key];
            }
        }

        foreach (['pricing_complete', 'usable_fare_linkage', 'linkage_failure_reason_code', 'selected_response_candidate_ordinal'] as $key) {
            if (! array_key_exists($key, $merged) && array_key_exists($key, $outcome)) {
                $merged[$key] = $outcome[$key];
            }
        }

        if (! array_key_exists('fare_basis_complete', $merged) && array_key_exists('fare_basis_complete', $outcome)) {
            $merged['fare_basis_complete'] = (bool) $outcome['fare_basis_complete'];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $linkage
     */
    protected function http200LinkageDiagnosticsMissing(array $outcome, array $linkage): bool
    {
        $failureClass = trim((string) ($outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? ''));
        $needsLinkage = in_array($failureClass, ['unusable_linkage', 'fare_basis_incomplete', 'pricing_tripwire'], true)
            || ($outcome['usable_fare_linkage'] ?? null) === false;

        if (! $needsLinkage) {
            return false;
        }

        $authoritativeKeys = [
            'structurally_eligible_candidate_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
            'unique_usable_linkage_match_count',
        ];

        foreach ($authoritativeKeys as $key) {
            if (array_key_exists($key, $linkage) && is_int($linkage[$key])) {
                return false;
            }
        }

        return $linkage === [] || ! $this->hasAnyLinkageCount($linkage);
    }

    /**
     * @param  array<string, mixed>  $linkage
     */
    protected function hasAnyLinkageCount(array $linkage): bool
    {
        foreach ([
            'structurally_eligible_candidate_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
            'pricing_compatible_match_count',
            'fare_basis_compatible_match_count',
            'booking_class_compatible_match_count',
            'unique_usable_linkage_match_count',
            'ambiguous_linkage_match_count',
        ] as $key) {
            if (array_key_exists($key, $linkage) && is_int($linkage[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function uniqueUsableLinkageSuccessGatesSatisfied(array $outcome): bool
    {
        return app(SabreGdsRevalidationLinkageAggregateContract::class)
            ->normalizeFromOutcome($outcome)['usable_fare_linkage'] === true;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $linkage
     */
    protected function triStateLinkageBool(array $outcome, array $linkage, string $key): ?bool
    {
        if (array_key_exists($key, $outcome)) {
            return ($outcome[$key] ?? false) === true;
        }
        if (array_key_exists($key, $linkage)) {
            return ($linkage[$key] ?? false) === true;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $linkage
     */
    protected function fareBasisSegmentIncomplete(array $outcome, array $linkage, string $scope): bool
    {
        $key = match ($scope) {
            'selected' => 'selected_fare_basis_complete',
            'draft' => 'draft_fare_basis_complete',
            'candidate' => 'candidate_fare_basis_complete',
            default => '',
        };
        if ($key === '') {
            return false;
        }

        $canonical = $this->resolveCanonicalLinkageNormalizationDiagnosticsBlock($outcome);
        $value = $canonical[$key] ?? data_get($outcome, 'payload_structural_digest.'.$key);
        if ($value === null) {
            return false;
        }

        return $value === false;
    }

    protected function resolveScenarioFailureCategory(array $outcome, string $scenarioReasonCode): ?string
    {
        return match ($scenarioReasonCode) {
            self::REASON_SUCCESS => null,
            self::REASON_FARE_BASIS_INCOMPLETE => 'fare_basis_incomplete',
            self::REASON_FARE_LINKAGE_MISSING => 'unusable_linkage',
            self::REASON_DIAGNOSTICS_INCOMPLETE => 'diagnostics_incomplete',
            self::REASON_RESPONSE_MAPPING_FAILED => 'pricing_tripwire',
            self::REASON_SUPPLIER_APPLICATION_ERROR => 'application_error',
            default => $outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? null,
        };
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function buildOutcomeMapperInputSnapshot(array $outcome): array
    {
        $aggregates = app(SabreGdsRevalidationLinkageAggregateContract::class)->normalizeFromOutcome($outcome);
        $linkage = $this->mergedLinkageDiagnostics($outcome);

        return array_filter([
            'http_status' => $outcome['http_status'] ?? null,
            'success' => ($outcome['success'] ?? false) === true,
            'failure_category' => $outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? null,
            'fare_basis_complete' => $aggregates['fare_basis_complete'] === true ? true : ($aggregates['fare_basis_complete'] === false ? false : null),
            'overall_fare_basis_complete' => $aggregates['overall_fare_basis_complete'] === true ? true : ($aggregates['overall_fare_basis_complete'] === false ? false : null),
            'selected_fare_basis_complete' => $aggregates['selected_fare_basis_complete'],
            'draft_fare_basis_complete' => $aggregates['draft_fare_basis_complete'],
            'candidate_fare_basis_complete' => $aggregates['candidate_fare_basis_complete'],
            'pricing_complete' => $aggregates['pricing_complete'] === true ? true : ($aggregates['pricing_complete'] === false ? false : null),
            'usable_fare_linkage' => $aggregates['usable_fare_linkage'] === true,
            'response_candidate_count' => $linkage['response_candidate_count'] ?? $this->resolveResponseCandidateCount($outcome),
            'structurally_eligible_candidate_count' => $linkage['structurally_eligible_candidate_count'] ?? null,
            'exact_segment_signature_match_count' => $linkage['exact_segment_signature_match_count'] ?? null,
            'exact_itinerary_match_count' => $linkage['exact_itinerary_match_count'] ?? null,
            'pricing_compatible_match_count' => $linkage['pricing_compatible_match_count'] ?? null,
            'fare_basis_compatible_match_count' => $linkage['fare_basis_compatible_match_count'] ?? null,
            'booking_class_compatible_match_count' => $linkage['booking_class_compatible_match_count'] ?? null,
            'unique_usable_linkage_match_count' => $linkage['unique_usable_linkage_match_count'] ?? null,
            'ambiguous_linkage_match_count' => $linkage['ambiguous_linkage_match_count'] ?? null,
            'linkage_failure_reason_code' => $linkage['linkage_failure_reason_code'] ?? null,
            'aggregate_derivation_source' => $aggregates['aggregate_derivation_source'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>  $normalizerLogEntry
     */
    public function assessNormalizerRejectCorrelation(array $selectedContext, array $normalizerLogEntry): string
    {
        $logRevalidationCorrelation = trim((string) ($normalizerLogEntry['revalidation_correlation_id'] ?? ''));
        $selectedRevalidationCorrelation = trim((string) ($selectedContext['revalidation_correlation_id'] ?? ''));
        if ($logRevalidationCorrelation !== ''
            && $selectedRevalidationCorrelation !== ''
            && hash_equals($selectedRevalidationCorrelation, $logRevalidationCorrelation)) {
            return self::CORRELATION_SELECTED_OFFER;
        }

        $logSearchCorrelation = trim((string) ($normalizerLogEntry['scenario_search_correlation_id'] ?? ''));
        $selectedSearchCorrelation = trim((string) ($selectedContext['scenario_search_correlation_id'] ?? ''));
        if ($logSearchCorrelation !== ''
            && $selectedSearchCorrelation !== ''
            && ! hash_equals($selectedSearchCorrelation, $logSearchCorrelation)) {
            return self::CORRELATION_SEPARATE_SEARCH;
        }

        if ($logSearchCorrelation !== ''
            && $selectedSearchCorrelation !== ''
            && hash_equals($selectedSearchCorrelation, $logSearchCorrelation)) {
            if ($this->normalizerEntryMatchesSelectedOffer($selectedContext, $normalizerLogEntry)) {
                return self::CORRELATION_SELECTED_OFFER;
            }

            return self::CORRELATION_UNRELATED_OFFER_SAME_RESPONSE;
        }

        if ($this->normalizerEntryMatchesSelectedOffer($selectedContext, $normalizerLogEntry)) {
            return self::CORRELATION_SELECTED_OFFER;
        }

        if ($this->normalizerEntryClearlyUnrelated($selectedContext, $normalizerLogEntry)) {
            return self::CORRELATION_UNRELATED_OFFER_SAME_RESPONSE;
        }

        return self::CORRELATION_UNKNOWN;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    public function extractScenarioResultFields(array $evidence): array
    {
        return array_filter(array_merge(
            [
            'error' => $evidence['revalidation_reason_code'] ?? $evidence['block_reason'] ?? self::REASON_FAILED,
            'revalidation_reason_code' => $evidence['revalidation_reason_code'] ?? null,
            'revalidation_failure_category' => $evidence['revalidation_failure_category'] ?? null,
            'revalidation_http_status' => $evidence['revalidation_http_status'] ?? null,
            'revalidation_endpoint_path' => $evidence['revalidation_endpoint_path'] ?? null,
            'supplier_call_attempted' => $this->triStateBool($evidence, 'supplier_call_attempted'),
            'supplier_response_received' => $this->triStateBool($evidence, 'supplier_response_received'),
            'revalidation_style' => $evidence['revalidation_style'] ?? null,
            'response_structure_summary' => $evidence['response_structure_summary'] ?? null,
            'revalidation_attempted' => ($evidence['revalidation_attempted'] ?? false) === true,
            'revalidation_success' => ($evidence['revalidation_success'] ?? false) === true,
            'freshness_satisfied' => ($evidence['freshness_satisfied'] ?? false) === true,
            'selected_total' => $evidence['selected_total'] ?? null,
            'selected_currency' => $evidence['selected_currency'] ?? null,
            'revalidated_total' => $evidence['revalidated_total'] ?? null,
            'revalidated_currency' => $evidence['revalidated_currency'] ?? null,
            'fare_changed' => ($evidence['fare_changed'] ?? false) === true,
            'revalidation_at' => $evidence['revalidation_at'] ?? null,
            'selected_offer_fingerprint' => $evidence['selected_offer_fingerprint'] ?? null,
            'revalidation_linkage_ready' => ($evidence['revalidation_linkage_ready'] ?? false) === true,
            'retry_safe' => array_key_exists('retry_safe', $evidence) ? (bool) $evidence['retry_safe'] : null,
            'revalidation_correlation_id' => $evidence['revalidation_correlation_id'] ?? null,
            'selected_segment_signature_hash' => $evidence['selected_segment_signature_hash'] ?? null,
            'selected_source_identifier_hash' => $evidence['selected_source_identifier_hash'] ?? null,
            'selected_route' => $evidence['selected_route'] ?? null,
            'selected_segment_count' => $evidence['selected_segment_count'] ?? null,
            'revalidation_diagnostics' => $evidence['revalidation_diagnostics'] ?? null,
            'block_reason' => $evidence['block_reason'] ?? null,
            'reason_code' => $evidence['reason_code'] ?? null,
            'supplier_error_type' => $evidence['supplier_error_type'] ?? null,
            'supplier_error_code' => $evidence['supplier_error_code'] ?? null,
            'supplier_error_message_safe' => $evidence['supplier_error_message_safe'] ?? null,
            'supplier_additional_messages_summary' => $evidence['supplier_additional_messages_summary'] ?? null,
            'supplier_additional_message_codes' => $evidence['supplier_additional_message_codes'] ?? null,
            'supplier_validation_paths' => $evidence['supplier_validation_paths'] ?? null,
            'supplier_error_count' => $evidence['supplier_error_count'] ?? null,
            'supplier_warning_count' => $evidence['supplier_warning_count'] ?? null,
            'automatic_retry_allowed' => array_key_exists('automatic_retry_allowed', $evidence) ? (bool) $evidence['automatic_retry_allowed'] : null,
            'same_payload_retry_recommended' => array_key_exists('same_payload_retry_recommended', $evidence) ? (bool) $evidence['same_payload_retry_recommended'] : null,
            'retry_idempotency_safe' => array_key_exists('retry_idempotency_safe', $evidence) ? (bool) $evidence['retry_idempotency_safe'] : null,
            ],
            $this->extractCanonicalLinkageNormalizationArtifactFields($evidence),
        ), static fn ($value) => $value !== null);
    }

    /**
     * @return list<string>
     */
    public function canonicalLinkageNormalizationFlatFieldKeys(): array
    {
        return [
            'canonical_signature_version',
            'canonical_hash_tuple_schema_version',
            'canonical_hash_tuple_field_count',
            'selected_canonical_tuple_segment_digests',
            'draft_canonical_tuple_segment_digests',
            'selected_canonical_hash_tuple_values',
            'draft_canonical_hash_tuple_values',
            'selected_endpoint_clock_evidence',
            'draft_endpoint_clock_evidence',
            'selected_segment_signature_digest',
            'draft_segment_signature_digest',
            'selected_draft_signature_equal',
            'selected_segment_count',
            'draft_segment_count',
            'selected_segment_order_digest',
            'draft_segment_order_digest',
            'structurally_eligible_candidate_signature_digests',
            'candidate_mismatch_categories',
            'candidate_tuple_mismatch_field_names',
            'candidate_canonical_tuple_segment_digests',
            'candidate_canonical_hash_tuple_values',
            'candidate_tuple_field_comparisons',
            'candidate_schedule_descriptor_resolution',
            'candidate_operating_carrier_shape_categories',
            'candidate_canonical_operating_carrier_slots',
            'normalized_flight_number_shapes',
            'normalized_wall_clock_shapes',
            'marketing_carrier_shape_categories',
            'operating_carrier_shape_categories',
            'fare_basis_presence_by_candidate',
            'fare_basis_applicability_match_count',
            'booking_class_compatibility_count',
            'selected_segment_component_summaries',
            'draft_segment_component_summaries',
        ];
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array<string, mixed>
     */
    public function resolveCanonicalLinkageNormalizationDiagnosticsBlock(array $bag): array
    {
        $persistedKey = SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY;
        if (is_array($bag[$persistedKey] ?? null) && $bag[$persistedKey] !== []) {
            return $bag[$persistedKey];
        }

        foreach ([
            'canonical_linkage_normalization',
            'revalidation_canonical_linkage_normalization',
            'pre_supplier_canonical_linkage_normalization',
        ] as $nestedKey) {
            if (is_array($bag[$nestedKey] ?? null) && $bag[$nestedKey] !== []) {
                return $bag[$nestedKey];
            }
        }

        $fromLinkageDiagnostics = data_get($bag, 'response_linkage_diagnostics.canonical_linkage_normalization');
        if (is_array($fromLinkageDiagnostics) && $fromLinkageDiagnostics !== []) {
            return $fromLinkageDiagnostics;
        }

        $built = [];
        foreach ($this->canonicalLinkageNormalizationFlatFieldKeys() as $field) {
            if (! array_key_exists($field, $bag)) {
                continue;
            }
            $value = $bag[$field];
            if ($value === null || $value === []) {
                continue;
            }
            $built[$field] = $value;
        }

        return $built;
    }

    /**
     * Safe canonical linkage diagnostics for probe/scenario artifact JSON (success and failure).
     *
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    public function extractCanonicalLinkageNormalizationArtifactFields(array $evidence): array
    {
        $block = $this->resolveCanonicalLinkageNormalizationDiagnosticsBlock($evidence);
        if ($block === []) {
            return [];
        }

        $persistedKey = SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY;
        $fields = [
            $persistedKey => $block,
            'canonical_linkage_normalization' => $block,
        ];

        foreach ($this->canonicalLinkageNormalizationFlatFieldKeys() as $flatKey) {
            if (array_key_exists($flatKey, $evidence) && $evidence[$flatKey] !== null && $evidence[$flatKey] !== []) {
                $fields[$flatKey] = $evidence[$flatKey];

                continue;
            }
            if (array_key_exists($flatKey, $block)) {
                $fields[$flatKey] = $block[$flatKey];
            }
        }

        return array_filter($fields, static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function triStateBool(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        return ($data[$key] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>|null
     */
    protected function resolveResponseStructureSummary(array $outcome): ?array
    {
        if (is_array($outcome['response_structure_summary'] ?? null)) {
            return $outcome['response_structure_summary'] !== []
                ? $outcome['response_structure_summary']
                : null;
        }

        if (! array_key_exists('response_structure', $outcome) || ! is_array($outcome['response_structure'])) {
            return null;
        }

        $built = SabreGdsRevalidationSanitizedOutcomeContract::buildResponseStructureSummary($outcome['response_structure']);

        return $built !== [] ? $built : null;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function buildDiagnostics(array $outcome): array
    {
        $canonicalBlock = $this->resolveCanonicalLinkageNormalizationDiagnosticsBlock($outcome);
        $linkage = $this->mergedLinkageDiagnostics($outcome);
        $aggregates = app(SabreGdsRevalidationLinkageAggregateContract::class)->normalizeFromOutcome($outcome);
        $scenarioReasonCode = $this->classifyScenarioReasonCode($outcome);

        return array_filter([
            'duration_ms' => $outcome['duration_ms'] ?? null,
            'operation' => $outcome['operation'] ?? null,
            'transport_error_category' => $outcome['transport_error_category'] ?? null,
            'exception_class_category' => $outcome['exception_class_category'] ?? null,
            'grouped_itinerary_errors_present' => ($outcome['grouped_itinerary_errors_present'] ?? false) === true ? true : null,
            'application_errors_present' => ($outcome['application_errors_present'] ?? false) === true ? true : null,
            'application_warnings_present' => ($outcome['application_warnings_present'] ?? false) === true ? true : null,
            'blocking_application_error_present' => ($outcome['blocking_application_error_present'] ?? false) === true ? true : null,
            'blocking_application_warning_present' => ($outcome['blocking_application_warning_present'] ?? false) === true ? true : null,
            'informational_warning_present' => ($outcome['informational_warning_present'] ?? false) === true ? true : null,
            'application_error_count' => $outcome['application_error_count'] ?? null,
            'application_warning_count' => $outcome['application_warning_count'] ?? null,
            'application_message_categories' => $outcome['application_message_categories'] ?? null,
            'application_message_codes' => $outcome['application_message_codes'] ?? null,
            'response_candidate_count' => $linkage['response_candidate_count'] ?? $this->resolveResponseCandidateCount($outcome),
            'structurally_eligible_candidate_count' => $linkage['structurally_eligible_candidate_count'] ?? null,
            'exact_segment_signature_match_count' => $linkage['exact_segment_signature_match_count'] ?? null,
            'exact_itinerary_match_count' => $linkage['exact_itinerary_match_count'] ?? null,
            'pricing_compatible_match_count' => $linkage['pricing_compatible_match_count'] ?? null,
            'fare_basis_compatible_match_count' => $linkage['fare_basis_compatible_match_count'] ?? null,
            'booking_class_compatible_match_count' => $linkage['booking_class_compatible_match_count'] ?? null,
            'unique_usable_linkage_match_count' => $linkage['unique_usable_linkage_match_count'] ?? null,
            'ambiguous_linkage_match_count' => $linkage['ambiguous_linkage_match_count'] ?? null,
            'linkage_failure_reason_code' => $linkage['linkage_failure_reason_code'] ?? $outcome['linkage_failure_reason_code'] ?? null,
            'linkage_missing_components' => $linkage['linkage_missing_components'] ?? data_get($outcome, 'response_linkage_diagnostics.linkage_missing_components'),
            'linkage_conflicting_components' => $linkage['linkage_conflicting_components'] ?? data_get($outcome, 'response_linkage_diagnostics.linkage_conflicting_components'),
            'selected_response_candidate_ordinal' => $linkage['selected_response_candidate_ordinal'] ?? $outcome['selected_response_candidate_ordinal'] ?? null,
            'pricing_complete' => $aggregates['pricing_complete'] === true ? true : ($aggregates['pricing_complete'] === false ? false : null),
            'fare_basis_complete' => $aggregates['fare_basis_complete'] === true ? true : ($aggregates['fare_basis_complete'] === false ? false : null),
            'selected_fare_basis_complete' => $aggregates['selected_fare_basis_complete'] ?? $canonicalBlock['selected_fare_basis_complete'] ?? null,
            'draft_fare_basis_complete' => $aggregates['draft_fare_basis_complete'] ?? $canonicalBlock['draft_fare_basis_complete'] ?? null,
            'candidate_fare_basis_complete' => $aggregates['candidate_fare_basis_complete'] ?? $canonicalBlock['candidate_fare_basis_complete'] ?? null,
            'overall_fare_basis_complete' => $aggregates['overall_fare_basis_complete'] === true ? true : ($aggregates['overall_fare_basis_complete'] === false ? false : null),
            'usable_fare_linkage' => $aggregates['usable_fare_linkage'] === true ? true : ($aggregates['usable_fare_linkage'] === false ? false : null),
            'aggregate_derivation_inputs' => $aggregates['aggregate_derivation_inputs'] ?? null,
            'aggregate_derivation_predicate' => $aggregates['aggregate_derivation_predicate'] ?? null,
            'aggregate_derivation_source' => $aggregates['aggregate_derivation_source'] ?? null,
            'prior_stale_values' => $aggregates['prior_stale_values'] ?? data_get($outcome, 'linkage_aggregate_derivation.prior_stale_values'),
            'outcome_mapper_input_snapshot' => $this->buildOutcomeMapperInputSnapshot($outcome),
            'scenario_reason_code_selected' => $scenarioReasonCode,
            'scenario_reason_predicate' => $this->describeScenarioReasonPredicate($outcome, $scenarioReasonCode),
            'offer_unavailable' => ($outcome['offer_unavailable'] ?? false) === true ? true : null,
            'response_json_valid' => array_key_exists('response_json_valid', $outcome) ? (bool) $outcome['response_json_valid'] : null,
            'response_empty' => array_key_exists('response_empty', $outcome) ? (bool) $outcome['response_empty'] : null,
            'supplier_error_type' => $outcome['supplier_error_type'] ?? null,
            'supplier_error_code' => $outcome['supplier_error_code'] ?? null,
            'supplier_error_message_safe' => $outcome['supplier_error_message_safe'] ?? null,
            'supplier_additional_messages_summary' => $outcome['supplier_additional_messages_summary'] ?? null,
            'supplier_additional_message_codes' => $outcome['supplier_additional_message_codes'] ?? null,
            'supplier_validation_paths' => $outcome['supplier_validation_paths'] ?? null,
            'supplier_error_count' => $outcome['supplier_error_count'] ?? null,
            'supplier_warning_count' => $outcome['supplier_warning_count'] ?? null,
            'automatic_retry_allowed' => array_key_exists('automatic_retry_allowed', $outcome) ? (bool) $outcome['automatic_retry_allowed'] : null,
            'same_payload_retry_recommended' => array_key_exists('same_payload_retry_recommended', $outcome) ? (bool) $outcome['same_payload_retry_recommended'] : null,
            'retry_idempotency_safe' => array_key_exists('retry_idempotency_safe', $outcome) ? (bool) $outcome['retry_idempotency_safe'] : null,
            'mismatches' => data_get($outcome, 'fare_comparison.mismatches'),
            SabreGdsRevalidationCanonicalSignatureRuntimePropagation::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY => $canonicalBlock !== []
                ? $canonicalBlock
                : null,
            'canonical_linkage_normalization' => $canonicalBlock !== [] ? $canonicalBlock : null,
        ], static fn ($value) => $value !== null);
    }

    protected function describeScenarioReasonPredicate(array $outcome, string $scenarioReasonCode): string
    {
        if ($scenarioReasonCode === self::REASON_SUCCESS) {
            return 'unique_usable_linkage_match_count=1 && ambiguous_linkage_match_count=0 && pricing_complete && fare_basis_complete && usable_fare_linkage';
        }

        if ($scenarioReasonCode === self::REASON_DIAGNOSTICS_INCOMPLETE) {
            return 'linkage_failure_classification_requires_authoritative_counts && counts_missing';
        }

        if ($scenarioReasonCode === self::REASON_FARE_BASIS_INCOMPLETE) {
            return 'fare_basis_complete===false || scoped_fare_basis_complete===false';
        }

        $linkage = $this->mergedLinkageDiagnostics($outcome);
        $linkageReason = trim((string) ($linkage['linkage_failure_reason_code'] ?? ''));

        return match ($scenarioReasonCode) {
            self::REASON_FARE_LINKAGE_MISSING => $linkageReason !== ''
                ? 'linkage_failure_reason_code='.$linkageReason
                : 'linkage_compatibility_or_uniqueness_failed',
            self::REASON_RESPONSE_MAPPING_FAILED => 'invalid_response_or_pricing_incompatible',
            default => 'failure_category='.(string) ($outcome['failure_category'] ?? $outcome['revalidation_failure_class'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function resolveResponseCandidateCount(array $outcome): ?int
    {
        $fromStructure = data_get($outcome, 'response_structure.candidate_count');
        if (is_string($fromStructure) && ctype_digit(trim($fromStructure))) {
            return (int) trim($fromStructure);
        }
        if (is_int($fromStructure) && $fromStructure >= 0) {
            return $fromStructure;
        }

        $fromSummary = data_get($outcome, 'response_structure_summary.candidate_count');
        if (is_int($fromSummary) && $fromSummary >= 0) {
            return $fromSummary;
        }

        $fromOutcome = $outcome['response_candidate_count'] ?? null;
        if (is_int($fromOutcome) && $fromOutcome >= 0) {
            return $fromOutcome;
        }

        $fromLinkage = data_get($outcome, 'response_linkage_diagnostics.response_candidate_count');
        if (is_int($fromLinkage) && $fromLinkage >= 0) {
            return $fromLinkage;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected function extractLinkageEvidenceFields(array $outcome, array $diagnostics): array
    {
        return array_filter([
            'blocking_application_error_present' => ($outcome['blocking_application_error_present'] ?? false) === true ? true : (($outcome['blocking_application_error_present'] ?? null) === false ? false : null),
            'blocking_application_warning_present' => ($outcome['blocking_application_warning_present'] ?? false) === true ? true : (($outcome['blocking_application_warning_present'] ?? null) === false ? false : null),
            'informational_warning_present' => ($outcome['informational_warning_present'] ?? false) === true ? true : (($outcome['informational_warning_present'] ?? null) === false ? false : null),
            'application_error_count' => $outcome['application_error_count'] ?? $diagnostics['application_error_count'] ?? null,
            'application_warning_count' => $outcome['application_warning_count'] ?? $diagnostics['application_warning_count'] ?? null,
            'response_candidate_count' => $this->resolveResponseCandidateCount($outcome),
            'structurally_eligible_candidate_count' => $diagnostics['structurally_eligible_candidate_count'] ?? data_get($outcome, 'response_linkage_diagnostics.structurally_eligible_candidate_count'),
            'exact_segment_signature_match_count' => $diagnostics['exact_segment_signature_match_count'] ?? null,
            'exact_itinerary_match_count' => $diagnostics['exact_itinerary_match_count'] ?? null,
            'pricing_compatible_match_count' => $diagnostics['pricing_compatible_match_count'] ?? null,
            'fare_basis_compatible_match_count' => $diagnostics['fare_basis_compatible_match_count'] ?? null,
            'booking_class_compatible_match_count' => $diagnostics['booking_class_compatible_match_count'] ?? null,
            'unique_usable_linkage_match_count' => $diagnostics['unique_usable_linkage_match_count'] ?? null,
            'ambiguous_linkage_match_count' => $diagnostics['ambiguous_linkage_match_count'] ?? null,
            'usable_fare_linkage' => array_key_exists('usable_fare_linkage', $outcome) ? (bool) $outcome['usable_fare_linkage'] : ($diagnostics['usable_fare_linkage'] ?? null),
            'linkage_failure_reason_code' => $outcome['linkage_failure_reason_code'] ?? $diagnostics['linkage_failure_reason_code'] ?? null,
            'selected_response_candidate_ordinal' => $outcome['selected_response_candidate_ordinal'] ?? $diagnostics['selected_response_candidate_ordinal'] ?? null,
            'pricing_complete' => array_key_exists('pricing_complete', $outcome) ? (bool) $outcome['pricing_complete'] : ($diagnostics['pricing_complete'] ?? null),
            'fare_basis_complete' => array_key_exists('fare_basis_complete', $outcome) ? (bool) $outcome['fare_basis_complete'] : ($diagnostics['fare_basis_complete'] ?? null),
            'fixture_response_present' => ($outcome['fixture_response_present'] ?? false) === true ? true : null,
            'fixture_response_analyzed' => ($outcome['fixture_response_analyzed'] ?? false) === true ? true : null,
            'replay_performed' => ($outcome['replay_performed'] ?? false) === true ? true : null,
            'supplier_revalidation_call_count' => array_key_exists('supplier_revalidation_call_count', $outcome) ? (int) $outcome['supplier_revalidation_call_count'] : null,
            'db_mutation_detected' => array_key_exists('db_mutation_detected', $outcome) ? (bool) $outcome['db_mutation_detected'] : null,
        ], static fn ($value) => $value !== null);
    }

    protected function classifyPreCallBlockReason(string $blockReason): string
    {
        return match ($blockReason) {
            SabreGdsLiveScenarioRevalidationGate::REASON_REVALIDATION_UNAVAILABLE,
            SabreGdsLiveScenarioRevalidationGate::REASON_DRAFT_INVALID,
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_FINGERPRINT_MISMATCH,
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SOURCE_IDENTIFIER_MISMATCH,
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_SEGMENT_SIGNATURE_MISMATCH => self::REASON_UNSUPPORTED_CONTEXT,
            SabreGdsLiveScenarioExactOfferEvidence::REASON_EXACT_OFFER_LINKAGE_UNAVAILABLE => self::REASON_FARE_LINKAGE_MISSING,
            SabreGdsLiveScenarioRevalidationGate::REASON_FARE_CHANGE_REQUIRES_ACCEPTANCE => self::REASON_PRICE_CHANGED,
            default => self::REASON_FAILED,
        };
    }

    /**
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>  $normalizerLogEntry
     */
    protected function normalizerEntryMatchesSelectedOffer(array $selectedContext, array $normalizerLogEntry): bool
    {
        $selectedSegmentSignature = trim((string) ($selectedContext['selected_segment_signature_hash'] ?? ''));
        $logSegmentSignature = trim((string) ($normalizerLogEntry['segment_signature_hash'] ?? ''));
        if ($selectedSegmentSignature !== '' && $logSegmentSignature !== '') {
            return hash_equals($selectedSegmentSignature, $logSegmentSignature);
        }

        $selectedRoute = trim((string) ($selectedContext['selected_route'] ?? ''));
        $logOrigin = strtoupper(trim((string) ($normalizerLogEntry['offer_origin'] ?? '')));
        $logDestination = strtoupper(trim((string) ($normalizerLogEntry['offer_destination'] ?? '')));
        $logRoute = $logOrigin !== '' && $logDestination !== '' ? $logOrigin.'-'.$logDestination : '';

        $selectedSegmentCount = (int) ($selectedContext['selected_segment_count'] ?? 0);
        $logSegmentCount = (int) ($normalizerLogEntry['segment_count'] ?? 0);

        if ($selectedRoute !== '' && $logRoute !== '' && $selectedRoute === $logRoute
            && $selectedSegmentCount > 0 && $logSegmentCount > 0
            && $selectedSegmentCount === $logSegmentCount) {
            $selectedFingerprint = trim((string) ($selectedContext['selected_offer_fingerprint'] ?? ''));
            $logOfferIdHash = trim((string) ($normalizerLogEntry['offer_id_hash'] ?? ''));

            return $selectedFingerprint !== '' && $logOfferIdHash !== ''
                && hash_equals($selectedFingerprint, $logOfferIdHash);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>  $normalizerLogEntry
     */
    protected function normalizerEntryClearlyUnrelated(array $selectedContext, array $normalizerLogEntry): bool
    {
        $selectedRoute = trim((string) ($selectedContext['selected_route'] ?? ''));
        $logOrigin = strtoupper(trim((string) ($normalizerLogEntry['offer_origin'] ?? '')));
        $logDestination = strtoupper(trim((string) ($normalizerLogEntry['offer_destination'] ?? '')));
        $logRoute = $logOrigin !== '' && $logDestination !== '' ? $logOrigin.'-'.$logDestination : '';

        if ($selectedRoute !== '' && $logRoute !== '' && $selectedRoute !== $logRoute) {
            return true;
        }

        $selectedSegmentCount = (int) ($selectedContext['selected_segment_count'] ?? 0);
        $logSegmentCount = (int) ($normalizerLogEntry['segment_count'] ?? 0);

        return $selectedSegmentCount > 0 && $logSegmentCount > 0 && $selectedSegmentCount !== $logSegmentCount;
    }
}
