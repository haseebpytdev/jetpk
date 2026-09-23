<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\LeadProvider;
use App\Models\AiEmbedTenant;

final class JetPakistanLeadProvider implements LeadProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }
}
