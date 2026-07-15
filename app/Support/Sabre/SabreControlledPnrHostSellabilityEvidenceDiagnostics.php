<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Security\SensitiveDataRedactor;

/**
 * F9R: Read-only controlled Sabre PNR host-sellability evidence after post-final-retry failure (no supplier HTTP, no DB mutation).
 */
final class SabreControlledPnrHostSellabilityEvidenceDiagnostics
{
    public function __construct(
        protected SabreBookingService $sabreBookingService,
        protected SabrePassengerRecordsApplicationResultDigest $applicationResultDigest,
        protected SabreControlledFinalPnrRetryAllowanceGate $finalPnrRetryAllowanceGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspectBooking(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets']);

        $payloadDigest = $this->sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);
        $applicationInspect = $this->applicationResultDigest->inspectBooking($booking);
        $containment = $this->finalPnrRetryAllowanceGate->assessPostFinalRetryContainment($booking);

        $comparison = is_array($payloadDigest['context_comparison'] ?? null)
            ? $payloadDigest['context_comparison']
            : [];
        $selectedSummary = is_array($payloadDigest['selected_context_summary'] ?? null)
            ? $payloadDigest['selected_context_summary']
            : [];

        $digestStatus = (string) ($payloadDigest['digest_status'] ?? '');
        $schemaStatus = (string) ($payloadDigest['cpnr_schema_validation_status'] ?? 'not_run');
        $postF9iClean = ($payloadDigest['post_f9i_payload_digest_clean'] ?? false) === true;
        $hardPayloadRisk = ($payloadDigest['hard_no_fares_rbd_carrier_risk'] ?? false) === true;

        $localPayloadClean = $digestStatus === 'ok'
            && $schemaStatus === 'pass'
            && $postF9iClean
            && ! $hardPayloadRisk;

        $hostRejectedSellability = ($containment['post_final_retry_host_failure'] ?? false) === true;

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $strongLinkageRecord = is_array($meta[SabreControlledStrongRevalidationLinkageApply::META_KEY] ?? null)
            ? $meta[SabreControlledStrongRevalidationLinkageApply::META_KEY]
            : [];
        $brandMatch = $this->resolveBrandMatch($payloadDigest, $comparison, $strongLinkageRecord);

        $out = [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'local_payload_clean' => $localPayloadClean,
            'host_rejected_sellability' => $hostRejectedSellability,
            'controlled_final_pnr_retry_allowance_used' => ($containment['controlled_final_pnr_retry_allowance_used'] ?? false) === true,
            'final_controlled_create_attempted' => ($containment['final_controlled_create_attempted'] ?? false) === true,
            'final_controlled_create_failed' => ($containment['final_controlled_create_failed'] ?? false) === true,
            'post_final_retry_host_failure' => $hostRejectedSellability,
            'post_final_retry_host_failure_code' => $containment['post_final_retry_host_failure_code'] ?? null,
            'no_safe_retry_without_remediation' => ($containment['no_safe_retry_without_remediation'] ?? false) === true,
            'digest_status' => $digestStatus !== '' ? $digestStatus : null,
            'endpoint_path' => $payloadDigest['endpoint_path'] ?? null,
            'payload_style' => $payloadDigest['payload_style'] ?? null,
            'segment_count' => isset($payloadDigest['segment_count']) ? (int) $payloadDigest['segment_count'] : null,
            'validating_carrier' => $payloadDigest['validating_carrier'] ?? ($selectedSummary['validating_carrier'] ?? null),
            'brand_code' => $payloadDigest['brand_code'] ?? ($selectedSummary['brand_code'] ?? null),
            'airprice_validating_carrier_present' => ($payloadDigest['airprice_validating_carrier_present'] ?? false) === true,
            'airprice_validating_carrier' => $payloadDigest['airprice_validating_carrier'] ?? null,
            'validating_carrier_match' => $comparison['validating_carrier_match'] ?? null,
            'selected_context_brand_code' => $selectedSummary['brand_code'] ?? null,
            'payload_airprice_brand_code' => $payloadDigest['payload_airprice_brand_code'] ?? null,
            'brand_match' => $brandMatch,
            'airbook_segment_count' => isset($payloadDigest['airbook_segment_count']) ? (int) $payloadDigest['airbook_segment_count'] : null,
            'airprice_present' => ($payloadDigest['airprice_present'] ?? false) === true,
            'airbook_rbd_complete' => ($payloadDigest['airbook_rbd_complete'] ?? false) === true,
            'airbook_carrier_complete' => ($payloadDigest['airbook_carrier_complete'] ?? false) === true,
            'cpnr_schema_validation_status' => $schemaStatus,
            'cpnr_schema_validation_failed' => ($payloadDigest['cpnr_schema_validation_failed'] ?? false) === true,
            'post_f9i_payload_digest_clean' => $postF9iClean,
            'selected_context_summary' => [
                'route_chain' => $selectedSummary['route_chain'] ?? null,
            ],
            'rbd_by_segment' => $payloadDigest['rbd_by_segment'] ?? ($selectedSummary['rbd_by_segment'] ?? null),
            'fare_basis_by_segment' => $payloadDigest['fare_basis_by_segment'] ?? ($selectedSummary['fare_basis_by_segment'] ?? null),
            'context_comparison' => [
                'route_match' => $comparison['route_match'] ?? null,
                'date_match' => $comparison['date_match'] ?? null,
                'rbd_match' => $comparison['rbd_match'] ?? null,
                'fare_basis_present' => $comparison['fare_basis_present'] ?? null,
                'validating_carrier_match' => $comparison['validating_carrier_match'] ?? null,
                'brand_match' => $brandMatch,
                'mismatch_reasons' => is_array($comparison['mismatch_reasons'] ?? null)
                    ? array_values($comparison['mismatch_reasons'])
                    : [],
            ],
            'application_error_digest_available' => ($applicationInspect['application_error_digest_available'] ?? false) === true,
            'sabre_last_create_status' => $applicationInspect['sabre_last_create_status'] ?? null,
            'sabre_last_create_error_code' => $applicationInspect['sabre_last_create_error_code'] ?? null,
            'sabre_last_create_error_message' => $applicationInspect['sabre_last_create_error_message'] ?? null,
            'sabre_application_status' => $applicationInspect['sabre_application_status'] ?? null,
            'sabre_application_error_count' => (int) ($applicationInspect['sabre_application_error_count'] ?? 0),
            'sabre_application_warning_count' => (int) ($applicationInspect['sabre_application_warning_count'] ?? 0),
            'safe_errors' => is_array($applicationInspect['safe_errors'] ?? null)
                ? array_slice($applicationInspect['safe_errors'], 0, 10)
                : [],
            'safe_warnings' => is_array($applicationInspect['safe_warnings'] ?? null)
                ? array_slice($applicationInspect['safe_warnings'], 0, 10)
                : [],
            'recommended_next_action' => $this->recommendedNextAction($hostRejectedSellability, $localPayloadClean),
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];

        return SensitiveDataRedactor::redact($out);
    }

