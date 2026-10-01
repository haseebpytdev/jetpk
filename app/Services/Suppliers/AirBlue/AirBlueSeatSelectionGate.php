<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

/**
 * Certification-safe gate: v3 ticketing may require seat selection before AirDemandTicket.
 */
class AirBlueSeatSelectionGate
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $pnrState
     */
    public function assertSeatSelectionSatisfied(array $config, array $context, array $pnrState = []): void
    {
        $protocol = (string) ($config['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value);
        if ($protocol !== AirBlueZapwaysProtocolVersion::V3->value) {
            return;
        }

        if (($context['ticketing_mode'] ?? 'ticket') !== 'ticket') {
            return;
        }

        $required = (bool) ($context['seat_selection_required'] ?? $pnrState['seat_selection_required'] ?? true);
        if (! $required) {
            return;
        }

        $seats = is_array($pnrState['seats'] ?? null) ? $pnrState['seats'] : [];
        $confirmedSeats = is_array($context['confirmed_seats'] ?? null) ? $context['confirmed_seats'] : [];
        if ($seats !== [] || $confirmedSeats !== []) {
            return;
        }

        throw new AirBlueValidationException(
            'seat_selection_required',
            422,
            'AirBlue Zapways v3 ticketing requires seat selection before ticket issuance.',
        );
    }
}
