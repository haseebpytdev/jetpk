<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationCategory;
use App\Enums\IntegrationOperationalStatus;
use App\Support\Integrations\IntegrationDefinition;
use App\Support\Integrations\IntegrationRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds Integration Hub overview metrics and provider cards (sanitized).
 *
 * Each provider resolves independently. A single manager failure must not
 * crash the hub: that provider is rendered as DEGRADED / CONFIG ERROR and
 * the overview still returns HTTP 200-ready payload.
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

            $cards[] = $this->resolveProviderCard($definition, $agencyId);
        }

        $allForMetrics = [];
        foreach (IntegrationRegistry::all() as $definition) {
            $allForMetrics[] = $this->resolveProviderCard($definition, $agencyId);
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
                'active' => count(array_filter($allForMetrics, static fn (array $row): bool => (bool) ($row['active'] ?? false))),
                'configured' => count(array_filter($allForMetrics, static fn (array $row): bool => (bool) ($row['configured'] ?? false))),
                'needs_attention' => count(array_filter($allForMetrics, static fn (array $row): bool => (bool) ($row['needs_attention'] ?? false))),
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

    /**
     * Resolve one provider card. Failures are isolated and never rethrown.
     *
     * @return array<string, mixed>
     */
    private function resolveProviderCard(IntegrationDefinition $definition, ?int $agencyId): array
    {
        try {
            $manager = $this->managers->forDefinition($definition);
            $status = $manager->getStatus($agencyId);
            $summary = $manager->getConfigurationSummary($agencyId);

            return array_merge($definition->toArray(), [
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
                'resolution_error' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('integration_hub.provider_resolution_failed', [
                'provider' => $definition->code,
                'exception_class' => $e::class,
                'message' => mb_substr($e->getMessage(), 0, 180),
            ]);

            return $this->degradedCard($definition, $e);
        }
    }

    /**
     * Sanitized degraded card — no exception internals in admin JSON.
     *
     * @return array<string, mixed>
     */
    private function degradedCard(IntegrationDefinition $definition, Throwable $e): array
    {
        $status = IntegrationOperationalStatus::Degraded;
        $safeMessage = 'Configuration error — provider status could not be loaded.';

        return array_merge($definition->toArray(), [
            'status' => $status->value,
            'status_label' => 'Config error',
            'summary' => [
                'provider' => $definition->code,
                'name' => $definition->name,
                'adapter_installed' => $definition->adapterInstalled,
                'configured' => false,
                'credentials_configured' => false,
                'is_active' => false,
                'environment' => null,
                'status' => $status->value,
                'status_label' => 'Config error',
                'last_error' => $safeMessage,
                'resolution_error' => true,
            ],
            'environment' => null,
            'configured' => false,
            'active' => false,
            'needs_attention' => true,
            'resolution_error' => true,
            'error_code' => 'PROVIDER_RESOLUTION_FAILED',
            'error_message' => $safeMessage,
        ]);
    }
}
