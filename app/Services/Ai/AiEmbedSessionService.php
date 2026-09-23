<?php

namespace App\Services\Ai;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\EmbedTenantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Opaque, origin-bound, tenant-bound embed sessions for cross-origin iframe AI transport.
 */
final class AiEmbedSessionService
{
    private const CACHE_PREFIX = 'ai_embed_sess:';

    public function __construct(
        private readonly EmbedTenantResolver $tenantResolver,
    ) {}

    /**
     * @return array{token: string, expires_at: string, tenant_public_id: string, parent_origin: string}|null
     */
    public function createSession(AiEmbedTenant $tenant, string $parentOrigin): ?array
    {
        if (! $this->isTenantSessionReady($tenant)) {
            return null;
        }

        $normalizedOrigin = $this->normalizeOrigin($parentOrigin);
        if ($normalizedOrigin === null || ! $this->isOriginAllowed($tenant, $normalizedOrigin)) {
            return null;
        }

        $ttl = max(60, (int) config('ai_embed.session_ttl_seconds', 14400));
        $rawToken = Str::random(48);
        $expiresAt = now()->addSeconds($ttl);

        $payload = [
            'tenant_id' => $tenant->id,
            'tenant_public_id' => $tenant->public_id,
            'parent_origin' => $normalizedOrigin,
            'visitor_raw' => Str::random(40),
            'conversation_public_id' => null,
            'created_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($this->cacheKey($rawToken), $payload, $expiresAt);

        return [
            'token' => $rawToken,
            'expires_at' => $payload['expires_at'],
            'tenant_public_id' => $tenant->public_id,
            'parent_origin' => $normalizedOrigin,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateToken(AiEmbedTenant $tenant, string $rawToken, ?string $parentOrigin = null): ?array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || strlen($rawToken) < 32) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = Cache::get($this->cacheKey($rawToken));
        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['tenant_id'] ?? 0) !== (int) $tenant->id) {
            return null;
        }

        if ($parentOrigin !== null && $parentOrigin !== '') {
            $normalized = $this->normalizeOrigin($parentOrigin);
            if ($normalized === null || ($payload['parent_origin'] ?? null) !== $normalized) {
                return null;
            }
        }

        $expiresAt = (string) ($payload['expires_at'] ?? '');
        if ($expiresAt !== '' && now()->greaterThan($expiresAt)) {
            Cache::forget($this->cacheKey($rawToken));

            return null;
        }

        $payload['tenant'] = $tenant;

        return $payload;
    }

    public function touchSession(string $rawToken): void
    {
        $key = $this->cacheKey($rawToken);
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            return;
        }

        $ttl = max(60, (int) config('ai_embed.session_ttl_seconds', 14400));
        $expiresAt = now()->addSeconds($ttl);
        $payload['expires_at'] = $expiresAt->toIso8601String();
        Cache::put($key, $payload, $expiresAt);
    }

    public function bindConversation(string $rawToken, string $conversationPublicId): void
    {
        $key = $this->cacheKey($rawToken);
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            return;
        }

        $payload['conversation_public_id'] = $conversationPublicId;
        $expiresAt = (string) ($payload['expires_at'] ?? '');
        $ttlDate = $expiresAt !== '' ? \Illuminate\Support\Carbon::parse($expiresAt) : now()->addSeconds(3600);
        Cache::put($key, $payload, $ttlDate);
    }

    public function invalidate(string $rawToken): void
    {
        Cache::forget($this->cacheKey($rawToken));
    }

    public function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ($origin === '' || strcasecmp($origin, 'null') === 0 || $origin === '*') {
            return null;
        }

        $parsed = parse_url($origin);
        if (! is_array($parsed)) {
            return null;
        }

        if (($parsed['scheme'] ?? '') !== 'https') {
            return null;
        }

        $host = (string) ($parsed['host'] ?? '');
        if ($host === '') {
            return null;
        }

        if (isset($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
            return null;
        }

        if (isset($parsed['query']) || isset($parsed['fragment'])) {
            return null;
        }

        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return 'https://'.$host.$port;
    }

    public function isOriginAllowed(AiEmbedTenant $tenant, string $normalizedOrigin): bool
    {
        $allowed = $tenant->normalizedAllowedOrigins();
        if ($allowed === []) {
            return false;
        }

        return in_array($normalizedOrigin, $allowed, true);
    }

    public function isTenantSessionReady(AiEmbedTenant $tenant): bool
    {
        return $this->tenantResolver->isGloballyEnabled()
            && $tenant->status === AiEmbedTenant::STATUS_ACTIVE
            && $tenant->embed_enabled
            && $tenant->keys()->where('status', \App\Models\AiEmbedTenantKey::STATUS_ACTIVE)->exists();
    }

    public function isEmbedPageAvailable(AiEmbedTenant $tenant): bool
    {
        return $this->isTenantSessionReady($tenant);
    }

    public function resolveTenantByEmbedKey(string $embedKey): ?AiEmbedTenant
    {
        return $this->tenantResolver->resolveOperationalByEmbedKey($embedKey);
    }

    private function cacheKey(string $rawToken): string
    {
        return self::CACHE_PREFIX.hash('sha256', $rawToken);
    }
}
