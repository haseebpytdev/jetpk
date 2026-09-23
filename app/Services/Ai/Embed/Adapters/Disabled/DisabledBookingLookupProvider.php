<?php

namespace App\Services\Ai\Embed\Adapters\Disabled;

use App\Contracts\Ai\Embed\BookingLookupProvider;

final class DisabledBookingLookupProvider implements BookingLookupProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}
