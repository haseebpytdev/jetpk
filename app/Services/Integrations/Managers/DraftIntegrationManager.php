<?php

namespace App\Services\Integrations\Managers;

use App\Contracts\Integrations\IntegrationManager;
use App\Enums\IntegrationOperationalStatus;
use App\Models\User;
use App\Support\Integrations\IntegrationDefinition;
use RuntimeException;

/**
 * Placeholder / custom-API shell — never activates runtime without an approved adapter.
 */
final class DraftIntegrationManager implements IntegrationManager
{
    public function __construct(
        private readonly IntegrationDefinition $definition,
    ) {}

    public function code(): string
    {
        return $this->definition->code;
    }

    public function getStatus(?int $agencyId = null): IntegrationOperationalStatus
    {
        return $this->definition->adapterInstalled
            ? IntegrationOperationalStatus::NotConfigured
            : IntegrationOperationalStatus::Draft;
    }

    public function getConfigurationSummary(?int $agencyId = null): array
    {
        return [
            'configured' => false,
            'adapter_installed' => $this->definition->adapterInstalled,
            'can_activate_runtime' => false,
            'message' => 'A JetPakistan runtime adapter is required before this integration can process live traffic.',
        ];
    }

    public function getSettingsDefinition(): array
    {
        return [
            'sections' => [
                [
                    'key' => 'general',
                    'label' => 'General',
                    'fields' => [
                        ['key' => 'name', 'label' => 'Display name', 'type' => 'text', 'readonly' => true],
                        ['key' => 'category', 'label' => 'Category', 'type' => 'text', 'readonly' => true],
                    ],
                ],
            ],
            'values' => [
                'name' => $this->definition->name,
                'category' => $this->definition->category->label(),
            ],
        ];
    }

    public function saveSettings(User $actor, array $data, ?int $agencyId = null): array
    {
        throw new RuntimeException('Draft integrations cannot persist runtime credentials until an approved adapter exists.');
    }

    public function testConnection(User $actor, ?int $agencyId = null): array
    {
        throw new RuntimeException('Connection testing is unavailable until a runtime adapter is installed.');
    }

    public function activate(User $actor, ?int $agencyId = null): void
    {
        throw new RuntimeException('Runtime activation blocked: no approved JetPakistan adapter for '.$this->definition->code.'.');
    }

    public function deactivate(User $actor, ?int $agencyId = null): void
    {
        // No-op for drafts.
    }

    public function getHealth(?int $agencyId = null): array
    {
        return [
            'status' => 'never_tested',
            'history' => [],
        ];
    }

    public function supportsTestTransaction(): bool
    {
        return false;
    }

    public function createTestTransaction(User $actor, array $options = [], ?int $agencyId = null): array
    {
        throw new RuntimeException('Test payments are not supported for draft integrations.');
    }
}
