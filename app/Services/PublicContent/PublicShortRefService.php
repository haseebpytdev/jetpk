<?php

namespace App\Services\PublicContent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Opaque public short references (WP7 Class B/C).
 *
 * Cache-backed for search-session TTL alignment; does not replace booking_reference.
 * Codes are cryptographically random — never sequential or reversible DB ids.
 *
 * Production note: with CACHE_STORE=file on a single host, forward + reverse maps are
 * acceptable for search TTL when the cache filesystem is shared across PHP workers on
 * that node. Multi-host without a shared cache store may mint divergent codes.
 */
final class PublicShortRefService
{
    public const PURPOSE_FLIGHT_SEARCH = 'flight_search';

    public const PURPOSE_SHARE = 'share';

    private const CACHE_PREFIX = 'jp_public_short_ref:';

    private const REVERSE_PREFIX = 'jp_public_short_ref_rev:';

    private const CODE_BYTES = 9;

    /**
     * @param  array{target_type: string, target_key: string, meta?: array<string, mixed>}  $payload
     */
    public function mint(string $purpose, array $payload, int $ttlSeconds = 1800): string
    {
        $code = $this->generateCode();
        $record = [
            'code' => $code,
            'purpose' => $purpose,
            'target_type' => (string) ($payload['target_type'] ?? ''),
            'target_key' => (string) ($payload['target_key'] ?? ''),
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds(max(60, $ttlSeconds))->toIso8601String(),
        ];

        Cache::put($this->cacheKey($code), $record, max(60, $ttlSeconds));

        return $code;
    }

    /**
     * @return array{code: string, purpose: string, target_type: string, target_key: string, meta: array<string, mixed>, created_at: string, expires_at: string}|null
     */
    public function resolve(string $code): ?array
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '' || ! preg_match('/^[a-z0-9]{8,32}$/', $normalized)) {
            return null;
        }

        $record = Cache::get($this->cacheKey($normalized));

        return is_array($record) ? $record : null;
    }

    /**
     * Reverse lookup: purpose + target → existing short code (cache-backed).
     */
    public function findCodeForTarget(string $purpose, string $targetType, string $targetKey): ?string
    {
        $targetKey = trim($targetKey);
        if ($purpose === '' || $targetType === '' || $targetKey === '') {
            return null;
        }

        $code = Cache::get($this->reverseCacheKey($purpose, $targetType, $targetKey));

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Idempotent mint for a flight search session. Reuses an existing reverse map
     * when the forward record still resolves to the same search_id.
     */
    public function mintFlightSearch(string $searchId, int $ttlSeconds = 1800): string
    {
        $searchId = trim($searchId);
        $ttl = max(60, $ttlSeconds);

        if ($searchId === '') {
            return $this->mint(self::PURPOSE_FLIGHT_SEARCH, [
                'target_type' => 'search_id',
                'target_key' => $searchId,
            ], $ttl);
        }

        $existing = $this->findCodeForTarget(self::PURPOSE_FLIGHT_SEARCH, 'search_id', $searchId);
        if ($existing !== null) {
            $record = $this->resolve($existing);
            if (
                is_array($record)
                && ($record['purpose'] ?? '') === self::PURPOSE_FLIGHT_SEARCH
                && ($record['target_type'] ?? '') === 'search_id'
                && ($record['target_key'] ?? '') === $searchId
            ) {
                return $existing;
            }
        }

        $code = $this->mint(self::PURPOSE_FLIGHT_SEARCH, [
            'target_type' => 'search_id',
            'target_key' => $searchId,
        ], $ttl);

        Cache::put(
            $this->reverseCacheKey(self::PURPOSE_FLIGHT_SEARCH, 'search_id', $searchId),
            $code,
            $ttl
        );

        return $code;
    }

    private function generateCode(): string
    {
        // Base62-ish lowercase alphanumeric without ambiguous chars.
        return strtolower(Str::random(self::CODE_BYTES * 2));
    }

    private function cacheKey(string $code): string
    {
        return self::CACHE_PREFIX.$code;
    }

    private function reverseCacheKey(string $purpose, string $targetType, string $targetKey): string
    {
        return self::REVERSE_PREFIX.$purpose.':'.$targetType.':'.hash('sha256', $targetKey);
    }
}
