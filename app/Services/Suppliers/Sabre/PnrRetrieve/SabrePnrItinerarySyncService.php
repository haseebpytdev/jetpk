<?php

namespace App\Services\Suppliers\Sabre\PnrRetrieve;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Auth;

/**
 * B84B.2: Sync sanitized Trip Orders getBooking itinerary into {@code meta.pnr_itinerary_snapshot} (no raw body).
 * Phase F: safe cancel flags + retrieve-failure sidecar on {@code meta.pnr_itinerary_sync} (no raw getBooking JSON).
 * D3: {@code partial_resource_unavailable} persists safe locator/cancel/segment sidecar without full snapshot when RESOURCE_UNAVAILABLE has partial signals.
 */
final class SabrePnrItinerarySyncService
{
    public function __construct(
        protected SabrePnrRetrieveProbe $retrieveProbe,
        protected SabreTripOrdersGetBookingItineraryMapper $mapper,
        protected SabreTripOrdersGetBookingInspectSummary $inspectSummary,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(Booking $booking, bool $dryRun = false): array
    {
        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_gds')) {
            return SensitiveDataRedactor::redact([
                'error' => 'sabre_gds_disabled',
                'booking_id' => $booking->id,
                'dry_run' => $dryRun,
                'synced' => false,
            ]);
        }

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pnr = $this->resolvePnr($booking, $meta);
        if ($pnr === '') {
            return SensitiveDataRedactor::redact([
                'error' => 'booking_missing_pnr',
                'booking_id' => $booking->id,
                'dry_run' => $dryRun,
            ]);
        }

        $fetch = $this->retrieveProbe->fetchTripOrdersGetBooking($booking);
        if (isset($fetch['error'])) {
            $reasonCode = (string) $fetch['error'];
            if (! $dryRun) {
                $this->persistRetrieveFailed($booking, $pnr, $reasonCode);
            }

            return SensitiveDataRedactor::redact(array_merge($fetch, [
                'dry_run' => $dryRun,
                'synced' => false,
            ]));
        }

        $httpStatus = (int) ($fetch['http_status'] ?? 0);
        $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
        if ($json === []) {
            if (! $dryRun) {
                $this->persistRetrieveFailed($booking, $pnr, 'get_booking_empty', $httpStatus);
            }

            return SensitiveDataRedactor::redact([
                'error' => 'get_booking_empty',
                'booking_id' => $booking->id,
                'pnr' => $pnr,
                'http_status' => $httpStatus,
                'dry_run' => $dryRun,
                'synced' => false,
            ]);
        }

        $cancelSafety = $this->inspectSummary->extractDirectCancelSafetyFlags($json);
        $locatorObservability = $this->inspectSummary->buildAirlineLocatorObservability($json);
        $codes = is_array($fetch['response_error_codes'] ?? null)
            ? array_map('strval', $fetch['response_error_codes'])
            : [];
        $messages = is_array($fetch['response_error_messages'] ?? null)
            ? array_map('strval', $fetch['response_error_messages'])
            : [];

        $preview = $this->mapper->mapPreview($json, [
            'http_status' => $httpStatus,
            'response_error_codes' => $codes,
            'response_error_messages' => $messages,
        ]);
        $eligibility = $this->mapper->evaluateSyncEligibility($preview);
        $syncedAt = now()->toIso8601String();
        $snapshot = $this->mapper->buildSnapshot($preview, $pnr, $syncedAt);

        $result = $this->buildSafeResult(
            $booking,
            $pnr,
            $httpStatus,
            $preview,
            $eligibility,
            $dryRun,
            $snapshot,
            $syncedAt,
            $cancelSafety,
            $locatorObservability,
        );

        if ($dryRun) {
            return SensitiveDataRedactor::redact($result);
        }

        if ($eligibility['can_sync'] && $snapshot !== null) {
            $meta['pnr_itinerary_snapshot'] = $snapshot;
            $meta['pnr_itinerary_sync'] = $this->buildSyncedSidecar($pnr, $preview, $syncedAt, $cancelSafety, $locatorObservability);
            $booking->meta = $meta;
            $booking->save();
            $this->recordAttempt($booking, $httpStatus, $preview, 'success', null, $locatorObservability);

            return SensitiveDataRedactor::redact(array_merge($result, [
                'synced' => true,
                'wrote_pnr_itinerary_snapshot' => true,
            ]));
        }

        $blockedReasonCode = $this->resolveBlockedReasonCode(
            $preview,
            $eligibility,
            $locatorObservability,
        );
        $result['reason_code'] = $blockedReasonCode;
        $result['partial_sync'] = $blockedReasonCode === 'partial_resource_unavailable';
        $result['pnr_itinerary_sync_preview'] = $this->buildBlockedSidecar(
            $pnr,
            $preview,
            $blockedReasonCode,
            $syncedAt,
            $cancelSafety,
            $locatorObservability,
        );

        $meta['pnr_itinerary_sync'] = $result['pnr_itinerary_sync_preview'];
        $booking->meta = $meta;
        $booking->save();
        $this->recordAttempt($booking, $httpStatus, $preview, 'needs_review', $blockedReasonCode, $locatorObservability);

        return SensitiveDataRedactor::redact(array_merge($result, [
            'synced' => false,
            'partial_sync' => $blockedReasonCode === 'partial_resource_unavailable',
            'wrote_pnr_itinerary_snapshot' => false,
            'preserved_existing_snapshot' => $this->hasExistingSnapshot($meta),
        ]));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasExistingSnapshot(array $meta): bool
    {
        $snap = $meta['pnr_itinerary_snapshot'] ?? null;

        return is_array($snap)
            && is_array($snap['segments'] ?? null)
            && $snap['segments'] !== [];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array{can_sync: bool, reason_code: ?string, blocked_segment_statuses: list<string>}  $eligibility
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    protected function buildSafeResult(
        Booking $booking,
        string $pnr,
        int $httpStatus,
        array $preview,
        array $eligibility,
        bool $dryRun,
        ?array $snapshot,
        string $syncedAt,
        array $cancelSafety,
        array $locatorObservability,
    ): array {
        $segmentCount = (int) ($preview['candidate_segment_count'] ?? 0);
        $resolvedReasonCode = $eligibility['can_sync']
            ? null
            : $this->resolveBlockedReasonCode($preview, $eligibility, $locatorObservability);

        return [
            'booking_id' => $booking->id,
            'pnr' => $pnr,
            'dry_run' => $dryRun,
            'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
            'http_status' => $httpStatus,
            'candidate_segment_count' => $segmentCount,
            'mappable_segment_count' => (int) ($preview['mappable_segment_count'] ?? 0),
            'safe_to_map_preview' => (bool) ($preview['safe_to_map_preview'] ?? false),
            'resource_unavailable_present' => (bool) ($preview['resource_unavailable_present'] ?? false),
            'can_sync' => (bool) ($eligibility['can_sync'] ?? false),
            'reason_code' => $resolvedReasonCode,
            'partial_sync' => $resolvedReasonCode === 'partial_resource_unavailable',
            'blocked_segment_statuses' => $eligibility['blocked_segment_statuses'] ?? [],
            'error_codes_sanitized' => $preview['error_codes_sanitized'] ?? [],
            'would_write_pnr_itinerary_snapshot' => $eligibility['can_sync'] && $snapshot !== null,
            'pnr_itinerary_snapshot_preview' => $snapshot,
            'pnr_itinerary_sync_preview' => $eligibility['can_sync'] && $snapshot !== null
                ? $this->buildSyncedSidecar($pnr, $preview, $syncedAt, $cancelSafety, $locatorObservability)
                : $this->buildBlockedSidecar(
                    $pnr,
                    $preview,
                    $this->resolveBlockedReasonCode($preview, $eligibility, $locatorObservability),
                    $syncedAt,
                    $cancelSafety,
                    $locatorObservability,
                ),
            'airline_locator_observability' => $locatorObservability,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array{
     *   is_ticketed: ?bool,
     *   is_cancelable: ?bool,
     *   ticket_numbers_present: bool,
     *   booking_id_present: bool
     * }  $cancelSafety
     * @return array<string, mixed>
     */
    protected function buildSyncedSidecar(
        string $pnr,
        array $preview,
        string $syncedAt,
        array $cancelSafety,
        array $locatorObservability = [],
    ): array {
        return array_merge([
            'status' => 'synced',
            'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
            'synced_at' => $syncedAt,
            'pnr' => strtoupper($pnr),
            'segment_count' => (int) ($preview['candidate_segment_count'] ?? 0),
            'mappable_segment_count' => (int) ($preview['mappable_segment_count'] ?? 0),
            'resource_unavailable_present' => false,
        ], $this->safeCancelFlagsSlice($cancelSafety), $this->airlineLocatorObservabilitySlice($locatorObservability));
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array{can_sync: bool, reason_code: ?string, blocked_segment_statuses: list<string>}  $eligibility
     * @param  array<string, mixed>  $locatorObservability
     */
    protected function resolveBlockedReasonCode(array $preview, array $eligibility, array $locatorObservability): string
    {
        $reasonCode = (string) ($eligibility['reason_code'] ?? 'unmappable');
        if ($reasonCode === 'blocked_resource_unavailable') {
            return $this->mapper->refineResourceUnavailableReason($preview, $locatorObservability);
        }

        return $reasonCode;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array{
     *   is_ticketed: ?bool,
     *   is_cancelable: ?bool,
     *   ticket_numbers_present: bool,
     *   booking_id_present: bool
     * }  $cancelSafety
     * @return array<string, mixed>
     */
    protected function buildBlockedSidecar(
        string $pnr,
        array $preview,
        string $reasonCode,
        string $attemptedAt,
        array $cancelSafety,
        array $locatorObservability = [],
    ): array {
        return array_merge([
            'status' => $reasonCode,
            'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
            'attempted_at' => $attemptedAt,
            'pnr' => strtoupper($pnr),
            'resource_unavailable_present' => (bool) ($preview['resource_unavailable_present'] ?? false),
            'reason_code' => $reasonCode,
            'segment_count' => (int) ($preview['candidate_segment_count'] ?? 0),
            'mappable_segment_count' => (int) ($preview['mappable_segment_count'] ?? 0),
        ], $this->safeCancelFlagsSlice($cancelSafety), $this->airlineLocatorObservabilitySlice($locatorObservability));
    }

    /**
     * @param  array{
     *   is_ticketed: ?bool,
     *   is_cancelable: ?bool,
     *   ticket_numbers_present: bool,
     *   booking_id_present: bool
     * }  $cancelSafety
     * @return array<string, mixed>
     */
    protected function safeCancelFlagsSlice(array $cancelSafety): array
    {
        return [
            'is_cancelable' => $cancelSafety['is_cancelable'] ?? null,
            'is_ticketed' => $cancelSafety['is_ticketed'] ?? null,
            'ticket_numbers_present' => (bool) ($cancelSafety['ticket_numbers_present'] ?? false),
            'booking_id_present' => (bool) ($cancelSafety['booking_id_present'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $locatorObservability
     * @return array<string, mixed>
     */
    protected function airlineLocatorObservabilitySlice(array $locatorObservability): array
    {
        if ($locatorObservability === []) {
            return [];
        }

        return [
            'airline_locator_present' => (bool) ($locatorObservability['airline_locator_present'] ?? false),
            'airline_locator_path' => is_string($locatorObservability['airline_locator_path'] ?? null)
                ? $locatorObservability['airline_locator_path']
                : null,
            'airline_locator_paths' => is_array($locatorObservability['airline_locator_paths'] ?? null)
                ? array_values(array_slice(array_map('strval', $locatorObservability['airline_locator_paths']), 0, 12))
                : [],
            'airline_locator_value' => is_string($locatorObservability['airline_locator_value'] ?? null)
                ? $locatorObservability['airline_locator_value']
                : null,
            'sabre_record_locator_present' => (bool) ($locatorObservability['sabre_record_locator_present'] ?? false),
            'sabre_record_locator_path' => is_string($locatorObservability['sabre_record_locator_path'] ?? null)
                ? $locatorObservability['sabre_record_locator_path']
                : null,
            'sabre_record_locator_value' => is_string($locatorObservability['sabre_record_locator_value'] ?? null)
                ? $locatorObservability['sabre_record_locator_value']
                : null,
            'trip_orders_confirmation_id_present' => (bool) ($locatorObservability['trip_orders_confirmation_id_present'] ?? false),
        ];
    }

    protected function persistRetrieveFailed(
        Booking $booking,
        string $pnr,
        string $reasonCode,
        int $httpStatus = 0,
    ): void {
        $attemptedAt = now()->toIso8601String();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_sync'] = [
            'status' => 'retrieve_failed',
            'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
            'attempted_at' => $attemptedAt,
            'synced_at' => $attemptedAt,
            'pnr' => strtoupper($pnr),
            'reason_code' => $reasonCode,
        ];
        $booking->meta = $meta;
        $booking->save();
        $this->recordRetrieveFailedAttempt($booking, $reasonCode, $httpStatus);
    }

    protected function recordRetrieveFailedAttempt(Booking $booking, string $reasonCode, int $httpStatus): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = data_get($meta, 'supplier_connection_id');
        $cid = is_numeric($cid) ? (int) $cid : null;
        $status = in_array($reasonCode, ['get_booking_empty'], true) ? 'needs_review' : 'failed';

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid > 0 ? $cid : null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'pnr_retrieve',
            'status' => $status,
            'error_code' => $reasonCode,
            'request_payload' => null,
            'response_payload' => null,
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'sabre_sync_pnr_itinerary',
                'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
                'http_status' => $httpStatus,
                'retrieve_failed' => true,
                'reason_code' => $reasonCode,
            ]),
            'attempted_by' => Auth::id(),
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    protected function recordAttempt(
        Booking $booking,
        int $httpStatus,
        array $preview,
        string $status,
        ?string $errorCode = null,
        array $locatorObservability = [],
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = data_get($meta, 'supplier_connection_id');
        $cid = is_numeric($cid) ? (int) $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid > 0 ? $cid : null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'pnr_retrieve',
            'status' => $status,
            'error_code' => $errorCode,
            'request_payload' => null,
            'response_payload' => null,
            'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                'source' => 'sabre_sync_pnr_itinerary',
                'endpoint_path' => SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
                'http_status' => $httpStatus,
                'segment_count' => (int) ($preview['candidate_segment_count'] ?? 0),
                'mappable_segment_count' => (int) ($preview['mappable_segment_count'] ?? 0),
                'safe_to_map_preview' => (bool) ($preview['safe_to_map_preview'] ?? false),
                'resource_unavailable_present' => (bool) ($preview['resource_unavailable_present'] ?? false),
            ], $this->airlineLocatorObservabilitySlice($locatorObservability))),
            'attempted_by' => Auth::id(),
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolvePnr(Booking $booking, array $meta): string
    {
        foreach ([
            $booking->pnr,
            $booking->supplier_reference,
            data_get($meta, 'sabre_provider_snapshot.pnr'),
            data_get($meta, 'pnr'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(substr(trim($candidate), 0, 32));
            }
        }

        return '';
    }
}
