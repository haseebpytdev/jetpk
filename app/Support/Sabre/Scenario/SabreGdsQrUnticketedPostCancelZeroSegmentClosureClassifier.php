<?php

namespace App\Support\Sabre\Scenario;

/**
 * Post-cancel closure classification using prior cancellation proof + safe retrieve evidence (no raw payloads).
 */
final class SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier
{
    public const REASON_ZERO_SEGMENT_PRIOR_CANCEL_CONFIRMED = 'zero_segment_http_200_prior_cancellation_confirmed';

    public const REASON_RETRIEVE_FAILED_OR_EMPTY_WITHOUT_CANCEL_PROOF = 'retrieve_failed_or_empty_without_cancel_proof';

    /**
     * @param  array<string, mixed>  $closureContext
     * @param  array<string, mixed>  $retrieveEvidence
     * @param  array<string, mixed>  $segmentAssessment
     * @return array<string, mixed>
     */
    public function classify(array $closureContext, array $retrieveEvidence, array $segmentAssessment): array
    {
        $blockers = $this->closureBlockers($closureContext, $retrieveEvidence, $segmentAssessment);
        if ($blockers !== []) {
            return $this->outcome(
                confirmed: false,
                ambiguous: $this->isAmbiguousBlockerSet($blockers, $retrieveEvidence, $segmentAssessment),
                reason: $blockers[0],
                segmentAssessment: $segmentAssessment,
                blockers: $blockers,
            );
        }

        return $this->outcome(
            confirmed: true,
            ambiguous: false,
            reason: self::REASON_ZERO_SEGMENT_PRIOR_CANCEL_CONFIRMED,
            segmentAssessment: $segmentAssessment,
            retrieveOutcomeState: 'retrieve_confirmed',
        );
    }