    /**
     * @param  array<string, mixed>  $payloadDigest
     * @param  array<string, mixed>  $comparison
     * @param  array<string, mixed>  $strongLinkageRecord
     */
    protected function resolveBrandMatch(array $payloadDigest, array $comparison, array $strongLinkageRecord): bool
    {
        if (array_key_exists('brand_match', $payloadDigest) && $payloadDigest['brand_match'] !== null) {
            return ($payloadDigest['brand_match'] ?? false) === true;
        }

        if (array_key_exists('brand_match', $comparison) && $comparison['brand_match'] !== null) {
            return ($comparison['brand_match'] ?? false) === true;
        }

        if (array_key_exists('brand_match', $strongLinkageRecord)) {
            return ($strongLinkageRecord['brand_match'] ?? false) === true;
        }

        return false;
    }

    protected function recommendedNextAction(bool $hostRejectedSellability, bool $localPayloadClean): string
    {
        if ($hostRejectedSellability && $localPayloadClean) {
            return 'Staff review / Sabre host/PCC/QR/RBD/fare basis/brand qualifier investigation.';
        }

        if ($hostRejectedSellability) {
            return 'Review post-final-retry host failure and payload context before any remediation.';
        }

        return 'No post-final-retry host sellability rejection detected.';
    }
}
