<?php

namespace App\Services\Ai\Embed\Adapters\Disabled;

use App\Contracts\Ai\Embed\LeadProvider;

final class DisabledLeadProvider implements LeadProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}
