<?php

namespace App\Support\Sabre\Revalidation;

use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;

/**
 * Conservative exact-offer candidate linkage for Sabre BFM revalidation groupedItineraryResponse bodies.
 * Never exposes raw supplier reference IDs, transaction IDs, or full message text.
 */
final class SabreGdsRevalidationResponseCandidateLinker
{
    public const REASON_NO_STRUCTURALLY_ELIGIBLE = 'no_structurally_eligible_candidates';

    public const REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH = 'no_exact_segment_signature_match';

    public const REASON_NO_EXACT_ITINERARY_MATCH = 'no_exact_itinerary_match';

    public const REASON_AMBIGUOUS_EXACT_ITINERARY_MATCH = 'ambiguous_exact_itinerary_match';

    public const REASON_PRICING_INCOMPLETE = 'pricing_incomplete';

    public const REASON_FARE_BASIS_INCOMPATIBLE = 'fare_basis_incompatible';

    public const REASON_BOOKING_CLASS_INCOMPATIBLE = 'booking_class_incompatible';

    public function __construct(
        private readonly SabreRevalidationPayloadBuilder $payloadBuilder,
        private readonly SabreGdsRevalidationCanonicalSegmentSignature $canonicalSegmentSignature,
        private readonly SabreGdsRevalidationGirDescriptorResolver $descriptorResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    public function buildSelectedContextFromDraft(array $apiDraft): array
    {
        $propagation = app(SabreGdsRevalidationCanonicalSignatureRuntimePropagation::class);
        $segments = $propagation->resolveLinkageSegmentsFromDraft($apiDraft);
        if ($segments === []) {
            $segments = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        }
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $bookingClasses = [];
        $fareBasisCodes = [];
        $cabinCodes = [];
        $routeSequence = [];
        $carrierSequence = [];
        $flightNumbers = [];
        $normalizedSegments = [];

        foreach ($segments as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($segment['destination'] ?? '')));
            $carrier = strtoupper(trim((string) ($segment['carrier'] ?? $segment['marketing_carrier'] ?? '')));
            $marketingCarrier = $this->canonicalSegmentSignature->normalizeMarketingCarrier($segment);
            if ($marketingCarrier === '' && $carrier !== '') {
                $marketingCarrier = $carrier;
            }
            $operatingCarrier = $this->canonicalSegmentSignature->canonicalOperatingCarrierForSignature($segment, $marketingCarrier);
            $flightNumber = $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($segment['flight_number'] ?? ''));
            $bookingClass = strtoupper(trim((string) ($segment['booking_class'] ?? $segment['class_of_service'] ?? '')));
            $fareBasis = strtoupper(trim((string) ($segment['fare_basis_code'] ?? '')));
            $cabin = strtoupper(trim((string) ($segment['segment_cabin_code'] ?? $segment['cabin_code'] ?? $segment['cabin'] ?? '')));

            $bookingClasses[] = $bookingClass;
            $fareBasisCodes[] = $fareBasis;
            $cabinCodes[] = $cabin;
            $routeSequence[] = $origin.'-'.$destination;
            $carrierSequence[] = $marketingCarrier !== '' ? $marketingCarrier : $carrier;
            $flightNumbers[] = $flightNumber;

