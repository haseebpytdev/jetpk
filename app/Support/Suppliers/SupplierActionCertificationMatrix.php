<?php

namespace App\Support\Suppliers;

use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;

/**
 * Derives strategy certification status from safe evidence (never from payload build alone).
 */
final class SupplierActionCertificationMatrix
{
    public const STATUS_UNTESTED = 'untested';

    public const STATUS_STRUCTURALLY_VALID = 'structurally_valid';

    public const STATUS_DRY_RUN_VALID = 'dry_run_valid';

    public const STATUS_LIVE_SUCCESS = 'live_success';

    public const STATUS_LIVE_FAILED = 'live_failed';

    public const STATUS_CERTIFIED = 'certified';

    public const STATUS_BLOCKED = 'blocked';

    public function __construct(
        protected SupplierKnownGoodStrategyStore $knownGoodStore,
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
    ) {}

    /**
     * @return array{certification_status: string, certification_evidence: array<string, mixed>}
     */
    public function resolveForSabreGdsCreate(Booking $booking, string $strategyCode, bool $contextReady): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $route = $this->routeSelector->selectForBooking($booking);
        $validatingCarrier = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $routePattern = (string) ($route['category'] ?? SabreCertifiedRouteSelector::CATEGORY_UNKNOWN);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);

        $success = $connId > 0
            ? $this->knownGoodStore->bestSuccessEvidence(
                $connId,
                'sabre',
                $strategyCode,
                $validatingCarrier,
                $routePattern,
                $tripType,
                $segmentCount,
            )
            : null;
        $failure = $connId > 0
            ? $this->knownGoodStore->lastFailureEvidence(
                $connId,
                $strategyCode,
                $validatingCarrier,
                $routePattern,
                $tripType,
                $segmentCount,
            )
            : null;

        if ($success !== null && (int) ($success['success_count'] ?? 0) >= 1) {
            $status = self::STATUS_LIVE_SUCCESS;
            if ($this->hasRetrieveConfirmed($booking, $strategyCode)) {
                $status = self::STATUS_CERTIFIED;
            }
        } elseif ($failure !== null) {
            $status = self::STATUS_LIVE_FAILED;
        } elseif ($contextReady) {
            $status = self::STATUS_STRUCTURALLY_VALID;
        } else {
            $status = self::STATUS_UNTESTED;
        }

        return [
            'certification_status' => $status,
            'certification_evidence' => array_filter([
                'last_success_booking_id' => $success['last_success_booking_id'] ?? null,
                'last_failed_booking_id' => $failure['last_failed_booking_id'] ?? null,
                'last_success_at' => $success['last_success_at'] ?? null,
                'last_failure_reason_code' => $failure['last_failure_reason_code'] ?? ($failure['host_error_family'] ?? null),
            ], static fn (mixed $v): bool => $v !== null && $v !== ''),
        ];
    }

    protected function hasRetrieveConfirmed(Booking $booking, string $strategyCode): bool
    {
        unset($strategyCode);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        return ($sync['status'] ?? '') === 'synced' || ($sync['synced'] ?? false) === true;
    }
}
