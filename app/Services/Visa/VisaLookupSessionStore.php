<?php

namespace App\Services\Visa;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Opaque short-lived encrypted visa lookup session store.
 */
final class VisaLookupSessionStore
{
    public function __construct(
        private readonly ?Repository $cache = null,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public function put(string $ownerToken, array $state, int $ttlSeconds): string
    {
        $id = Str::random(40);
        $payload = [
            'owner' => hash('sha256', $ownerToken),
            'state' => encrypt($state),
            'created_at' => time(),
            'expires_at' => time() + $ttlSeconds,
        ];
        $this->store()->put($this->key($id), $payload, $ttlSeconds);

        return $id;
    }

    /**
     * @return array{owner:string,state:array<string,mixed>,created_at:int,expires_at:int}|null
     */
    public function get(string $id, string $ownerToken): ?array
    {
        $payload = $this->store()->get($this->key($id));
        if (! is_array($payload)) {
            return null;
        }
        if (($payload['owner'] ?? '') !== hash('sha256', $ownerToken)) {
            return null;
        }
        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            $this->forget($id);

            return null;
        }
        $state = decrypt($payload['state']);
        if (! is_array($state)) {
            return null;
        }

        return [
            'owner' => (string) $payload['owner'],
            'state' => $state,
            'created_at' => (int) $payload['created_at'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function update(string $id, string $ownerToken, array $state): bool
    {
        $existing = $this->get($id, $ownerToken);
        if ($existing === null) {
            return false;
        }
        $ttl = max(1, $existing['expires_at'] - time());
        $payload = [
            'owner' => hash('sha256', $ownerToken),
            'state' => encrypt($state),
            'created_at' => $existing['created_at'],
            'expires_at' => $existing['expires_at'],
        ];
        $this->store()->put($this->key($id), $payload, $ttl);

        return true;
    }

    public function forget(string $id): void
    {
        $this->store()->forget($this->key($id));
    }

    private function key(string $id): string
    {
        return 'visa_lookup_session:'.$id;
    }

    private function store(): Repository
    {
        return $this->cache ?? Cache::store();
    }
}