            $normalizedSegments[] = array_filter([
                'ordinal' => $index + 1,
                'origin' => $origin,
                'destination' => $destination,
                'departure_at' => (string) ($segment['departure_at'] ?? $segment['depart_at'] ?? ''),
                'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
                'marketing_carrier' => $marketingCarrier !== '' ? $marketingCarrier : $carrier,
                'operating_carrier' => $operatingCarrier,
                'flight_number' => $flightNumber,
                'booking_class' => $bookingClass,
                'fare_basis_code' => $fareBasis,
                'cabin_code' => $cabin,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        $normalizedSegments = $this->canonicalSegmentSignature->canonicalScheduleIdentityRows($normalizedSegments);

        $fare = is_array($apiDraft['fare'] ?? null) ? $apiDraft['fare'] : [];
        $segmentSignature = $this->canonicalSegmentSignature->hashFromSegments($normalizedSegments);
        $runtime = is_array($apiDraft[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::RUNTIME_DRAFT_KEY] ?? null)
            ? $apiDraft[SabreGdsRevalidationCanonicalSignatureRuntimePropagation::RUNTIME_DRAFT_KEY]
            : [];
        $selectedDigest = trim((string) ($runtime['selected_segment_signature_digest'] ?? ''));
        if ($selectedDigest !== '' && $segmentSignature !== '' && ! hash_equals($selectedDigest, $segmentSignature)) {
            // Authoritative shop schedule rows with draft fare overlay must drive linkage; never a stale precomputed digest.
            $segmentSignature = $this->canonicalSegmentSignature->hashFromSegments($normalizedSegments);
        }

        return array_filter([
            'segment_count' => count($normalizedSegments),
            'route_sequence' => $routeSequence,
            'marketing_carrier_sequence' => $carrierSequence,
            'marketing_flight_number_sequence' => $flightNumbers,
            'booking_class_sequence' => $bookingClasses,
            'fare_basis_sequence' => $fareBasisCodes,
            'cabin_sequence' => $cabinCodes,
            'segments' => $normalizedSegments,
            'validating_carrier' => strtoupper(trim((string) ($apiDraft['validating_carrier'] ?? ''))) ?: null,
            'selected_total' => is_numeric($fare['amount'] ?? null) ? round((float) $fare['amount'], 2) : null,
            'selected_currency' => strtoupper(trim((string) ($fare['currency'] ?? ''))) ?: null,
            'segment_signature' => $segmentSignature,
            'canonical_signature_version' => SabreGdsRevalidationCanonicalSegmentSignature::VERSION,
            'selected_segment_signature_digest' => $selectedDigest !== '' ? $selectedDigest : null,
            'draft_segment_signature_digest' => trim((string) ($runtime['draft_segment_signature_digest'] ?? '')) ?: null,
            'selected_draft_signature_equal' => array_key_exists('selected_draft_signature_equal', $runtime)
                ? (bool) $runtime['selected_draft_signature_equal']
                : null,
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    public function normalizedCandidateSegmentsForDiagnostics(array $itinerary, ?array $json): array
    {
        return $this->resolveCandidateSegments($itinerary, $json);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $selectedContext
     * @return array<string, mixed>
     */
    public function analyze(?array $json, array $selectedContext, ?int $declaredResponseCandidateCount = null): array
    {
        $candidates = $this->enumerateCandidates($json);
        $expectedSegmentCount = (int) ($selectedContext['segment_count'] ?? 0);
        $enumeratedCandidateCount = count($candidates);
        $responseCandidateCount = $declaredResponseCandidateCount !== null && $declaredResponseCandidateCount >= 0
            ? $declaredResponseCandidateCount
            : $enumeratedCandidateCount;

        $structurallyEligible = 0;
        $exactSegmentSignatureMatches = 0;
        $exactItineraryMatches = 0;
        $pricingCompatibleMatches = 0;
        $fareBasisCompatibleMatches = 0;
        $bookingClassCompatibleMatches = 0;
        $usableMatches = [];
        $missingComponents = [];
        $conflictingComponents = [];

        foreach ($candidates as $candidate) {
            $evidence = $this->evaluateCandidate($candidate, $selectedContext, $json);
            if (($evidence['structurally_eligible'] ?? false) === true) {
                $structurallyEligible++;
            }
            if (($evidence['exact_segment_signature_match'] ?? false) === true) {
                $exactSegmentSignatureMatches++;
            }
            if (($evidence['exact_itinerary_match'] ?? false) === true) {
                $exactItineraryMatches++;
            }
            if (($evidence['pricing_compatible'] ?? false) === true) {
                $pricingCompatibleMatches++;
            }
            if (($evidence['fare_basis_compatible'] ?? false) === true) {
                $fareBasisCompatibleMatches++;
            }
            if (($evidence['booking_class_compatible'] ?? false) === true) {
                $bookingClassCompatibleMatches++;
            }
            if (($evidence['usable_linkage_match'] ?? false) === true) {
                $usableMatches[] = [
                    'ordinal' => $candidate['ordinal'],
                    'evidence' => $this->safeCandidateEvidence($evidence),
                ];
            }
        }

        $uniqueUsable = count($usableMatches);
        $ambiguous = $uniqueUsable > 1;
        $selectedOrdinal = $uniqueUsable === 1 ? (int) $usableMatches[0]['ordinal'] : null;
        $linkageFailureReason = $this->resolveLinkageFailureReason(
            $candidates,
            $structurallyEligible,
            $exactSegmentSignatureMatches,
            $exactItineraryMatches,
            $pricingCompatibleMatches,
            $fareBasisCompatibleMatches,
            $bookingClassCompatibleMatches,
            $uniqueUsable,
            $ambiguous,
            $expectedSegmentCount,
            $missingComponents,
            $conflictingComponents,
        );

        $normalizationDiagnostics = ($uniqueUsable !== 1 && $expectedSegmentCount > 0)
            ? app(SabreGdsRevalidationLinkageNormalizationDiagnostics::class)->buildForAnalysis($selectedContext, [
                'selected_response_candidate_ordinal' => $selectedOrdinal,
                'structurally_eligible_candidate_count' => $structurallyEligible,
                'linkage_failure_reason_code' => $linkageFailureReason,
                'linkage_missing_components' => $missingComponents !== [] ? array_values(array_unique($missingComponents)) : null,
            ], $json)
            : null;

        return array_filter([
            'response_candidate_count' => $responseCandidateCount,
            'enumerated_candidate_count' => $enumeratedCandidateCount,
            'structurally_eligible_candidate_count' => $structurallyEligible,
            'exact_segment_signature_match_count' => $exactSegmentSignatureMatches,
            'exact_itinerary_match_count' => $exactItineraryMatches,
            'pricing_compatible_match_count' => $pricingCompatibleMatches,
            'fare_basis_compatible_match_count' => $fareBasisCompatibleMatches,
            'booking_class_compatible_match_count' => $bookingClassCompatibleMatches,
            'unique_usable_linkage_match_count' => $uniqueUsable,
            'ambiguous_linkage_match_count' => $ambiguous ? $uniqueUsable : 0,
            'selected_response_candidate_ordinal' => $selectedOrdinal,
            'usable_fare_linkage' => $uniqueUsable === 1,
            'linkage_failure_reason_code' => $linkageFailureReason,
            'linkage_missing_components' => $missingComponents !== [] ? array_values(array_unique($missingComponents)) : null,
            'linkage_conflicting_components' => $conflictingComponents !== [] ? array_values(array_unique($conflictingComponents)) : null,
            'pricing_complete' => $selectedOrdinal !== null
                ? (($usableMatches[0]['evidence']['pricing_complete'] ?? false) === true)
                : null,
            'selected_total' => $selectedContext['selected_total'] ?? null,
            'selected_currency' => $selectedContext['selected_currency'] ?? null,
            'linkage_normalization_diagnostics' => $normalizationDiagnostics,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array{ordinal: int, itinerary: array<string, mixed>}>
     */
    public function enumerateCandidates(?array $json): array
    {
        if (! is_array($json) || $json === []) {
            return [];
        }

        $groups = data_get($json, 'groupedItineraryResponse.itineraryGroups');
        if (! is_array($groups)) {
            return [];
        }

        $candidates = [];
        $ordinal = 0;
        foreach ($groups as $group) {
            $itineraries = is_array($group['itineraries'] ?? null) ? $group['itineraries'] : [];
            foreach ($itineraries as $itinerary) {
                if (! is_array($itinerary)) {
                    continue;
                }
                $ordinal++;
                $candidates[] = [
                    'ordinal' => $ordinal,
                    'itinerary' => $itinerary,
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $linkageAnalysis
     * @return array<string, mixed>
     */
    public function extractLinkageForSelectedCandidate(?array $json, array $linkageAnalysis): array
    {
        $ordinal = (int) ($linkageAnalysis['selected_response_candidate_ordinal'] ?? 0);
        if ($ordinal < 1 || ! is_array($json) || $json === []) {
            return [];
        }

        foreach ($this->enumerateCandidates($json) as $candidate) {
            if ((int) $candidate['ordinal'] !== $ordinal) {
                continue;
            }

            return $this->payloadBuilder->extractFareLinkage($json, $candidate['itinerary']);
        }

        return [];
    }

    /**
     * @param  array{ordinal: int, itinerary: array<string, mixed>}  $candidate
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function evaluateCandidate(array $candidate, array $selectedContext, ?array $json): array
    {
        $expectedSegments = is_array($selectedContext['segments'] ?? null) ? $selectedContext['segments'] : [];
        $expectedCount = count($expectedSegments);
        $candidateSegments = $this->resolveCandidateSegments($candidate['itinerary'], $json);
        $segmentCount = count($candidateSegments);

        $structurallyEligible = $expectedCount > 0
            && $segmentCount === $expectedCount
            && $this->routeSequenceMatches($candidateSegments, $expectedSegments)
            && $this->carrierSequenceMatches($candidateSegments, $expectedSegments)
            && $this->flightNumberSequenceMatches($candidateSegments, $expectedSegments);

        $segmentSignature = $this->canonicalSegmentSignature->hashFromSegments($candidateSegments);
        $exactSegmentSignature = $structurallyEligible
            && $segmentSignature !== ''
            && $segmentSignature === (string) ($selectedContext['segment_signature'] ?? '')
            && $this->temporalContinuityMatches($candidateSegments, $expectedSegments);

        $bookingClassCompatible = $structurallyEligible
            && $this->sequenceMatches(
                array_map(static fn (array $row): string => (string) ($row['booking_class'] ?? ''), $candidateSegments),
                is_array($selectedContext['booking_class_sequence'] ?? null) ? $selectedContext['booking_class_sequence'] : [],
            );

        $fareBasisCompatible = $structurallyEligible
            && $this->sequenceMatches(
                array_map(static fn (array $row): string => (string) ($row['fare_basis_code'] ?? ''), $candidateSegments),
                is_array($selectedContext['fare_basis_sequence'] ?? null) ? $selectedContext['fare_basis_sequence'] : [],
            );

        $cabinCompatible = $structurallyEligible
            && $this->sequenceMatches(
                array_map(static fn (array $row): string => (string) ($row['cabin_code'] ?? ''), $candidateSegments),
                is_array($selectedContext['cabin_sequence'] ?? null) ? $selectedContext['cabin_sequence'] : [],
            );

        $pricing = $this->resolveCandidatePricing($candidate['itinerary'], $json);
        $pricingCompatible = $pricing['total'] !== null && $pricing['currency'] !== null;
        $currencyCompatible = $pricing['currency'] === null
            || ($selectedContext['selected_currency'] ?? null) === null
            || strtoupper((string) $selectedContext['selected_currency']) === strtoupper((string) $pricing['currency']);

        $exactItineraryMatch = $exactSegmentSignature
            && $bookingClassCompatible
            && $fareBasisCompatible
            && $cabinCompatible
            && $currencyCompatible;

        $usableLinkageMatch = $exactItineraryMatch && $pricingCompatible;

        return [
            'candidate_ordinal' => $candidate['ordinal'],
            'segment_count' => $segmentCount,
            'segment_route_sequence' => array_map(
                static fn (array $row): string => ($row['origin'] ?? '').'-'.($row['destination'] ?? ''),
                $candidateSegments
            ),
            'structurally_eligible' => $structurallyEligible,
            'exact_segment_signature_match' => $exactSegmentSignature,
            'exact_itinerary_match' => $exactItineraryMatch,
            'pricing_compatible' => $pricingCompatible,
            'fare_basis_compatible' => $fareBasisCompatible,
            'booking_class_compatible' => $bookingClassCompatible,
            'usable_linkage_match' => $usableLinkageMatch,
            'pricing_complete' => $pricingCompatible,
            'revalidated_total' => $pricing['total'],
            'revalidated_currency' => $pricing['currency'],
            'pricing_source_location' => $pricing['source_location'],
            'brand_context_present' => $this->brandContextPresent($candidate['itinerary']),
            'itinerary_index_coverage' => $segmentCount > 0,
            'leg_reference_coverage' => is_array($candidate['itinerary']['legs'] ?? null) && $candidate['itinerary']['legs'] !== [],
            'schedule_reference_coverage' => $segmentCount > 0,
            'fare_component_reference_coverage' => $this->fareComponentReferenceCoverage($candidate['itinerary']),
            'selected_segment_signature_match' => $exactSegmentSignature,
            'selected_source_context_match' => $exactItineraryMatch,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function safeCandidateEvidence(array $evidence): array
    {
        return array_filter([
            'candidate_ordinal' => $evidence['candidate_ordinal'] ?? null,
            'segment_count' => $evidence['segment_count'] ?? null,
            'segment_route_sequence' => $evidence['segment_route_sequence'] ?? null,
            'exact_segment_signature_match' => ($evidence['exact_segment_signature_match'] ?? false) === true,
            'exact_itinerary_match' => ($evidence['exact_itinerary_match'] ?? false) === true,
            'pricing_complete' => ($evidence['pricing_complete'] ?? false) === true,
            'revalidated_total' => $evidence['revalidated_total'] ?? null,
            'revalidated_currency' => $evidence['revalidated_currency'] ?? null,
            'pricing_source_location' => $evidence['pricing_source_location'] ?? null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    public function buildCandidateScheduleDescriptorResolutionDiagnostics(
        array $itinerary,
        ?array $json,
        int $candidateOrdinal,
    ): array {
        $gir = is_array($json['groupedItineraryResponse'] ?? null) ? $json['groupedItineraryResponse'] : [];
        $scheduleSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['scheduleDescs'] ?? []),
        );
        $legSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['legDescs'] ?? []),
        );
        $allScheduleRows = $scheduleSlice['rows'];
        $qr629Summaries = [];
        foreach ($allScheduleRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $summary = $this->descriptorResolver->safeScheduleSummary($row, $this->canonicalSegmentSignature);
            if (($summary['origin'] ?? '') === 'LHE'
                && ($summary['destination'] ?? '') === 'DOH'
                && $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($summary['flight_number'] ?? '')) === '629') {
                $qr629Summaries[] = $summary;
            }
        }

        $segments = [];
        $segmentOrdinal = 0;
        foreach (is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : [] as $legWrap) {
            $legRef = $this->descriptorResolver->legDescriptorRefFromWrap($legWrap);
            $leg = $this->descriptorResolver->resolveDescriptor($legSlice, $legRef);
            $legSchedules = is_array($leg) ? ($leg['schedules'] ?? null) : null;
            if (! is_array($legSchedules) || $legSchedules === []) {
                $legSchedules = is_array($legWrap) ? ($legWrap['schedules'] ?? null) : null;
            }
            if (! is_array($legSchedules)) {
                continue;
            }
            foreach ($legSchedules as $scheduleWrap) {
                $segmentOrdinal++;
                $scheduleRef = $this->descriptorResolver->scheduleRefFromLegScheduleWrap($scheduleWrap);
                $schedule = $this->descriptorResolver->resolveDescriptor($scheduleSlice, $scheduleRef);
                $lookupMode = $this->descriptorResolver->lookupModeForResolution($scheduleSlice, $scheduleRef, $schedule);
                $summary = is_array($schedule)
                    ? $this->descriptorResolver->safeScheduleSummary($schedule, $this->canonicalSegmentSignature)
                    : [];
                $duplicateCount = 0;
                if ($summary !== []) {
                    $duplicateCount = $this->descriptorResolver->countMatchingScheduleSummaries(
                        $allScheduleRows,
                        $this->canonicalSegmentSignature,
                        (string) ($summary['origin'] ?? ''),
                        (string) ($summary['destination'] ?? ''),
                        (string) ($summary['marketing_carrier'] ?? ''),
                        (string) ($summary['flight_number'] ?? ''),
                    );
                }
                $segments[] = array_filter([
                    'candidate_ordinal' => $candidateOrdinal,
                    'segment_ordinal' => $segmentOrdinal,
                    'leg_reference_category' => $this->descriptorResolver->referenceWrapCategory($legWrap, 'leg'),
                    'schedule_reference_category' => $this->descriptorResolver->referenceWrapCategory($scheduleWrap, 'schedule'),
                    'resolved_descriptor_ordinal_category' => $this->descriptorResolver->resolvedDescriptorOrdinalCategory($scheduleRef),
                    'lookup_mode' => $lookupMode,
                    'resolution_ambiguity_flag' => in_array($scheduleRef, $scheduleSlice['ambiguous_keys'] ?? [], true)
                        || $duplicateCount > 1,
                    'duplicate_matching_schedule_count' => $duplicateCount > 0 ? $duplicateCount : null,
                    'schedule_summary' => $summary !== [] ? $summary : null,
                ], static fn ($value) => $value !== null && $value !== false);
            }
        }

        return array_filter([
            'candidate_ordinal' => $candidateOrdinal,
            'matching_qr_629_lhe_doh_schedule_summaries' => $qr629Summaries !== [] ? $qr629Summaries : null,
            'segment_resolution' => $segments !== [] ? $segments : null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, string>>
     */
    private function resolveCandidateSegments(array $itinerary, ?array $json): array
    {
        $gir = is_array($json['groupedItineraryResponse'] ?? null) ? $json['groupedItineraryResponse'] : [];
        $scheduleSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['scheduleDescs'] ?? []),
        );
        $legSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['legDescs'] ?? []),
        );
        $segments = [];

        foreach (is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : [] as $legWrap) {
            $legRef = $this->descriptorResolver->legDescriptorRefFromWrap($legWrap);
            $leg = $this->descriptorResolver->resolveDescriptor($legSlice, $legRef);
            if (! is_array($leg)) {
                return [];
            }
            $legSchedules = is_array($leg['schedules'] ?? null) ? $leg['schedules'] : [];
            if ($legSchedules === [] && is_array($legWrap)) {
                $legSchedules = is_array($legWrap['schedules'] ?? null) ? $legWrap['schedules'] : [];
            }
            foreach ($legSchedules as $scheduleWrap) {
                $scheduleRef = $this->descriptorResolver->scheduleRefFromLegScheduleWrap($scheduleWrap);
                $schedule = $this->descriptorResolver->resolveDescriptor($scheduleSlice, $scheduleRef);
                if (! is_array($schedule)) {
                    return [];
                }
                $segments[] = $this->canonicalSegmentSignature->segmentRowFromScheduleDesc($schedule, [
                    'schedule_desc_ref' => (string) $scheduleRef,
                    'schedule_desc_lookup_mode' => $this->descriptorResolver->lookupModeForResolution($scheduleSlice, $scheduleRef, $schedule),
                    'schedule_desc_ref_digest' => $this->descriptorResolver->descriptorLookupKeyDigest($schedule) ?: null,
                ]);
            }
        }

        if ($segments === []) {
            return [];
        }

        $fareOverlay = $this->resolveFareOverlayByScheduleIndex($itinerary, $json, $segments);
        foreach ($segments as $index => &$segment) {
            $overlay = is_array($fareOverlay[$index] ?? null) ? $fareOverlay[$index] : [];
            foreach (['booking_class', 'fare_basis_code', 'cabin_code'] as $key) {
                $value = trim((string) ($overlay[$key] ?? ''));
                if ($value !== '') {
                    $segment[$key] = $value;
                }
            }
        }
        unset($segment);

        return $this->canonicalSegmentSignature->canonicalScheduleIdentityRows($segments);
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @param  list<array<string, mixed>>  $scheduleSegments
     * @return list<array<string, string>>
     */
    private function resolveFareOverlayByScheduleIndex(array $itinerary, ?array $json, array $scheduleSegments): array
    {
        $count = count($scheduleSegments);
        if ($count === 0) {
            return [];
        }

        $overlay = array_fill(0, $count, []);
        $gir = is_array($json['groupedItineraryResponse'] ?? null) ? $json['groupedItineraryResponse'] : [];
        $fareComponentDescs = $this->indexDescs($gir['fareComponentDescs'] ?? []);
        $scheduleRefToIndex = $this->scheduleRefToIndexMap($itinerary, $json);

        foreach ($this->passengerInfoRowsFromItinerary($itinerary) as $passengerInfo) {
            foreach (is_array($passengerInfo['fareComponents'] ?? null) ? $passengerInfo['fareComponents'] : [] as $fareComponent) {
                if (! is_array($fareComponent)) {
                    continue;
                }
                $componentFareBasis = $this->resolveFareBasisFromFareComponent($fareComponent, $fareComponentDescs);
                $componentBookingClass = strtoupper(trim((string) (
                    $fareComponent['bookingCode']
                    ?? $fareComponent['resBookDesigCode']
                    ?? $fareComponent['classOfService']
                    ?? ''
                )));
                $componentCabin = strtoupper(trim((string) ($fareComponent['cabinCode'] ?? $fareComponent['cabin'] ?? '')));
                $segmentWraps = is_array($fareComponent['segments'] ?? null) ? $fareComponent['segments'] : [];
                $positionalIndices = null;
                if ($segmentWraps !== [] && ! $this->fareComponentSegmentsHaveApplicabilityHints($segmentWraps)) {
                    if (count($segmentWraps) === $count) {
                        $positionalIndices = range(0, $count - 1);
                    }
                }

                foreach ($segmentWraps as $wrapPosition => $segWrap) {
                    $seg = is_array($segWrap['segment'] ?? null) ? $segWrap['segment'] : (is_array($segWrap) ? $segWrap : []);
                    if ($seg === []) {
                        continue;
                    }
                    $indices = $this->resolveFareComponentSegmentIndices($seg, $scheduleSegments, $scheduleRefToIndex);
                    if ($indices === [] && is_array($positionalIndices) && isset($positionalIndices[$wrapPosition])) {
                        $indices = [$positionalIndices[$wrapPosition]];
                    }
                    if ($indices === []) {
                        continue;
                    }
                    $segFareBasis = strtoupper(trim((string) ($seg['fareBasisCode'] ?? $seg['fareBasis'] ?? $componentFareBasis)));
                    $segBooking = strtoupper(trim((string) ($seg['bookingCode'] ?? $seg['resBookDesigCode'] ?? $seg['classOfService'] ?? $componentBookingClass)));
                    $segCabin = strtoupper(trim((string) ($seg['cabinCode'] ?? $seg['cabin'] ?? $componentCabin)));
                    foreach ($indices as $index) {
                        if (! isset($overlay[$index]) || ! is_array($overlay[$index])) {
                            continue;
                        }
                        if ($segFareBasis !== '') {
                            if (
                                isset($overlay[$index]['fare_basis_code'])
                                && $overlay[$index]['fare_basis_code'] !== ''
                                && $overlay[$index]['fare_basis_code'] !== $segFareBasis
                            ) {
                                $overlay[$index]['fare_basis_code'] = '';
                                $overlay[$index]['fare_basis_ambiguous'] = true;
                            } elseif (($overlay[$index]['fare_basis_ambiguous'] ?? false) !== true) {
                                $overlay[$index]['fare_basis_code'] = $segFareBasis;
                            }
                        }
                        if ($segBooking !== '') {
                            $overlay[$index]['booking_class'] = $segBooking;
                        }
                        if ($segCabin !== '') {
                            $overlay[$index]['cabin_code'] = $segCabin;
                        }
                    }
                }
            }
        }

        return $overlay;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return list<array<string, mixed>>
     */
    private function passengerInfoRowsFromItinerary(array $itinerary): array
    {
        $rows = [];
        $lists = [
            data_get($itinerary, 'pricingInformation.0.fare.passengerInfoList'),
            data_get($itinerary, 'pricingInformation.0.passengerInfoList'),
        ];
        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $wrap) {
                if (! is_array($wrap)) {
                    continue;
                }
                $pi = is_array($wrap['passengerInfo'] ?? null) ? $wrap['passengerInfo'] : [];
                if ($pi === []) {
                    continue;
                }
                $type = strtoupper(trim((string) ($pi['passengerType'] ?? $pi['passengerTypeCode'] ?? '')));
                $rows[] = ['type' => $type, 'passengerInfo' => $pi];
            }
            if ($rows !== []) {
                break;
            }
        }

        foreach ($rows as $row) {
            if (in_array($row['type'], ['ADT', 'ADULT', ''], true)) {
                return [$row['passengerInfo']];
            }
        }

        return isset($rows[0]['passengerInfo']) && is_array($rows[0]['passengerInfo'])
            ? [$rows[0]['passengerInfo']]
            : [];
    }

    /**
     * @param  array<string, mixed>  $fareComponent
     * @param  array<string, array<string, mixed>>  $fareComponentDescs
     */
    private function resolveFareBasisFromFareComponent(array $fareComponent, array $fareComponentDescs): string
    {
        foreach (['fareBasisCode', 'fareBasis'] as $key) {
            $code = strtoupper(trim((string) ($fareComponent[$key] ?? '')));
            if ($code !== '') {
                return $code;
            }
        }
        foreach (['ref', 'fareComponentDescRef', 'fareComponentDescNumber', 'fareComponentDescIndex'] as $refKey) {
            if (! isset($fareComponent[$refKey]) || ! is_scalar($fareComponent[$refKey])) {
                continue;
            }
            $desc = $fareComponentDescs[(string) $fareComponent[$refKey]] ?? null;
            if (! is_array($desc)) {
                continue;
            }
            foreach (['fareBasisCode', 'fareBasis'] as $key) {
                $code = strtoupper(trim((string) ($desc[$key] ?? '')));
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @return array<string, int>
     */
    private function scheduleRefToIndexMap(array $itinerary, ?array $json): array
    {
        $map = [];
        $gir = is_array($json['groupedItineraryResponse'] ?? null) ? $json['groupedItineraryResponse'] : [];
        $scheduleSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['scheduleDescs'] ?? []),
        );
        $legSlice = $this->descriptorResolver->buildResolutionSlice(
            $this->descriptorResolver->listDescRows($gir['legDescs'] ?? []),
        );
        $index = 0;
        foreach (is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : [] as $legWrap) {
            $legRef = $this->descriptorResolver->legDescriptorRefFromWrap($legWrap);
            $leg = $this->descriptorResolver->resolveDescriptor($legSlice, $legRef);
            if (! is_array($leg)) {
                continue;
            }
            $legSchedules = is_array($leg['schedules'] ?? null) ? $leg['schedules'] : [];
            if ($legSchedules === [] && is_array($legWrap)) {
                $legSchedules = is_array($legWrap['schedules'] ?? null) ? $legWrap['schedules'] : [];
            }
            foreach ($legSchedules as $scheduleWrap) {
                $scheduleRef = $this->descriptorResolver->scheduleRefFromLegScheduleWrap($scheduleWrap);
                if ($scheduleRef >= 0) {
                    $map[(string) $scheduleRef] = $index;
                }
                $index++;
            }
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $segmentWraps
     */
    private function fareComponentSegmentsHaveApplicabilityHints(array $segmentWraps): bool
    {
        foreach ($segmentWraps as $segWrap) {
            $seg = is_array($segWrap['segment'] ?? null) ? $segWrap['segment'] : (is_array($segWrap) ? $segWrap : []);
            if ($seg === []) {
                continue;
            }
            foreach (['ref', 'scheduleRef', 'scheduleDescRef', 'id', 'segmentNumber', 'segmentId', 'number'] as $key) {
                if (array_key_exists($key, $seg)) {
                    return true;
                }
            }
            $origin = strtoupper(trim((string) (data_get($seg, 'departure.locationCode') ?? data_get($seg, 'departure.airport') ?? '')));
            $destination = strtoupper(trim((string) (data_get($seg, 'arrival.locationCode') ?? data_get($seg, 'arrival.airport') ?? '')));
            if ($origin !== '' && $destination !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $seg
     * @param  list<array<string, mixed>>  $scheduleSegments
     * @param  array<string, int>  $scheduleRefToIndex
     * @return list<int>
     */
    private function resolveFareComponentSegmentIndices(array $seg, array $scheduleSegments, array $scheduleRefToIndex): array
    {
        foreach (['ref', 'scheduleRef', 'scheduleDescRef'] as $refKey) {
            if (! isset($seg[$refKey]) || ! is_scalar($seg[$refKey])) {
                continue;
            }
            $idx = $scheduleRefToIndex[(string) $seg[$refKey]] ?? null;
            if (is_int($idx)) {
                return [$idx];
            }
        }

        foreach (['id', 'segmentNumber', 'segmentId', 'number'] as $idKey) {
            if (! isset($seg[$idKey]) || ! is_numeric($seg[$idKey])) {
                continue;
            }
            $ref = (int) $seg[$idKey];
            $idx = $scheduleRefToIndex[(string) $ref] ?? null;
            if (is_int($idx)) {
                return [$idx];
            }
        }

        $origin = strtoupper(trim((string) (data_get($seg, 'departure.locationCode') ?? data_get($seg, 'departure.airport') ?? '')));
        $destination = strtoupper(trim((string) (data_get($seg, 'arrival.locationCode') ?? data_get($seg, 'arrival.airport') ?? '')));
        if ($origin !== '' && $destination !== '') {
            $matches = [];
            foreach ($scheduleSegments as $index => $scheduleSegment) {
                if (! is_array($scheduleSegment)) {
                    continue;
                }
                if (($scheduleSegment['origin'] ?? '') === $origin
                    && ($scheduleSegment['destination'] ?? '') === $destination) {
                    $matches[] = (int) $index;
                }
            }
            if (count($matches) === 1) {
                return $matches;
            }
        }

        return [];
    }

    /**
     * @param  mixed  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexDescs(mixed $rows): array
    {
        $slice = $this->descriptorResolver->buildResolutionSlice($this->descriptorResolver->listDescRows($rows));
        $indexed = [];
        foreach ($slice['lookup'] as $key => $row) {
            $indexed[(string) $key] = $row;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, string>>  $candidateSegments
     * @param  list<array<string, mixed>>  $expectedSegments
     */
    private function routeSequenceMatches(array $candidateSegments, array $expectedSegments): bool
    {
        if (count($candidateSegments) !== count($expectedSegments)) {
            return false;
        }
        foreach ($expectedSegments as $index => $expected) {
            $candidate = $candidateSegments[$index] ?? null;
            if (! is_array($candidate)) {
                return false;
            }
            if (($candidate['origin'] ?? '') !== strtoupper(trim((string) ($expected['origin'] ?? '')))) {
                return false;
            }
            if (($candidate['destination'] ?? '') !== strtoupper(trim((string) ($expected['destination'] ?? '')))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, string>>  $candidateSegments
     * @param  list<array<string, mixed>>  $expectedSegments
     */
    private function carrierSequenceMatches(array $candidateSegments, array $expectedSegments): bool
    {
        foreach ($expectedSegments as $index => $expected) {
            $candidate = $candidateSegments[$index] ?? null;
            if (! is_array($candidate)) {
                return false;
            }
            $expectedCarrier = strtoupper(trim((string) ($expected['marketing_carrier'] ?? '')));
            if ($expectedCarrier !== '' && ($candidate['marketing_carrier'] ?? '') !== $expectedCarrier) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, string>>  $candidateSegments
     * @param  list<array<string, mixed>>  $expectedSegments
     */
    private function flightNumberSequenceMatches(array $candidateSegments, array $expectedSegments): bool
    {
        if ($candidateSegments === []) {
            return false;
        }
        foreach ($expectedSegments as $index => $expected) {
            $candidate = $candidateSegments[$index] ?? null;
            if (! is_array($candidate)) {
                return false;
            }
            $expectedFlight = $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($expected['flight_number'] ?? ''));
            $candidateFlight = $this->canonicalSegmentSignature->normalizeFlightNumber((string) ($candidate['flight_number'] ?? ''));
            if ($expectedFlight === '' || $candidateFlight === '') {
                return false;
            }
            if ($expectedFlight !== $candidateFlight) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, string>>  $candidateSegments
     * @param  list<array<string, mixed>>  $expectedSegments
     */
    private function temporalContinuityMatches(array $candidateSegments, array $expectedSegments): bool
    {
        foreach ($expectedSegments as $index => $expected) {
            $candidate = $candidateSegments[$index] ?? null;
            if (! is_array($candidate)) {
                return false;
            }
            foreach (['departure_at', 'arrival_at'] as $key) {
                $expectedValue = $this->canonicalSegmentSignature->comparableWallClock((string) ($expected[$key] ?? ''));
                $candidateValue = $this->canonicalSegmentSignature->comparableWallClock((string) ($candidate[$key] ?? ''));
                if ($expectedValue !== '' && $candidateValue !== '' && $expectedValue !== $candidateValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $actual
     * @param  list<string>  $expected
     */
    private function sequenceMatches(array $actual, array $expected): bool
    {
        if ($expected === [] || in_array('', $expected, true)) {
            return true;
        }
        if (count($actual) !== count($expected)) {
            return false;
        }
        foreach ($expected as $index => $value) {
            $value = strtoupper(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if (strtoupper(trim((string) ($actual[$index] ?? ''))) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>|null  $json
     * @return array{total: ?float, currency: ?string, source_location: ?string}
     */
    private function resolveCandidatePricing(array $itinerary, ?array $json): array
    {
        $paths = [
            'pricingInformation.0.fare.totalFare.totalPrice' => 'pricingInformation.0.fare.totalFare',
            'pricingInformation.0.fare.totalFare.amount' => 'pricingInformation.0.fare.totalFare',
        ];
        $total = null;
        $currency = null;
        $source = null;
        foreach ($paths as $amountPath => $currencyBase) {
            $value = data_get($itinerary, $amountPath);
            if (is_numeric($value) && (float) $value > 0) {
                $total = (float) $value;
                $source = $amountPath;
                $currency = strtoupper(trim((string) (
                    data_get($itinerary, $currencyBase.'.currencyCode')
                    ?? data_get($itinerary, $currencyBase.'.currency')
                    ?? ''
                )));
                break;
            }
        }

        if ($total === null && is_array($json)) {
            $linkage = $this->payloadBuilder->extractFareLinkage($json, $itinerary);
            if (is_numeric($linkage['revalidated_total'] ?? null) && (float) $linkage['revalidated_total'] > 0) {
                $total = (float) $linkage['revalidated_total'];
                $currency = strtoupper(trim((string) ($linkage['revalidated_currency'] ?? '')));
                $source = 'extracted_linkage';
            }
        }

        return [
            'total' => $total,
            'currency' => $currency !== '' ? $currency : null,
            'source_location' => $source,
        ];
    }

    private function brandContextPresent(array $itinerary): bool
    {
        return trim((string) data_get($itinerary, 'pricingInformation.0.brand.code')) !== ''
            || trim((string) data_get($itinerary, 'pricingInformation.0.fare.brandCode')) !== '';
    }

    private function fareComponentReferenceCoverage(array $itinerary): bool
    {
        $fareComponents = data_get($itinerary, 'pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents');

        return is_array($fareComponents) && $fareComponents !== [];
    }

    /**
     * @param  list<array{ordinal: int, itinerary: array<string, mixed>}>  $candidates
     * @param  list<string>  $missingComponents
     * @param  list<string>  $conflictingComponents
     */
    private function resolveLinkageFailureReason(
        array $candidates,
        int $structurallyEligible,
        int $exactSegmentSignatureMatches,
        int $exactItineraryMatches,
        int $pricingCompatibleMatches,
        int $fareBasisCompatibleMatches,
        int $bookingClassCompatibleMatches,
        int $uniqueUsable,
        bool $ambiguous,
        int $expectedSegmentCount,
        array &$missingComponents,
        array &$conflictingComponents,
    ): ?string {
        if ($candidates === []) {
            $missingComponents[] = 'response_candidates';

            return self::REASON_NO_STRUCTURALLY_ELIGIBLE;
        }
        if ($expectedSegmentCount < 1) {
            $missingComponents[] = 'selected_segment_context';

            return self::REASON_NO_STRUCTURALLY_ELIGIBLE;
        }
        if ($structurallyEligible === 0) {
            $missingComponents[] = 'segment_structure';

            return self::REASON_NO_STRUCTURALLY_ELIGIBLE;
        }
        if ($exactSegmentSignatureMatches === 0) {
            $missingComponents[] = 'segment_signature';

            return self::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH;
        }
        if ($bookingClassCompatibleMatches === 0) {
            $missingComponents[] = 'booking_class_sequence';

            return self::REASON_BOOKING_CLASS_INCOMPATIBLE;
        }
        if ($fareBasisCompatibleMatches === 0) {
            $missingComponents[] = 'fare_basis_sequence';

            return self::REASON_FARE_BASIS_INCOMPATIBLE;
        }
        if ($exactItineraryMatches === 0) {
            $missingComponents[] = 'itinerary_context';

            return self::REASON_NO_EXACT_ITINERARY_MATCH;
        }
        if ($pricingCompatibleMatches === 0) {
            $missingComponents[] = 'pricing_total_currency';

            return self::REASON_PRICING_INCOMPLETE;
        }
        if ($ambiguous) {
            $conflictingComponents[] = 'multiple_exact_itinerary_matches';

            return self::REASON_AMBIGUOUS_EXACT_ITINERARY_MATCH;
        }
        if ($uniqueUsable !== 1) {
            $missingComponents[] = 'unique_usable_linkage';

            return self::REASON_NO_EXACT_ITINERARY_MATCH;
        }

        return null;
    }
}
