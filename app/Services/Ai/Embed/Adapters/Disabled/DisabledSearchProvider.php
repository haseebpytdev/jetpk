<?php

namespace App\Services\Ai\Embed\Adapters\Disabled;

use App\Contracts\Ai\Embed\SearchProvider;

final class DisabledSearchProvider implements SearchProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}
