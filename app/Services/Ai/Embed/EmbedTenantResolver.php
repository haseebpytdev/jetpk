<?php

namespace App\Services\Ai\Embed;

use App\Models\AiEmbedTenant;
use App\Models\AiEmbedTenantKey;
use Illuminate\Support\Str;

final class EmbedTenantResolver
{
    private const ENTRY_PATH_PATTERN = '/^[A-Za-z0-9_-]{16,128}$/';

    public function normalizeEmbedKey(string $token): ?string
    {
        $token = trim($token);
        if ($token === '' || preg_match(self::ENTRY_PATH_PATTERN, $token) !== 1) {
            return null;
        }

        return $token;
    }

    public function resolveByEmbedKey(string $embedKey): ?AiEmbedTenant
    {
        $normalized = $this->normalizeEmbedKey($embedKey);
        if ($normalized === null) {
            return null;
        }

        $hash = hash('sha256', $normalized);

        /** @var AiEmbedTenantKey|null $key */
        $key = AiEmbedTenantKey::query()
            ->where('key_hash', $hash)
            ->where('status', AiEmbedTenantKey::STATUS_ACTIVE)
            ->first();

        if ($key === null) {
            return null;
        }

        /** @var AiEmbedTenant|null $tenant */
        $tenant = AiEmbedTenant::query()->find($key->ai_embed_tenant_id);
        if ($tenant === null || $tenant->status !== AiEmbedTenant::STATUS_ACTIVE) {
            return null;
        }

        return $tenant;
    }

    public function resolveOperationalByEmbedKey(string $embedKey): ?AiEmbedTenant
    {
        $tenant = $this->resolveByEmbedKey($embedKey);
        if ($tenant === null || ! $this->isGloballyEnabled() || ! $tenant->embed_enabled) {
            return null;
        }

        return $tenant;
    }

    public function isGloballyEnabled(): bool
    {
        return (bool) config('ai_embed.enabled', false);
    }

    /**
     * @return array{raw: string, hash: string, prefix: string}
     */
    public static function generateEmbedKeyMaterial(): array
    {
        $raw = Str::random(32);

        return [
            'raw' => $raw,
            'hash' => hash('sha256', $raw),
            'prefix' => substr($raw, 0, 8),
        ];
    }
}
