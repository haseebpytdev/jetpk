<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\TenantConfigProvider;
use App\Models\AiEmbedTenant;

final class JetPakistanTenantConfigProvider implements TenantConfigProvider
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

        return [
            'display_name' => e((string) ($branding['display_name'] ?? $this->tenant->display_name ?: 'JetPakistan')),
            'assistant_name' => e((string) ($branding['assistant_name'] ?? $this->tenant->assistant_name ?: 'Ask JetPakistan')),
            'welcome_text' => e((string) ($branding['welcome_text'] ?? 'How can we help with your travel today?')),
            'support_label' => e((string) ($branding['support_label'] ?? 'JetPakistan Support')),
            'logo_url' => null,
            'theme' => [
                'primary' => e((string) (($this->tenant->theme['primary'] ?? null) ?: '#0b5fff')),
            ],
        ];
    }

    public function unavailableActions(): array
    {
        $base = rtrim((string) config('ai_embed.public_base_url', config('app.url')), '/');

        return [
            ['label' => 'Search Flights', 'href' => $base.'/#flight-search'],
            ['label' => 'Browse Groups', 'href' => $base.'/groups'],
            ['label' => 'Manage Booking', 'href' => $base.'/lookup-booking'],
            ['label' => 'Contact Support', 'href' => $base.'/support'],
        ];
    }
}
