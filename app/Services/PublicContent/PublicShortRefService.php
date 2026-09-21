<?php

namespace App\Services\PublicContent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Opaque public short references (WP7 Class B/C).
 *
 * Cache-backed for search-session TTL alignment; does not replace booking_reference.
 * Codes are cryptographically random — never sequential or reversible DB ids.
 */
final class PublicShortRefService
{
    public const PURPOSE_FLIGHT_SEARCH = 'flight_search';

    public const PURPOSE_SHARE = 'share';

    private const CACHE_PREFIX = 'jp_public_short_ref:';

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

    public function mintFlightSearch(string $searchId, int $ttlSeconds = 1800): string
    {
        return $this->mint(self::PURPOSE_FLIGHT_SEARCH, [
            'target_type' => 'search_id',
            'target_key' => $searchId,
        ], $ttlSeconds);
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
}
