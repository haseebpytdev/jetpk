<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Log;

/**
 * NDC ancillary operations (seat/baggage) — Crane NDC channel only.
 */
class AirBlueAncillaryService
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
    ) {}

    public function isSupported(SupplierConnection $connection): bool
    {
        return $this->configResolver->apiChannel($connection) === AirBlueApiChannel::CraneNdc;
    }

    public function logUnavailable(SupplierConnection $connection, string $operation): void
    {
        Log::channel('air-blue')->info('airblue.ancillary.probe', [
            'operation' => $operation,
            'api_channel' => $this->configResolver->apiChannel($connection)->value,
            'supported' => $this->isSupported($connection),
        ]);
    }
}
