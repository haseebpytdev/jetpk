<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingItineraryMapper;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier;

/**
 * Safe segment counts for post-cancel retrieve closure (no raw supplier payloads).
 */
final class SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment
{
    public function __construct(
        private readonly SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier $zeroSegmentClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $syncResult
     * @param  array<string, mixed>|null  $closureContext
     * @return array<string, mixed>
     */
    public function assessFromSyncResult(array $syncResult, ?array $closureContext = null): array
    {
        $preview = is_array($syncResult['map_preview'] ?? null)
            ? $syncResult['map_preview']
            : (is_array($syncResult['pnr_itinerary_sync_preview'] ?? null)
                ? ($syncResult['pnr_itinerary_sync_preview']['map_preview'] ?? [])
                : []);

        if (! is_array($preview) || $preview === []) {
            $preview = [
                'candidate_segment_count' => (int) ($syncResult['candidate_segment_count'] ?? 0),
                'mappable_segment_count' => (int) ($syncResult['mappable_segment_count'] ?? 0),
                'candidate_rows' => [],
                'safe_to_map_preview' => (bool) ($syncResult['safe_to_map_preview'] ?? false),
                'resource_unavailable_present' => (bool) ($syncResult['resource_unavailable_present'] ?? false),
            ];
        }

        $segmentBase = $this->assessFromPreview($preview, $syncResult);
        if ($closureContext === null) {
            return $segmentBase;
        }

        $retrieveEvidence = $this->buildRetrieveEvidenceFromSync($syncResult, $closureContext);
        $classified = $this->zeroSegmentClassifier->classify($closureContext, $retrieveEvidence, $segmentBase);

        return array_merge($segmentBase, $classified);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @param  array<string, mixed>  $closureContext
     * @param  array<string, mixed>  $retrieveArtifact
     * @return array<string, mixed>
     */
    public function assessFromPersistedSafeSummary(
        array $safeSummary,
        array $closureContext,
        array $retrieveArtifact = [],
    ): array {
        $preview = [
            'candidate_segment_count' => (int) ($safeSummary['segment_count'] ?? 0),
            'mappable_segment_count' => (int) ($safeSummary['mappable_segment_count'] ?? 0),
            'candidate_rows' => [],
            'safe_to_map_preview' => (bool) ($safeSummary['safe_to_map_preview'] ?? false),
            'resource_unavailable_present' => (bool) ($safeSummary['resource_unavailable_present'] ?? false),
        ];

        $segmentBase = $this->assessFromPreview($preview, [
            'synced' => false,
            'reason_code' => (string) ($safeSummary['reason_code'] ?? 'unmappable'),
        ]);

        $retrieveEvidence = [
            'http_status' => (int) ($safeSummary['http_status'] ?? 0),
            'segment_count' => (int) ($safeSummary['segment_count'] ?? 0),
            'mappable_segment_count' => (int) ($safeSummary['mappable_segment_count'] ?? 0),
            'resource_unavailable_present' => (bool) ($safeSummary['resource_unavailable_present'] ?? false),
            'safe_to_map_preview' => (bool) ($safeSummary['safe_to_map_preview'] ?? false),
            'retrieve_request_dispatched' => ($retrieveArtifact['retrieve_request_dispatched'] ?? true) === true,
            'retrieve_response_received' => ($retrieveArtifact['retrieve_response_received'] ?? true) === true,
            'supplier_retrieve_call_count' => (int) ($retrieveArtifact['supplier_retrieve_call_count'] ?? 1),
        ];

        return array_merge(
            $segmentBase,
            $this->zeroSegmentClassifier->classify($closureContext, $retrieveEvidence, $segmentBase),
        );
    }

    /**
     * @param  array<string, mixed>  $syncResult
     * @param  array<string, mixed>  $closureContext
     * @return array<string, mixed>
     */
    public function buildRetrieveEvidenceFromSync(array $syncResult, array $closureContext): array
    {
        unset($closureContext);

        $preview = is_array($syncResult['map_preview'] ?? null) ? $syncResult['map_preview'] : [];

        return [
            'http_status' => (int) ($syncResult['http_status'] ?? $preview['http_status'] ?? 0),
            'segment_count' => (int) ($syncResult['segment_count'] ?? $preview['candidate_segment_count'] ?? $syncResult['candidate_segment_count'] ?? 0),
            'mappable_segment_count' => (int) ($syncResult['mappable_segment_count'] ?? $preview['mappable_segment_count'] ?? 0),
            'resource_unavailable_present' => (bool) ($syncResult['resource_unavailable_present'] ?? $preview['resource_unavailable_present'] ?? false),
            'safe_to_map_preview' => (bool) ($syncResult['safe_to_map_preview'] ?? $preview['safe_to_map_preview'] ?? false),
            'error' => $syncResult['error'] ?? null,
            'retrieve_request_dispatched' => true,
            'retrieve_response_received' => true,
            'supplier_retrieve_call_count' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function assessFromPreview(array $preview, array $context = []): array
    {
        $rows = is_array($preview['candidate_rows'] ?? null) ? $preview['candidate_rows'] : [];
        $active = 0;
        $cancelled = 0;
        $inactive = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['segment_status'] ?? '')));
            if ($status === '') {
                $inactive++;

                continue;
            }
            if (in_array($status, SabreTripOrdersGetBookingItineraryMapper::BLOCKED_SEGMENT_STATUSES, true)) {
                $cancelled++;

                continue;
            }
            if ($status === 'HK') {
                $active++;

                continue;
            }
            $active++;
        }

        $candidateCount = (int) ($preview['candidate_segment_count'] ?? count($rows));
        $mappableCount = (int) ($preview['mappable_segment_count'] ?? 0);
        $httpStatus = (int) ($context['http_status'] ?? 0);
        $cancelVerificationStatus = (string) ($context['cancel_verification_status'] ?? '');

        if ($rows === [] && $candidateCount === 0 && $mappableCount === 0) {
            if (in_array($httpStatus, [200, 201], true)) {
                return [
                    'active_segment_count' => 0,
                    'inactive_segment_count' => 0,
                    'cancelled_segment_count' => 0,
                    'candidate_segment_count' => 0,
                    'mappable_segment_count' => 0,
                    'post_cancel_retrieve_confirmed' => false,
                    'retrieve_ambiguous' => true,
                    'assessment_reason' => SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier::REASON_RETRIEVE_FAILED_OR_EMPTY_WITHOUT_CANCEL_PROOF,
                ];
            }

            if (in_array($cancelVerificationStatus, ['likely_cancelled', 'cancelled'], true)
                || ($context['synced'] ?? false) === true && $active === 0) {
                return [
                    'active_segment_count' => 0,
                    'inactive_segment_count' => 0,
                    'cancelled_segment_count' => 0,
                    'candidate_segment_count' => 0,
                    'mappable_segment_count' => 0,
                    'post_cancel_retrieve_confirmed' => true,
                    'retrieve_ambiguous' => false,
                    'assessment_reason' => 'zero_candidate_segments_cancelled_itinerary',
                ];
            }

            if (isset($context['error']) || ($context['synced'] ?? null) === false) {
                return [
                    'active_segment_count' => 0,
                    'inactive_segment_count' => 0,
                    'cancelled_segment_count' => 0,
                    'candidate_segment_count' => 0,
                    'mappable_segment_count' => 0,
                    'post_cancel_retrieve_confirmed' => false,
                    'retrieve_ambiguous' => true,
                    'assessment_reason' => SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier::REASON_RETRIEVE_FAILED_OR_EMPTY_WITHOUT_CANCEL_PROOF,
                ];
            }
        }

        if ($active > 0) {
            return [
                'active_segment_count' => $active,
                'inactive_segment_count' => $inactive,
                'cancelled_segment_count' => $cancelled,
                'candidate_segment_count' => $candidateCount,
                'mappable_segment_count' => $mappableCount,
                'post_cancel_retrieve_confirmed' => false,
                'retrieve_ambiguous' => false,
                'assessment_reason' => 'active_air_segments_present',
            ];
        }

        if ($active === 0 && ($cancelled > 0 || $candidateCount === 0)) {
            return [
                'active_segment_count' => 0,
                'inactive_segment_count' => $inactive,
                'cancelled_segment_count' => $cancelled,
                'candidate_segment_count' => $candidateCount,
                'mappable_segment_count' => $mappableCount,
                'post_cancel_retrieve_confirmed' => true,
                'retrieve_ambiguous' => false,
                'assessment_reason' => 'zero_active_segments',
            ];
        }

        return [
            'active_segment_count' => $active,
            'inactive_segment_count' => $inactive,
            'cancelled_segment_count' => $cancelled,
            'candidate_segment_count' => $candidateCount,
            'mappable_segment_count' => $mappableCount,
            'post_cancel_retrieve_confirmed' => false,
            'retrieve_ambiguous' => true,
            'assessment_reason' => 'segment_state_not_definitive',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildClosureContextFromIdentity(
        array $identity,
        ?array $priorCancellationArtifact,
        int $expectedBookingId,
        int $expectedSupplierBookingId,
    ): array {
        return [
            'prior_cancellation_confirmed' => ($identity['prior_cancellation_confirmed'] ?? false) === true,
            'prior_cancellation_ambiguous' => ($identity['prior_cancellation_ambiguous'] ?? false) === true,
            'prior_supplier_cancellation_call_count' => (int) ($priorCancellationArtifact['supplier_cancellation_call_count'] ?? 1),
            'booking_id' => (int) ($identity['booking_id'] ?? 0),
            'supplier_booking_id' => (int) ($identity['supplier_booking_id'] ?? 0),
            'expected_booking_id' => $expectedBookingId,
            'expected_supplier_booking_id' => $expectedSupplierBookingId,
            'booking_pnr_present' => ($identity['booking_pnr_present'] ?? $identity['locator_present'] ?? false) === true,
            'supplier_pnr_present' => ($identity['supplier_pnr_present'] ?? $identity['locator_present'] ?? false) === true,
            'locator_matches' => ($identity['locator_matches'] ?? false) === true,
            'locator_denylisted' => ($identity['locator_denylisted'] ?? false) === true,
            'locator_sha256' => (string) ($identity['locator_sha256'] ?? ''),
            'prior_cancellation_artifact_locator_sha256' => (string) ($priorCancellationArtifact['locator_sha256'] ?? ''),
            'ticket_number_count' => (int) ($identity['ticket_number_count'] ?? 0),
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        ];
    }
}
