<?php

namespace App\Support\Sabre\Cancel;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;

/**
 * Read-only Sabre GDS unticketed cancel strategy digest (no live HTTP).
 */
final class SabreGdsCancelStrategyDigest
{
    /** @var list<array<string, mixed>> */
    private const STRATEGIES = [
        [
            'strategy_code' => 'trip_orders_cancel_booking',
            'endpoint_path' => '/v1/trip/orders/cancelBooking',
            'payload_schema' => 'trip_orders_cancel_booking',
            'automatic_allowed' => false,
            'admin_confirmed_only' => true,
            'duplicate_risk_level' => 'medium',
            'certification_status' => 'dry_run_valid',
        ],
    ];

    public function __construct(
        protected SabreGdsCancelReadiness $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildBookingSummary(Booking $booking): array
    {
        $eval = $this->readiness->evaluate($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'provider' => 'sabre',
            'distribution_channel' => 'gds',
            'pnr' => trim((string) ($booking->pnr ?? '')) ?: null,
            'ticketed' => (bool) ($eval['ticketed'] ?? false),
            'cancelled' => (bool) ($eval['cancelled'] ?? false),
            'can_execute' => (bool) ($eval['can_execute'] ?? false),
            'action_state' => (string) ($eval['action_state'] ?? ''),
            'blockers' => is_array($eval['blockers'] ?? null) ? array_values($eval['blockers']) : [],
            'unticketed_cancel_enabled' => (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false),
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) ?: null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildCandidateDigests(Booking $booking): array
    {
        $eval = $this->readiness->evaluate($booking);
        $eligible = ($eval['can_execute'] ?? false) === true;

        return array_map(function (array $strategy) use ($eligible, $eval): array {
            return array_merge($strategy, [
                'context_ready' => $eligible,
                'selected_by_selector' => $eligible,
                'selection_reason' => $eligible ? 'unticketed_pnr_cancel_eligible' : 'cancel_not_eligible',
                'required_fields_present' => trim((string) ($eval['stored_status'] ?? '')) !== '' || $eligible,
                'blockers' => is_array($eval['blockers'] ?? null) ? array_values($eval['blockers']) : [],
            ]);
        }, self::STRATEGIES);
    }
}
