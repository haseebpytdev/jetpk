<?php

namespace Tests\Support;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\EmbedTenantManager;
use App\Support\Ai\Embed\EmbedTenantCapability;

trait InteractsWithEmbedTenants
{
    protected function enableEmbedTenant(
        string $slug = 'jetpakistan',
        string $embedKey = 'test-embed-path-token12',
        array $allowedOrigins = ['https://client.example.com'],
        array $capabilities = [],
        bool $embedEnabled = true,
    ): AiEmbedTenant {
        config([
            'ai_embed.enabled' => true,
            'ai_embed.session_ttl_seconds' => 3600,
            'ai_embed.public_base_url' => 'https://jetpakistan.pk',
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
        ]);

        if ($capabilities === []) {
            $capabilities = AiEmbedTenant::jetPakistanCapabilities();
        }

        /** @var EmbedTenantManager $manager */
        $manager = app(EmbedTenantManager::class);
        $tenant = $manager->upsertTenant(
            slug: $slug,
            displayName: $slug === 'jetpakistan' ? 'JetPakistan' : strtoupper($slug),
            assistantName: $slug === 'jetpakistan' ? 'Ask JetPakistan' : 'Assistant '.$slug,
            allowedOrigins: $allowedOrigins,
            capabilities: $capabilities,
            embedEnabled: $embedEnabled,
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: $slug,
        );
        $manager->revokeActiveKeys($tenant);
        $manager->issueKey($tenant, $embedKey);

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();

        return $tenant->fresh() ?? $tenant;
    }

    protected function embedApiPath(string $embedKey, string $suffix): string
    {
        return '/api/embed/ai/'.$embedKey.$suffix;
    }
}
