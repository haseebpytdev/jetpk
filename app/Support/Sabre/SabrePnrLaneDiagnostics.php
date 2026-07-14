<?php

namespace App\Support\Sabre;

use App\Models\Booking;

/**
 * Sabre GDS PNR lane labels and lane-scoped readiness blockers (diagnostics only).
 */
final class SabrePnrLaneDiagnostics
{
    public const LANE_PUBLIC_CHECKOUT_PNR = 'public_checkout_pnr';

    public const LANE_OPERATIONAL_AUTO_PNR = 'operational_auto_pnr';

    public const LANE_ADMIN_MANUAL_PNR = 'admin_manual_pnr';

    /** @var array<string, string> */
    public const LANE_LABELS = [
        self::LANE_PUBLIC_CHECKOUT_PNR => 'Public checkout PNR',
        self::LANE_OPERATIONAL_AUTO_PNR => 'Operational auto-PNR',
        self::LANE_ADMIN_MANUAL_PNR => 'Admin manual PNR',
    ];

    /** @var array<string, string> */
    public const FLAG_DESCRIPTIONS = [
        'public_checkout_pnr_enabled' => 'Controls public checkout PNR creation at review/submit.',
        'operational_auto_pnr_enabled' => 'Controls background/operator auto-PNR lane (post-checkout ops).',
        'pnr_create_enabled' => 'Root gate for Sabre GDS PNR create (independent of ticketing).',
        'ticketing_enabled' => 'Controls ticket issuance only; does not block PNR create/retrieve/cancel.',
    ];

    /**
     * @return list<string>
     */
    public static function operationalBlockingConditionIds(): array
    {
        return [
            'operational_auto_pnr_enabled',
            'pnr_create_enabled',
            'ticketing_disabled',
            'gds_enabled',
            'payment_mode_manual',
            'provider_is_sabre',
            'supplier_connection_id_present',
            'no_pnr',
            'no_supplier_reference',
            'no_successful_supplier_booking',
            'passenger_fields_complete',
            'not_mixed_carrier',
            'same_carrier_connecting',
            'not_host_noop',
            'offer_snapshot_present',
            'sabre_booking_context_present',
            'safe_refresh_context_complete',
        ];
    }

    /**
     * Flags reported for context but never block the operational lane evaluation.
     *
     * @return list<string>
     */
    public static function operationalInformationalConditionIds(): array
    {
        return ['public_checkout_pnr_enabled', 'ticketing_enabled'];
    }

    /**
     * @return list<string>
     */
    public static function adminManualBlockingConditionIds(): array
    {
        return [
            'pnr_create_enabled',
            'admin_manual_pnr_enabled',
            'provider_is_sabre',
            'no_pnr',
            'no_supplier_reference',
            'no_successful_supplier_booking',
            'prior_failed_pnr_attempt_exists',
            'fare_context_consistent',
            'strategy_admin_fallback_allowed',
            'strategy_context_ready',
            'strategy_not_same_as_failed',
        ];
    }

    public static function laneLabel(string $lane): string
    {
        return self::LANE_LABELS[$lane] ?? str_replace('_', ' ', $lane);
    }

    public static function flagDescription(string $flag): string
    {
        return self::FLAG_DESCRIPTIONS[$flag] ?? str_replace('_', ' ', $flag);
    }

    /**
     * Detect the most relevant PNR lane for admin diagnostics on a booking.
     */
    public static function detectPrimaryLane(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];

        if (($checkout['live_call_attempted'] ?? false) === true) {
            return self::LANE_PUBLIC_CHECKOUT_PNR;
        }

        if (($meta['operational_auto_pnr_attempted'] ?? false) === true) {
            return self::LANE_OPERATIONAL_AUTO_PNR;
        }

        if (($meta['controlled_pnr_manual_review']['approved'] ?? false) === true
            || ($meta['defer_supplier_booking_to_manual_review'] ?? false) === true) {
            return self::LANE_ADMIN_MANUAL_PNR;
        }

        return self::LANE_PUBLIC_CHECKOUT_PNR;
    }

    public static function publicCheckoutPnrWasAttempted(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];

        return ($checkout['live_call_attempted'] ?? false) === true;
    }

    /**
     * @param  list<string>  $blockingConditions
     * @return list<string>
     */
    public static function filterBlockingConditionsForLane(array $blockingConditions, string $lane, Booking $booking): array
    {
        if ($lane === self::LANE_PUBLIC_CHECKOUT_PNR && self::publicCheckoutPnrWasAttempted($booking)) {
            return array_values(array_filter(
                $blockingConditions,
                static fn (string $id): bool => $id !== 'operational_auto_pnr_enabled',
            ));
        }

        if ($lane === self::LANE_OPERATIONAL_AUTO_PNR) {
            return array_values(array_filter(
                $blockingConditions,
                static fn (string $id): bool => ! in_array($id, ['public_checkout_pnr_enabled'], true),
            ));
        }

        if ($lane === self::LANE_PUBLIC_CHECKOUT_PNR) {
            return array_values(array_filter(
                $blockingConditions,
                static fn (string $id): bool => $id !== 'operational_auto_pnr_enabled',
            ));
        }

        return $blockingConditions;
    }

    /**
     * @param  array<string, bool>  $conditionResults
     * @return list<string>
     */
    public static function blockingConditionsFromResults(array $conditionResults, string $lane): array
    {
        $ids = match ($lane) {
            self::LANE_OPERATIONAL_AUTO_PNR => self::operationalBlockingConditionIds(),
            self::LANE_ADMIN_MANUAL_PNR => self::adminManualBlockingConditionIds(),
            default => array_keys($conditionResults),
        };

        $blocking = [];
        foreach ($ids as $conditionId) {
            if (($conditionResults[$conditionId] ?? false) !== true) {
                $blocking[] = $conditionId;
            }
        }

        return $blocking;
    }
}
