<?php

namespace App\Services\Ai\Embed;

use App\Models\AiEmbedTenant;
use App\Models\AiEmbedTenantKey;
use Illuminate\Support\Str;

final class EmbedTenantManager
{
    public function __construct(
        private readonly EmbedTenantResolver $resolver,
    ) {}

    /**
     * @param  list<string>  $allowedOrigins
     * @param  list<string>  $capabilities
     * @param  array<string, mixed>  $branding
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $settings
     */
    public function upsertTenant(
        string $slug,
        string $displayName,
        string $assistantName,
        array $allowedOrigins = [],
        array $capabilities = [],
        bool $embedEnabled = false,
        string $status = AiEmbedTenant::STATUS_DISABLED,
        string $knowledgeNamespace = 'default',
        array $branding = [],
        array $theme = [],
        array $settings = [],
    ): AiEmbedTenant {
        /** @var AiEmbedTenant $tenant */
        $tenant = AiEmbedTenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'display_name' => $displayName,
                'assistant_name' => $assistantName,
                'allowed_origins' => array_values($allowedOrigins),
                'capabilities' => array_values($capabilities),
                'embed_enabled' => $embedEnabled,
                'status' => $status,
                'knowledge_namespace' => $knowledgeNamespace,
                'branding' => $branding,
                'theme' => $theme,
                'settings' => $settings,
            ]
        );

        return $tenant->fresh() ?? $tenant;
    }

    /**
     * @return array{tenant: AiEmbedTenant, embed_key: string, key_prefix: string}
     */
    public function issueKey(AiEmbedTenant $tenant, ?string $rawKey = null): array
    {
        $material = $rawKey !== null
            ? [
                'raw' => $rawKey,
                'hash' => hash('sha256', $rawKey),
                'prefix' => substr($rawKey, 0, 8),
            ]
            : EmbedTenantResolver::generateEmbedKeyMaterial();

        AiEmbedTenantKey::query()->create([
            'ai_embed_tenant_id' => $tenant->id,
            'key_hash' => $material['hash'],
            'key_prefix' => $material['prefix'],
            'status' => AiEmbedTenantKey::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        return [
            'tenant' => $tenant,
            'embed_key' => $material['raw'],
            'key_prefix' => $material['prefix'],
        ];
    }

    public function revokeActiveKeys(AiEmbedTenant $tenant): int
    {
        return AiEmbedTenantKey::query()
            ->where('ai_embed_tenant_id', $tenant->id)
            ->where('status', AiEmbedTenantKey::STATUS_ACTIVE)
            ->update([
                'status' => AiEmbedTenantKey::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);
    }

    public function rotateKey(AiEmbedTenant $tenant): array
    {
        $this->revokeActiveKeys($tenant);

        return $this->issueKey($tenant);
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function setCapabilities(AiEmbedTenant $tenant, array $capabilities): AiEmbedTenant
    {
        $tenant->capabilities = array_values($capabilities);
        $tenant->save();

        return $tenant;
    }

    public function syncJetPakistanFromEnv(): AiEmbedTenant
    {
        $path = trim((string) env('AI_EMBED_JETPAKISTAN_PATH', ''));
        $origins = array_values(array_filter(array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', (string) env('AI_EMBED_JETPAKISTAN_ALLOWED_ORIGINS', ''))
        )));

        $tenant = $this->upsertTenant(
            slug: 'jetpakistan',
            displayName: 'JetPakistan',
            assistantName: 'Ask JetPakistan',
            allowedOrigins: $origins,
            capabilities: AiEmbedTenant::jetPakistanCapabilities(),
            embedEnabled: false,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: 'jetpakistan',
            branding: [
                'display_name' => 'JetPakistan',
                'assistant_name' => 'Ask JetPakistan',
            ],
            settings: [
                'public_base_url' => config('ai_embed.public_base_url'),
            ],
        );

        if ($path !== '') {
            $hash = hash('sha256', $path);
            $exists = AiEmbedTenantKey::query()
                ->where('ai_embed_tenant_id', $tenant->id)
                ->where('key_hash', $hash)
                ->where('status', AiEmbedTenantKey::STATUS_ACTIVE)
                ->exists();
            if (! $exists) {
                $this->issueKey($tenant, $path);
            }
        }

        return $tenant;
    }
}
