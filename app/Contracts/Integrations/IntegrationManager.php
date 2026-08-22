<?php

namespace App\Contracts\Integrations;

use App\Enums\IntegrationOperationalStatus;
use App\Models\User;

/**
 * Admin management facade over a provider runtime. Does not replace supplier/payment drivers.
 */
interface IntegrationManager
{
    public function code(): string;

    public function getStatus(?int $agencyId = null): IntegrationOperationalStatus;

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSummary(?int $agencyId = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getSettingsDefinition(): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveSettings(User $actor, array $data, ?int $agencyId = null): array;

    /**
     * @return array<string, mixed>
     */
    public function testConnection(User $actor, ?int $agencyId = null): array;

    public function activate(User $actor, ?int $agencyId = null): void;

    public function deactivate(User $actor, ?int $agencyId = null): void;

    /**
     * @return array<string, mixed>
     */
    public function getHealth(?int $agencyId = null): array;

    public function supportsTestTransaction(): bool;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createTestTransaction(User $actor, array $options = [], ?int $agencyId = null): array;
}
