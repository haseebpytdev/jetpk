<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;

/**
 * Void ticket is not supported on AirBlue Zapways OTA.
 */
class AirBlueVoidTicketService
{
    /**
     * @return array<string, mixed>
     */
    public function voidTicket(Booking $booking, SupplierConnection $connection): array
    {
        unset($booking, $connection);

        throw new AirBlueCancellationException(
            'void_unsupported',
            422,
            'AirBlue Zapways OTA does not support automated ticket void. Contact AirBlue support.',
        );
    }
}
