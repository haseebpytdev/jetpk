<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Log;

/**
 * Ancillary operations are not available on AirBlue Zapways OTA.
 */
class AirBlueAncillaryService
{
    public function isSupported(SupplierConnection $connection): bool
    {
        unset($connection);

        return false;
    }

    public function logUnavailable(SupplierConnection $connection, string $operation): void
    {
        Log::channel('air-blue')->info('airblue.ancillary.probe', [
            'operation' => $operation,
            'api_channel' => 'zapways_ota',
            'supported' => false,
        ]);
    }
}
