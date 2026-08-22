<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationCategory;
use App\Enums\IntegrationOperationalStatus;
use App\Support\Integrations\IntegrationRegistry;

/**
 * Builds Integration Hub overview metrics and provider cards (sanitized).
 */
final class IntegrationHubService
{
    public function __construct(
        private readonly IntegrationManagerResolver $managers,
    ) {}

    /**
     * @return array{metrics: array<string, int>, categories: list<array<string, string>>, integrations: list<array<string, mixed>>}
     */
    public function overview(?string $category = null, ?int $agencyId = null): array
    {
        $cards = [];
        foreach (IntegrationRegistry::all() as $definition) {
            if ($category !== null && $category !== '' && $category !== 'all'
                && $definition->category->value !== $category) {
                continue;
            }

            $manager = $this->managers->forDefinition($definition);
            $status = $manager->getStatus($agencyId);
            $summary = $manager->getConfigurationSummary($agencyId);

            $cards[] = array_merge($definition->toArray(), [
                'status' => $status->value,
                'status_label' => $status->label(),
                'summary' => $summary,
                'environment' => $summary['environment'] ?? null,
                'configured' => (bool) ($summary['configured'] ?? $summary['credentials_configured'] ?? false),
                'active' => (bool) ($summary['is_active'] ?? false),
                'needs_attention' => in_array($status, [
                    IntegrationOperationalStatus::AuthenticationFailed,
                    IntegrationOperationalStatus::Degraded,
                    IntegrationOperationalStatus::NotConfigured,
                ], true) && $definition->adapterInstalled,
            ]);
        }

        $allForMetrics = [];
        foreach (IntegrationRegistry::all() as $definition) {
            $status = $this->managers->forDefinition($definition)->getStatus($agencyId);
            $summary = $this->managers->forDefinition($definition)->getConfigurationSummary($agencyId);
            $allForMetrics[] = [
                'status' => $status,
                'configured' => (bool) ($summary['configured'] ?? $summary['credentials_configured'] ?? false),
                'active' => (bool) ($summary['is_active'] ?? false),
                'needs_attention' => in_array($status, [
                    IntegrationOperationalStatus::AuthenticationFailed,
                    IntegrationOperationalStatus::Degraded,
                    IntegrationOperationalStatus::NotConfigured,
                ], true) && $definition->adapterInstalled,
            ];
        }

        $categories = array_map(
            static fn (IntegrationCategory $cat): array => [
                'key' => $cat->value,
                'label' => $cat->label(),
            ],
            IntegrationRegistry::activeCategories(),
        );

        return [
            'subtitle' => 'Configure, test and monitor every external service connected to JetPakistan.',
            'metrics' => [
                'active' => count(array_filter($allForMetrics, static fn (array $row): bool => $row['active'])),
                'configured' => count(array_filter($allForMetrics, static fn (array $row): bool => $row['configured'])),
                'needs_attention' => count(array_filter($allForMetrics, static fn (array $row): bool => $row['needs_attention'])),
                'total' => count($allForMetrics),
            ],
            'categories' => array_merge([['key' => 'all', 'label' => 'All']], $categories),
            'integrations' => $cards,
            'wizard' => [
                'categories' => array_map(
                    static fn (IntegrationCategory $cat): array => [
                        'key' => $cat->value,
                        'label' => $cat->label(),
                    ],
                    IntegrationCategory::cases(),
                ),
                'providers' => array_map(
                    static fn ($definition): array => $definition->toArray(),
                    IntegrationRegistry::all(),
                ),
                'custom_api_activation_blocked' => true,
                'custom_api_message' => 'A generic API configured in Dashboard does not automatically become a functional flight supplier or payment gateway. A JetPakistan runtime adapter is still required.',
            ],
        ];
    }
}
