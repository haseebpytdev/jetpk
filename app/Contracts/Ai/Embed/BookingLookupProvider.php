<?php

namespace App\Contracts\Ai\Embed;

interface BookingLookupProvider
{
    public function isEnabled(): bool;
}
