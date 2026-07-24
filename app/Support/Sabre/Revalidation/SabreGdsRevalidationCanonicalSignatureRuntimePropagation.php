<?php

namespace App\Support\Sabre\Revalidation;

use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioExactOfferEvidence;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;

/**
 * Propagates canonical segment signatures through shop evidence, draft handoff, supplier HTTP, and artifacts.
 */
final class SabreGdsRevalidationCanonicalSignatureRuntimePropagation
{
    public const RUNTIME_DRAFT_KEY = '_canonical_linkage_runtime';

    public const CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY = 'canonical_linkage_normalization_diagnostics';

    public function __construct(
        private readonly SabreGdsRevalidationCanonicalSegmentSignature $canonicalSegmentSignature,
        private readonly SabreGdsLiveScenarioExactOfferEvidence $exactOfferEvidence,
    ) {}

    /**
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $offerSnap
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $selectedFareFamilyOption
     * @param  array<string, mixed>  $continuityEvidence
     * @return array<string, mixed>
     */
    public function attachRuntimeToDraft(
        array $apiDraft,
        SupplierConnection $connection,
        array $offerSnap,
        array $row,
        ?array $selectedFareFamilyOption,
        array $continuityEvidence,
    ): array {
        $selectedRows = $this->exactOfferEvidence->canonicalLinkageSegmentRows(
            $offerSnap,
            $row,
            $selectedFareFamilyOption,
            is_array($continuityEvidence['booking_classes_by_segment'] ?? null)
                ? $continuityEvidence['booking_classes_by_segment']
                : [],
        );
        $selectedDigest = trim((string) ($continuityEvidence['segment_signature'] ?? ''));
        if ($selectedDigest === '' && $selectedRows !== []) {
            $selectedDigest = $this->canonicalSegmentSignature->hashFromSegments(
                $this->mergeCanonicalRowsWithDraftFareFields($selectedRows, is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : []),
            );
        }

        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draftOnlyContext = $linker->buildSelectedContextFromDraft($this->withoutRuntime($apiDraft));
        $draftDigest = trim((string) ($draftOnlyContext['segment_signature'] ?? ''));

        $apiDraft[self::RUNTIME_DRAFT_KEY] = array_filter([
            'canonical_signature_version' => SabreGdsRevalidationCanonicalSegmentSignature::VERSION,
            'selected_segment_signature_digest' => $selectedDigest !== '' ? $selectedDigest : null,
            'draft_segment_signature_digest' => $draftDigest !== '' ? $draftDigest : null,
            'selected_draft_signature_equal' => $selectedDigest !== '' && $draftDigest !== ''
                ? hash_equals($selectedDigest, $draftDigest)
                : null,
            'selected_segment_count' => count($selectedRows),
            'draft_segment_count' => (int) ($draftOnlyContext['segment_count'] ?? 0),
            'selected_segment_order_digest' => $this->orderDigestFromRows($selectedRows),
            'draft_segment_order_digest' => $this->orderDigestFromContext($draftOnlyContext),
            'canonical_segment_rows' => $selectedRows !== [] ? $selectedRows : null,
            'selected_segment_component_summaries' => $this->safeComponentSummaries($selectedRows),
            'draft_segment_component_summaries' => $this->safeComponentSummariesFromContext($draftOnlyContext),
        ], static fn ($value) => $value !== null && $value !== []);

        return $apiDraft;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return list<array<string, mixed>>
     */
    public function resolveLinkageSegmentsFromDraft(array $apiDraft): array
    {
        $runtime = is_array($apiDraft[self::RUNTIME_DRAFT_KEY] ?? null) ? $apiDraft[self::RUNTIME_DRAFT_KEY] : [];
        $canonicalRows = is_array($runtime['canonical_segment_rows'] ?? null) ? $runtime['canonical_segment_rows'] : [];
        $draftSegments = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        if ($canonicalRows !== []) {
            return $this->mergeCanonicalRowsWithDraftFareFields($canonicalRows, $draftSegments);
        }

        return $draftSegments;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    public function preSupplierHttpDiagnostics(array $apiDraft): array
    {
        $runtime = is_array($apiDraft[self::RUNTIME_DRAFT_KEY] ?? null) ? $apiDraft[self::RUNTIME_DRAFT_KEY] : [];
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $withRuntime = $linker->buildSelectedContextFromDraft($apiDraft);
        $draftOnly = $linker->buildSelectedContextFromDraft($this->withoutRuntime($apiDraft));

        return array_filter([
            'canonical_hash_tuple_schema_version' => SabreGdsRevalidationCanonicalSegmentSignature::HASH_TUPLE_SCHEMA_VERSION,
            'canonical_hash_tuple_field_count' => SabreGdsRevalidationCanonicalSegmentSignature::HASH_TUPLE_FIELD_COUNT,
            'selected_canonical_tuple_segment_digests' => $this->canonicalSegmentSignature->scheduleHashTupleSegmentDigests(
                is_array($withRuntime['segments'] ?? null) ? $withRuntime['segments'] : [],
            ) ?: null,
            'draft_canonical_tuple_segment_digests' => $this->canonicalSegmentSignature->scheduleHashTupleSegmentDigests(
                is_array($draftOnly['segments'] ?? null) ? $draftOnly['segments'] : [],
            ) ?: null,
            'selected_canonical_hash_tuple_values' => $this->canonicalSegmentSignature->safeCanonicalHashTupleValueRows(
                is_array($withRuntime['segments'] ?? null) ? $withRuntime['segments'] : [],
            ) ?: null,
            'draft_canonical_hash_tuple_values' => $this->canonicalSegmentSignature->safeCanonicalHashTupleValueRows(
                is_array($draftOnly['segments'] ?? null) ? $draftOnly['segments'] : [],
            ) ?: null,
            'canonical_signature_version' => SabreGdsRevalidationCanonicalSegmentSignature::VERSION,
            'selected_segment_signature_digest' => $runtime['selected_segment_signature_digest']
                ?? ($withRuntime['segment_signature'] ?? null),
            'draft_segment_signature_digest' => $draftOnly['segment_signature'] ?? null,
            'selected_draft_signature_equal' => isset($withRuntime['segment_signature'], $draftOnly['segment_signature'])
                ? hash_equals((string) $withRuntime['segment_signature'], (string) $draftOnly['segment_signature'])
                : ($runtime['selected_draft_signature_equal'] ?? null),
            'selected_segment_count' => (int) ($withRuntime['segment_count'] ?? 0),
            'draft_segment_count' => (int) ($draftOnly['segment_count'] ?? 0),
            'selected_segment_order_digest' => $this->orderDigestFromContext($withRuntime),
            'draft_segment_order_digest' => $this->orderDigestFromContext($draftOnly),
            'selected_segment_component_summaries' => $this->safeComponentSummariesFromContext($withRuntime),
            'draft_segment_component_summaries' => $this->safeComponentSummariesFromContext($draftOnly),
            'selected_endpoint_clock_evidence' => $this->endpointClockEvidenceFromContext($withRuntime) ?: null,
            'draft_endpoint_clock_evidence' => $this->endpointClockEvidenceFromContext($draftOnly) ?: null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>  $linkageAnalysis
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>
     */
    public function postResponseDiagnostics(
        array $apiDraft,
        array $selectedContext,
        array $linkageAnalysis,
        ?array $response,
    ): array {
        $pre = $this->preSupplierHttpDiagnostics($apiDraft);
        $candidateDigests = [];
        $candidateMismatchCategories = [];
        $candidateTupleMismatchFieldNames = [];
        $candidateCanonicalTupleSegmentDigests = [];
        $candidateCanonicalHashTupleValues = [];
        $candidateTupleFieldComparisons = [];
        $candidateScheduleDescriptorResolution = [];
        $candidateOperatingShapeCategories = [];
        $candidateCanonicalOperatingSlots = [];
        $fareBasisPresence = [];
        $fareBasisApplicabilityMatches = 0;
        $bookingClassCompatibility = 0;
        $expectedSegments = is_array($selectedContext['segments'] ?? null) ? $selectedContext['segments'] : [];
        $expectedFareBasis = is_array($selectedContext['fare_basis_sequence'] ?? null) ? $selectedContext['fare_basis_sequence'] : [];

        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        foreach ($linker->enumerateCandidates($response) as $candidate) {
            $segments = $linker->normalizedCandidateSegmentsForDiagnostics($candidate['itinerary'], $response);
            if ($segments === []) {
                continue;
            }
            $ordinal = (int) $candidate['ordinal'];
            $digest = $this->canonicalSegmentSignature->hashFromSegments($segments);
            $candidateDigests[(string) $ordinal] = $digest;
            $comparison = $this->canonicalSegmentSignature->safeLinkageDigestComparison($expectedSegments, $segments);
            if (($comparison['mismatch_categories'] ?? null) !== null) {
                $candidateMismatchCategories[(string) $ordinal] = $comparison['mismatch_categories'];
            }
            if (($comparison['tuple_mismatch_field_names'] ?? null) !== null) {
                $candidateTupleMismatchFieldNames[(string) $ordinal] = $comparison['tuple_mismatch_field_names'];
            }
            $tupleDigests = $this->canonicalSegmentSignature->scheduleHashTupleSegmentDigests($segments);
            if ($tupleDigests !== []) {
                $candidateCanonicalTupleSegmentDigests[(string) $ordinal] = $tupleDigests;
            }
            $tupleValues = $this->canonicalSegmentSignature->safeCanonicalHashTupleValueRows($segments);
            if ($tupleValues !== []) {
                $candidateCanonicalHashTupleValues[(string) $ordinal] = $tupleValues;
            }
            $fieldComparisons = $this->canonicalSegmentSignature->safeTupleFieldComparisonsBySegment($expectedSegments, $segments);
            if ($fieldComparisons !== []) {
                $candidateTupleFieldComparisons[(string) $ordinal] = $fieldComparisons;
            }
            $resolutionDiagnostics = $linker->buildCandidateScheduleDescriptorResolutionDiagnostics(
                $candidate['itinerary'],
                $response,
                $ordinal,
            );
            if ($resolutionDiagnostics !== []) {
                $candidateScheduleDescriptorResolution[(string) $ordinal] = $resolutionDiagnostics;
            }
            $candidateOperatingShapeCategories[(string) $ordinal] = array_map(
                static fn (array $row): string => (string) ($row['operating_carrier_shape_category'] ?? 'absent'),
                $segments,
            );
            $candidateCanonicalOperatingSlots[(string) $ordinal] = array_map(
                static fn (array $row): string => (string) ($row['canonical_operating_carrier_slot'] ?? ''),
                $segments,
            );
            $fareBasisPresence[(string) $ordinal] = $this->fareBasisPresenceSummary($segments);
            if ($this->fareBasisSequencesMatch($segments, $expectedFareBasis)) {
                $fareBasisApplicabilityMatches++;
            }
            if ($this->bookingClassSequencesMatch(
                $segments,
                is_array($selectedContext['booking_class_sequence'] ?? null) ? $selectedContext['booking_class_sequence'] : [],
            )) {
                $bookingClassCompatibility++;
            }
        }

        $aggregateContract = app(SabreGdsRevalidationLinkageAggregateContract::class);
        $draftContext = $linker->buildSelectedContextFromDraft($this->withoutRuntime($apiDraft));
        $draftSegments = is_array($draftContext['segments'] ?? null) ? $draftContext['segments'] : [];
        $draftFareBasisSequence = is_array($draftContext['fare_basis_sequence'] ?? null) ? $draftContext['fare_basis_sequence'] : [];
        $selectedFareBasisComplete = $aggregateContract->segmentsFareBasisComplete($expectedSegments)
            && $aggregateContract->fareBasisSequenceComplete($expectedFareBasis);
        $draftFareBasisComplete = $aggregateContract->segmentsFareBasisComplete($draftSegments)
            && $aggregateContract->fareBasisSequenceComplete($draftFareBasisSequence);
        $candidateOrdinal = (string) ($linkageAnalysis['selected_response_candidate_ordinal'] ?? '');
        $candidateFareBasisComplete = null;
        if ($candidateOrdinal !== '' && is_array($fareBasisPresence[$candidateOrdinal] ?? null)) {
            $candidateFareBasisComplete = ($fareBasisPresence[$candidateOrdinal]['complete'] ?? false) === true;
        }

        return array_filter(array_merge($pre, [
            'structurally_eligible_candidate_signature_digests' => $candidateDigests !== [] ? $candidateDigests : null,
            'candidate_mismatch_categories' => $candidateMismatchCategories !== [] ? $candidateMismatchCategories : null,
            'candidate_tuple_mismatch_field_names' => $candidateTupleMismatchFieldNames !== [] ? $candidateTupleMismatchFieldNames : null,
            'candidate_canonical_tuple_segment_digests' => $candidateCanonicalTupleSegmentDigests !== [] ? $candidateCanonicalTupleSegmentDigests : null,
            'candidate_canonical_hash_tuple_values' => $candidateCanonicalHashTupleValues !== [] ? $candidateCanonicalHashTupleValues : null,
            'candidate_tuple_field_comparisons' => $candidateTupleFieldComparisons !== [] ? $candidateTupleFieldComparisons : null,
            'candidate_schedule_descriptor_resolution' => $candidateScheduleDescriptorResolution !== [] ? $candidateScheduleDescriptorResolution : null,
            'candidate_operating_carrier_shape_categories' => $candidateOperatingShapeCategories !== [] ? $candidateOperatingShapeCategories : null,
            'candidate_canonical_operating_carrier_slots' => $candidateCanonicalOperatingSlots !== [] ? $candidateCanonicalOperatingSlots : null,
            'normalized_flight_number_shapes' => $this->shapeSummaryFromContext($selectedContext, 'flight_number'),
            'normalized_wall_clock_shapes' => $this->wallClockShapeSummary($expectedSegments),
            'marketing_carrier_shape_categories' => $this->marketingCarrierShapeSummary($expectedSegments),
            'operating_carrier_shape_categories' => $this->operatingCarrierShapeSummary($expectedSegments),
            'fare_basis_presence_by_candidate' => $fareBasisPresence !== [] ? $fareBasisPresence : null,
            'fare_basis_applicability_match_count' => $fareBasisApplicabilityMatches,
            'booking_class_compatibility_count' => $bookingClassCompatibility,
            'selected_fare_basis_sequence_digest' => $this->sequenceDigest($expectedFareBasis),
            'draft_fare_basis_sequence_digest' => $this->sequenceDigest(
                is_array($selectedContext['fare_basis_sequence'] ?? null) ? $selectedContext['fare_basis_sequence'] : [],
            ),
            'selected_fare_basis_complete' => $selectedFareBasisComplete,
            'draft_fare_basis_complete' => $draftFareBasisComplete,
            'candidate_fare_basis_complete' => $candidateFareBasisComplete,
        ]), static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $storedArtifact
     * @return array<string, mixed>
     */
    public function extractStoredArtifactSignatureDiagnostics(array $storedArtifact): array
    {
        $key = self::CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY;
        $bags = [$storedArtifact];
        if (is_array($storedArtifact[$key] ?? null)) {
            $bags[] = [$key => $storedArtifact[$key]];
        }
        if (is_array(data_get($storedArtifact, 'revalidation_diagnostics.'.$key))) {
            $bags[] = [$key => data_get($storedArtifact, 'revalidation_diagnostics.'.$key)];
        }
        if (is_array(data_get($storedArtifact, 'revalidation_diagnostics.canonical_linkage_normalization'))) {
            $bags[] = [
                'canonical_linkage_normalization' => data_get($storedArtifact, 'revalidation_diagnostics.canonical_linkage_normalization'),
            ];
        }

        return app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class)
            ->resolveCanonicalLinkageNormalizationDiagnosticsBlock(array_merge(...$bags));
    }

    /**
     * @param  list<array<string, mixed>>  $canonicalRows
     * @param  list<array<string, mixed>>  $draftSegments
     * @return list<array<string, mixed>>
     */
    public function mergeCanonicalRowsWithDraftFareFields(array $canonicalRows, array $draftSegments): array
    {
        $draftByRoute = [];
        foreach ($draftSegments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $key = strtoupper(trim((string) ($segment['origin'] ?? ''))).'-'.strtoupper(trim((string) ($segment['destination'] ?? '')));
            if ($key !== '-') {
                $draftByRoute[$key] = $segment;
            }
        }

        $merged = [];
        foreach ($canonicalRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = strtoupper(trim((string) ($row['origin'] ?? ''))).'-'.strtoupper(trim((string) ($row['destination'] ?? '')));
            $draft = is_array($draftByRoute[$key] ?? null) ? $draftByRoute[$key] : [];
            $merged[] = array_filter(array_merge($row, [
                'booking_class' => strtoupper(trim((string) (
                    $draft['booking_class']
                    ?? $draft['class_of_service']
                    ?? $row['booking_class']
                    ?? ''
                ))),
                'fare_basis_code' => strtoupper(trim((string) (
                    $draft['fare_basis_code']
                    ?? $row['fare_basis_code']
                    ?? ''
                ))),
                'cabin_code' => strtoupper(trim((string) (
                    $draft['segment_cabin_code']
                    ?? $draft['cabin_code']
                    ?? $row['cabin_code']
                    ?? ''
                ))),
            ]), static fn ($value) => $value !== null && $value !== '');
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    public function withoutRuntime(array $apiDraft): array
    {
        unset($apiDraft[self::RUNTIME_DRAFT_KEY]);

        return $apiDraft;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function safeComponentSummaries(array $rows): array
    {
        $out = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = array_filter([
                'ordinal' => $index + 1,
                'route' => strtoupper(trim((string) ($row['origin'] ?? ''))).'-'.strtoupper(trim((string) ($row['destination'] ?? ''))),
                'marketing_carrier' => $this->canonicalSegmentSignature->normalizeMarketingCarrier($row) ?: null,
                'operating_carrier_present' => $this->canonicalSegmentSignature->normalizeOperatingCarrier(
                    $row,
                    $this->canonicalSegmentSignature->normalizeMarketingCarrier($row),
                ) !== '',
                'flight_number_normalized' => $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($row['flight_number'] ?? '')) ?: null,
                'departure_wall_clock' => $this->canonicalSegmentSignature->normalizeSignatureWallClockSlot((string) ($row['departure_at'] ?? '')) ?: null,
                'arrival_wall_clock' => $this->canonicalSegmentSignature->normalizeSignatureWallClockSlot((string) ($row['arrival_at'] ?? '')) ?: null,
                'booking_class' => $this->canonicalSegmentSignature->normalizeBookingClass((string) ($row['booking_class'] ?? '')) ?: null,
                'fare_basis_present' => trim((string) ($row['fare_basis_code'] ?? '')) !== '',
            ], static fn ($value) => $value !== null && $value !== false);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function safeComponentSummariesFromContext(array $context): array
    {
        return $this->safeComponentSummaries(
            is_array($context['segments'] ?? null) ? $context['segments'] : [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function orderDigestFromRows(array $rows): string
    {
        $routes = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $routes[] = strtoupper(trim((string) ($row['origin'] ?? ''))).'-'.strtoupper(trim((string) ($row['destination'] ?? '')));
        }

        return $this->sequenceDigest($routes);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function orderDigestFromContext(array $context): string
    {
        $routes = is_array($context['route_sequence'] ?? null) ? $context['route_sequence'] : [];

        return $this->sequenceDigest($routes);
    }

    /**
     * @param  list<string>  $values
     */
    private function sequenceDigest(array $values): string
    {
        if ($values === []) {
            return '';
        }

        return hash('sha256', json_encode(array_values($values), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{complete: bool, per_segment_present: list<bool>}
     */
    private function fareBasisPresenceSummary(array $segments): array
    {
        $present = [];
        foreach ($segments as $segment) {
            $present[] = trim((string) ($segment['fare_basis_code'] ?? '')) !== '';
        }

        return [
            'complete' => $present !== [] && ! in_array(false, $present, true),
            'per_segment_present' => $present,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  list<string>  $expected
     */
    private function fareBasisSequencesMatch(array $segments, array $expected): bool
    {
        if ($expected === [] || in_array('', $expected, true)) {
            return true;
        }
        if (count($segments) !== count($expected)) {
            return false;
        }
        foreach ($expected as $index => $value) {
            $value = strtoupper(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if (strtoupper(trim((string) ($segments[$index]['fare_basis_code'] ?? ''))) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  list<string>  $expected
     */
    private function bookingClassSequencesMatch(array $segments, array $expected): bool
    {
        if ($expected === [] || in_array('', $expected, true)) {
            return true;
        }
        if (count($segments) !== count($expected)) {
            return false;
        }
        foreach ($expected as $index => $value) {
            $value = strtoupper(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if (strtoupper(trim((string) ($segments[$index]['booking_class'] ?? ''))) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private function wallClockShapeSummary(array $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $out[] = $this->canonicalSegmentSignature->normalizeSignatureWallClockSlot((string) ($segment['departure_at'] ?? ''))
                .'/'.$this->canonicalSegmentSignature->normalizeSignatureWallClockSlot((string) ($segment['arrival_at'] ?? ''));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private function operatingCarrierShapeSummary(array $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $out[] = $this->canonicalSegmentSignature->operatingCarrierShapeCategory($segment);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private function marketingCarrierShapeSummary(array $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $carrier = $segment['marketing_carrier'] ?? $segment['carrier'] ?? '';
            $out[] = is_array($carrier) ? 'nested' : 'scalar';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function shapeSummaryFromContext(array $context, string $field): array
    {
        $segments = is_array($context['segments'] ?? null) ? $context['segments'] : [];
        $out = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $out[] = $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($segment[$field] ?? ''));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function endpointClockEvidenceFromContext(array $context): array
    {
        $segments = is_array($context['segments'] ?? null) ? $context['segments'] : [];
        $out = [];
        foreach (array_values($segments) as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            if (! is_array($segment['bfm_endpoint_clock_evidence'] ?? null)) {
                continue;
            }
            $out[] = array_merge(
                ['segment_ordinal' => $index + 1],
                $segment['bfm_endpoint_clock_evidence'],
            );
        }

        return $out;
    }
}
