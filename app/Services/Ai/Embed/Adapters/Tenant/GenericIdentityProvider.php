<?php

namespace App\Services\Ai\Embed\Adapters\Tenant;

use App\Contracts\Ai\Embed\IdentityProvider;
use App\Models\AiEmbedTenant;

final class GenericIdentityProvider implements IdentityProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function consentSource(): string
    {
        return 'embed_'.$this->tenant->slug;
    }

    public function leadSource(): string
    {
        return 'embed_'.$this->tenant->slug;
    }
}
