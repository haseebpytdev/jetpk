<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueTicketingException;

/**
 * Ticketing preview is not required for Zapways OTA AirDemandTicket flow.
 */
class AirBlueTicketPreviewService
{
    /**
     * @return array{amount: float, currency: string}
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        unset($booking, $connection);

        throw new AirBlueTicketingException(
            'ticket_preview_unsupported',
            422,
            'Zapways OTA does not require a separate ticketing preview step.',
        );
    }
}
