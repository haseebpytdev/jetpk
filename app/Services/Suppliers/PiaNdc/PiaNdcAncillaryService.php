<?php

namespace App\Services\Suppliers\PiaNdc;

use Illuminate\Support\Facades\Log;

/**
 * Ancillary framework shell — no sample payloads for ServiceList/SeatAvailability in ZIP.
 */
class PiaNdcAncillaryService
{
    public function isSupported(): bool
    {
        return false;
    }

    public function logUnavailable(string $operation): void
    {
        Log::channel('pia-ndc')->info('pia_ndc.ancillary.unavailable', [
            'operation' => $operation,
            'reason' => 'No complete ancillary sample payloads in provider docs.',
        ]);
    }
}
