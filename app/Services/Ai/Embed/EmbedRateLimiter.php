<?php

namespace App\Services\Ai\Embed;

use App\Models\AiEmbedTenant;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Support\Facades\RateLimiter;

final class EmbedRateLimiter
{
    public function tooManyAttempts(AiEmbedTenant $tenant, string $origin, string $bucket, int $maxAttempts, int $decaySeconds): bool
    {
        $key = $this->key($tenant, $origin, $bucket);

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    public function hit(AiEmbedTenant $tenant, string $origin, string $bucket, int $decaySeconds): void
    {
        RateLimiter::hit($this->key($tenant, $origin, $bucket), $decaySeconds);
    }

    private function key(AiEmbedTenant $tenant, string $origin, string $bucket): string
    {
        return 'ai-embed:'.$tenant->public_id.':'.sha1($origin).':'.$bucket;
    }

    public function assertCapability(AiEmbedTenant $tenant, string $capability): bool
    {
        return $tenant->hasCapability($capability);
    }

    public function requiresGeneralAi(AiEmbedTenant $tenant): bool
    {
        return $tenant->hasCapability(EmbedTenantCapability::GENERAL_AI);
    }
}
