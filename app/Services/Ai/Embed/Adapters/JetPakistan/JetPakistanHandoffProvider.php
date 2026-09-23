<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\SupportHandoffProvider;
use App\Models\AiEmbedTenant;

final class JetPakistanHandoffProvider implements SupportHandoffProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }
}
