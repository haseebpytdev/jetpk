<?php

namespace App\Services\Integrations;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Per-user / per-provider cooldown for connection tests.
 */
final class IntegrationTestThrottle
{
    public function __construct(
        private readonly int $cooldownSeconds = 20,
    ) {}

    public function assertAllowed(User $actor, string $provider, string $testType = 'connection'): void
    {
        $key = $this->key($actor, $provider, $testType);
        if (Cache::has($key)) {
            $retryAfter = (int) Cache::get($key.':ttl', $this->cooldownSeconds);
            throw new RuntimeException(
                "Connection test cooldown active. Retry in approximately {$retryAfter} seconds."
            );
        }
    }

    public function mark(User $actor, string $provider, string $testType = 'connection'): void
    {
        $key = $this->key($actor, $provider, $testType);
        Cache::put($key, true, $this->cooldownSeconds);
        Cache::put($key.':ttl', $this->cooldownSeconds, $this->cooldownSeconds);
    }

    public function inProgress(User $actor, string $provider, string $testType = 'connection'): bool
    {
        return Cache::has($this->key($actor, $provider, $testType));
    }

    private function key(User $actor, string $provider, string $testType): string
    {
        return 'integration_test:'.$actor->id.':'.$provider.':'.$testType;
    }
}
