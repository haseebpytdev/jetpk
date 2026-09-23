<?php

namespace App\Services\Ai\Embed\Adapters\Disabled;

use App\Contracts\Ai\Embed\SupportHandoffProvider;

final class DisabledHandoffProvider implements SupportHandoffProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}
