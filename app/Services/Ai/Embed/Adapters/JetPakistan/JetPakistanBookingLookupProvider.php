<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\BookingLookupProvider;
use App\Models\AiEmbedTenant;

final class JetPakistanBookingLookupProvider implements BookingLookupProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }
}
