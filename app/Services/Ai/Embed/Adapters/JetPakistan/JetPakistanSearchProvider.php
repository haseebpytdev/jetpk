<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\SearchProvider;
use App\Models\AiEmbedTenant;

final class JetPakistanSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }
}
