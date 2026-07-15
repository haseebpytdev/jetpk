<?php

namespace App\Support\Sabre;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;

/**
 * Regenerates safe structural snapshots from booking meta when attempt rows lack pre-call snapshots.
 */
final class SabrePnrAttemptStructureRegenerator
{
    public function __construct(
        protected SabreBookingService $bookingService,
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabrePnrAttemptStructureSnapshot $structureSnapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolveForAttempt(SupplierBookingAttempt $attempt): array
    {
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $stored = $this->extractStoredSnapshots($safe);
        if ($this->hasRequestSnapshots($stored)) {
            $stored['structure_snapshot_source'] = $stored['structure_snapshot_source'] ?? 'attempt_safe_summary';

            return $this->attachResponseFromAttempt($stored, $safe);
        }

        $attempt->loadMissing(['booking.passengers', 'booking.contact', 'booking.fareBreakdown']);
        $booking = $attempt->booking;
        if (! $booking instanceof Booking) {
            return [
                'attempt_id' => $attempt->id,
                'found' => false,
                'structure_snapshot_source' => 'missing_booking',
            ];
        }

        return $this->regenerateFromBooking($attempt, $booking, $safe);
    }

    /**
     * @param  array<string, mixed>  $safe
     * @return array<string, mixed>
     */
    protected function regenerateFromBooking(SupplierBookingAttempt $attempt, Booking $booking, array $safe): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))) !== SupplierProvider::Sabre->value) {
            return [
                'attempt_id' => $attempt->id,
                'booking_id' => $booking->id,
                'found' => true,
                'structure_snapshot_source' => 'not_sabre',
            ];
        }

        $payloadStyle = trim((string) (
            $safe['selected_payload_style']
            ?? $safe['payload_schema']
            ?? $safe['create_payload_style']
            ?? SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        ));
        $endpointPath = trim((string) (
            $safe['endpoint_path']
            ?? $safe['create_endpoint_path']
            ?? $this->payloadBuilder->resolvePassengerRecordsCreateEndpointPath($payloadStyle)
        ));

        $snapshots = $this->bookingService->rebuildPnrAttemptStructureSnapshots(
            $booking,
            $payloadStyle,
            $endpointPath,
            'regenerated_from_booking_meta',
        );
        if (($snapshots['structure_snapshot_source'] ?? '') === 'regeneration_draft_invalid') {
            return [
                'attempt_id' => $attempt->id,
                'booking_id' => $booking->id,
                'found' => true,
                'structure_snapshot_source' => 'regeneration_draft_invalid',
            ];
        }

        $responseDigest = $this->responseDigestFromAttemptSafeSummary($safe);
        if ($responseDigest !== []) {
            $snapshots['safe_response_structure'] = $this->structureSnapshot->buildResponseStructure($responseDigest);
        }

        return array_merge([
            'attempt_id' => $attempt->id,
            'booking_id' => $booking->id,
            'found' => true,
            'status' => (string) $attempt->status,
            'http_status' => $safe['http_status'] ?? null,
            'error_code' => $attempt->error_code,
        ], $snapshots);
    }

    /**
     * @param  array<string, mixed>  $safe
     * @return array<string, mixed>
     */
    protected function extractStoredSnapshots(array $safe): array
    {
        $out = [];
        foreach (SabrePnrAttemptStructureSnapshot::PERSISTENCE_KEYS as $key) {
            if (array_key_exists($key, $safe)) {
                $out[$key] = $safe[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    protected function hasRequestSnapshots(array $stored): bool
    {
        return is_array($stored['safe_request_structure'] ?? null)
            && is_array($stored['safe_airbook_structure'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $safe
     * @return array<string, mixed>
     */
    protected function attachResponseFromAttempt(array $stored, array $safe): array
    {
        if (! is_array($stored['safe_response_structure'] ?? null)) {
            $digest = $this->responseDigestFromAttemptSafeSummary($safe);
            if ($digest !== []) {
                $stored['safe_response_structure'] = $this->structureSnapshot->buildResponseStructure($digest);
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $safe
     * @return array<string, mixed>
     */
    protected function responseDigestFromAttemptSafeSummary(array $safe): array
    {
        return array_filter([
            'http_status' => $safe['http_status'] ?? null,
            'application_results_status' => $safe['application_results_status'] ?? null,
            'application_results_incomplete' => $safe['application_results_incomplete'] ?? null,
            'host_warning_modules' => $safe['host_warning_modules'] ?? null,
            'host_warning_sabre_codes' => $safe['host_warning_sabre_codes'] ?? null,
            'host_warning_messages_truncated' => $safe['host_warning_messages_truncated'] ?? null,
            'response_error_codes' => $safe['response_error_codes'] ?? null,
            'pnr' => $safe['pnr'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
