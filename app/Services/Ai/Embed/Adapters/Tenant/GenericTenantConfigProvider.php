<?php

namespace App\Services\Ai\Embed\Adapters\Tenant;

use App\Contracts\Ai\Embed\TenantConfigProvider;
use App\Models\AiEmbedTenant;

final class GenericTenantConfigProvider implements TenantConfigProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
    ) {}

    public function tenant(): AiEmbedTenant
    {
        return $this->tenant;
    }

    public function presentationConfig(): array
    {
        $branding = is_array($this->tenant->branding) ? $this->tenant->branding : [];
        $theme = is_array($this->tenant->theme) ? $this->tenant->theme : [];

        return [
            'display_name' => e($this->tenant->display_name),
            'assistant_name' => e($this->tenant->assistant_name),
            'welcome_text' => e((string) ($branding['welcome_text'] ?? 'How can we help you today?')),
            'support_label' => e((string) ($branding['support_label'] ?? 'Support')),
            'logo_url' => $this->sanitizeUrl((string) ($branding['logo_url'] ?? '')),
            'theme' => [
                'primary' => e((string) ($theme['primary'] ?? '#0b5fff')),
            ],
        ];
    }

    public function unavailableActions(): array
    {
        $settings = is_array($this->tenant->settings) ? $this->tenant->settings : [];
        $base = rtrim((string) ($settings['public_base_url'] ?? config('ai_embed.public_base_url', config('app.url'))), '/');

        return [
            ['label' => 'Contact Support', 'href' => $base.'/support'],
        ];
    }

    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        return $url;
    }
}
