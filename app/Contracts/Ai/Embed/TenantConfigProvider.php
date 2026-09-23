<?php

namespace App\Contracts\Ai\Embed;

use App\Models\AiEmbedTenant;

interface TenantConfigProvider
{
    public function tenant(): AiEmbedTenant;

    /**
     * @return array<string, mixed>
     */
    public function presentationConfig(): array;

    /**
     * @return list<array{label: string, href: string}>
     */
    public function unavailableActions(): array;
}