    /**
     * @param  array<string, mixed>  $closureContext
     * @param  array<string, mixed>  $retrieveEvidence
     * @return list<string>
     */
    public function closureBlockers(array $closureContext, array $retrieveEvidence, array $segmentAssessment): array
    {
        $blockers = [];

        if (($closureContext['prior_cancellation_confirmed'] ?? false) !== true) {
            $blockers[] = 'prior_cancellation_not_confirmed';
        }
        if (($closureContext['prior_cancellation_ambiguous'] ?? false) === true) {
            $blockers[] = 'prior_cancellation_ambiguous';
        }
        if ((int) ($closureContext['prior_supplier_cancellation_call_count'] ?? 0) !== 1) {
            $blockers[] = 'prior_supplier_cancellation_call_count_not_one';
        }
        if ((int) ($closureContext['booking_id'] ?? 0) !== (int) ($closureContext['expected_booking_id'] ?? 0)) {
            $blockers[] = 'booking_id_mismatch';
        }
        if ((int) ($closureContext['supplier_booking_id'] ?? 0) !== (int) ($closureContext['expected_supplier_booking_id'] ?? 0)) {
            $blockers[] = 'supplier_booking_id_mismatch';
        }
        if (($closureContext['booking_pnr_present'] ?? false) !== true || ($closureContext['supplier_pnr_present'] ?? false) !== true) {
            $blockers[] = 'pnr_missing';
        }
        if (($closureContext['locator_matches'] ?? false) !== true) {
            $blockers[] = 'booking_supplier_pnr_mismatch';
        }
        if (($closureContext['locator_denylisted'] ?? false) === true) {
            $blockers[] = 'locator_denylisted';
        }
        $locatorSha = (string) ($closureContext['locator_sha256'] ?? '');
        $priorArtifactSha = (string) ($closureContext['prior_cancellation_artifact_locator_sha256'] ?? '');
        if ($priorArtifactSha !== '' && $locatorSha !== '' && ! hash_equals($priorArtifactSha, $locatorSha)) {
            $blockers[] = 'locator_sha256_mismatch';
        }
        if ((int) ($closureContext['ticket_number_count'] ?? -1) !== 0) {
            $blockers[] = 'ticket_numbers_present';
        }
        if (($closureContext['ticketing_enabled'] ?? false) === true) {
            $blockers[] = 'ticketing_enabled';
        }
        if (($retrieveEvidence['retrieve_request_dispatched'] ?? false) !== true) {
            $blockers[] = 'retrieve_not_dispatched';
        }
        if (($retrieveEvidence['retrieve_response_received'] ?? false) !== true) {
            $blockers[] = 'retrieve_response_missing';
        }
        if ((int) ($retrieveEvidence['supplier_retrieve_call_count'] ?? 0) !== 1) {
            $blockers[] = 'supplier_retrieve_call_count_not_one';
        }

        $httpStatus = (int) ($retrieveEvidence['http_status'] ?? 0);
        if (! in_array($httpStatus, [200, 201], true)) {
            $blockers[] = 'http_status_not_success';
        }
        if (($retrieveEvidence['resource_unavailable_present'] ?? false) === true) {
            $blockers[] = 'resource_unavailable_present';
        }
        if (isset($retrieveEvidence['error']) && $httpStatus === 0) {
            $blockers[] = 'retrieve_transport_error';
        }

        $activeSeg = (int) ($segmentAssessment['active_segment_count'] ?? 0);
        $cancelledSeg = (int) ($segmentAssessment['cancelled_segment_count'] ?? 0);
        $segmentCount = (int) ($retrieveEvidence['segment_count'] ?? $segmentAssessment['candidate_segment_count'] ?? -1);
        $mappableCount = (int) ($retrieveEvidence['mappable_segment_count'] ?? $segmentAssessment['mappable_segment_count'] ?? -1);

        if ($activeSeg > 0) {
            $blockers[] = 'active_air_segments_present';
        } elseif ($segmentCount < 0 || $mappableCount < 0) {
            $blockers[] = 'segment_count_undetermined';
        } elseif ($segmentCount === 0 && $mappableCount === 0) {
            // Zero-segment post-cancel itinerary (Phase 16 production case).
        } elseif ($segmentCount > 0 && $activeSeg === 0 && $cancelledSeg > 0) {
            // All returned segments are cancelled (HX, etc.).
        } elseif ($segmentCount !== 0 || $mappableCount !== 0) {
            $blockers[] = 'non_zero_segment_counts';
        }

        return $blockers;
    }

    /**
     * @param  list<string>  $blockers
     */
    protected function isAmbiguousBlockerSet(array $blockers, array $retrieveEvidence, array $segmentAssessment): bool
    {
        unset($retrieveEvidence, $segmentAssessment);

        return $blockers !== [];
    }

    /**
     * @param  array<string, mixed>  $segmentAssessment
     * @param  list<string>  $blockers
     * @return array<string, mixed>
     */
    protected function outcome(
        bool $confirmed,
        bool $ambiguous,
        string $reason,
        array $segmentAssessment,
        string $retrieveOutcomeState = 'retrieve_ambiguous',
        array $blockers = [],
    ): array {
        return [
            'post_cancel_retrieve_confirmed' => $confirmed,
            'retrieve_ambiguous' => $confirmed ? false : $ambiguous,
            'retrieve_outcome_state' => $confirmed ? 'retrieve_confirmed' : $retrieveOutcomeState,
            'cancellation_closure_verified' => $confirmed,
            'manual_reconciliation_required' => $confirmed ? false : true,
            'active_segment_count' => (int) ($segmentAssessment['active_segment_count'] ?? 0),
            'inactive_segment_count' => (int) ($segmentAssessment['inactive_segment_count'] ?? 0),
            'cancelled_segment_count' => (int) ($segmentAssessment['cancelled_segment_count'] ?? 0),
            'assessment_reason' => $reason,
            'classification_blockers' => $blockers,
        ];
    }
}
