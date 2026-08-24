<?php

namespace App\Services\Integrations\Managers;

use App\Contracts\Integrations\IntegrationManager;
use App\Enums\IntegrationHealthStatus;
use App\Enums\IntegrationOperationalStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Integrations\IntegrationHealthRecorder;
use App\Services\Integrations\IntegrationTestThrottle;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Integrations\IntegrationAuthorization;
use App\Support\Integrations\IntegrationDefinition;
use App\Support\Suppliers\SupplierRegistry;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Admin facade over SupplierConnection for flight/group suppliers.
 * Test Connection uses readiness only — never creates PNR/hold/ticket.
 */
final class SupplierIntegrationManager implements IntegrationManager
{
    public function __construct(
        private readonly IntegrationDefinition $definition,
        private readonly SupplierConnectionService $connections,
        private readonly IntegrationHealthRecorder $healthRecorder,
        private readonly IntegrationTestThrottle $throttle,
    ) {}

    public function code(): string
    {
        return $this->definition->code;
    }

    public function getStatus(?int $agencyId = null): IntegrationOperationalStatus
    {
        if (! $this->definition->adapterInstalled) {
            return IntegrationOperationalStatus::AdapterMissing;
        }

        $connection = $this->connection($agencyId);
        if ($connection === null) {
            return IntegrationOperationalStatus::NotConfigured;
        }

        if (! $connection->is_active || $connection->status === SupplierConnectionStatus::Inactive) {
            return IntegrationOperationalStatus::Disabled;
        }

        $latest = $this->healthRecorder->latest($this->code(), 'connection');
        if ($latest === null && blank($connection->last_test_status)) {
            return IntegrationOperationalStatus::NeverTested;
        }

        if ($latest?->status === IntegrationHealthStatus::AuthFailed
            || $connection->last_test_status === 'missing_credentials') {
            return IntegrationOperationalStatus::AuthenticationFailed;
        }

        if ($latest?->status === IntegrationHealthStatus::Healthy
            || in_array($connection->last_test_status, ['ready_for_review', 'air_shopping_success', 'success'], true)) {
            return IntegrationOperationalStatus::Connected;
        }

        if ($latest?->status === IntegrationHealthStatus::Degraded
            || $latest?->status === IntegrationHealthStatus::NetworkFailed
            || $latest?->status === IntegrationHealthStatus::ProviderError) {
            return IntegrationOperationalStatus::Degraded;
        }

        return IntegrationOperationalStatus::NeverTested;
    }

    public function getConfigurationSummary(?int $agencyId = null): array
    {
        $connection = $this->connection($agencyId);
        $connections = $this->connectionsForProvider($agencyId);
        $provider = SupplierProvider::tryFrom($this->code());
        $registryState = $connection !== null
            ? SupplierRegistry::stateForConnection($connection)
            : ($provider ? SupplierRegistry::stateForUnprovisioned($provider) : null);
        $latest = $this->healthRecorder->latest($this->code(), 'connection');

        return [
            'provider' => $this->code(),
            'name' => $this->definition->name,
            'adapter_installed' => $this->definition->adapterInstalled,
            'configured' => $connection !== null,
            'connection_id' => $connection?->id,
            'connection_count' => $connections->count(),
            'supports_multiple_connections' => true,
            'connections' => $connections->map(static function (SupplierConnection $row): array {
                return [
                    'id' => (string) $row->id,
                    'name' => (string) ($row->display_name ?: $row->name),
                    'environment' => $row->environment?->value,
                    'is_active' => (bool) $row->is_active,
                    'status' => $row->status?->value,
                    'credentials_configured' => is_array($row->credentials) && $row->credentials !== [],
                    'last_test_status' => $row->last_test_status,
                ];
            })->values()->all(),
            'environment' => $connection?->environment?->value,
            'is_active' => (bool) ($connection?->is_active),
            'status' => $this->getStatus($agencyId)->value,
            'status_label' => $this->getStatus($agencyId)->label(),
            'registry_state' => $registryState,
            'last_tested_at' => $connection?->last_tested_at?->toIso8601String() ?? $latest?->tested_at?->toIso8601String(),
            'last_test_status' => $connection?->last_test_status ?? $latest?->status?->value,
            'last_error' => $connection?->last_error ?? $latest?->sanitized_message,
            'capabilities' => $this->definition->capabilities,
            'legacy_manage_path' => '/admin/dashboard/integrations?provider='.$this->code(),
        ];
    }

    public function getSettingsDefinition(): array
    {
        $summary = $this->getConfigurationSummary();

        return [
            'sections' => [
                [
                    'key' => 'general',
                    'label' => 'General',
                    'fields' => [
                        ['key' => 'provider', 'label' => 'Provider', 'type' => 'text', 'readonly' => true],
                        ['key' => 'environment', 'label' => 'Environment', 'type' => 'text', 'readonly' => true],
                        ['key' => 'is_active', 'label' => 'Enabled', 'type' => 'boolean'],
                    ],
                ],
                [
                    'key' => 'credentials',
                    'label' => 'Credentials',
                    'fields' => [
                        [
                            'key' => 'legacy_notice',
                            'label' => 'Credential editing',
                            'type' => 'notice',
                            'help' => 'Sensitive supplier credentials continue to be edited through the supplier connection form (encrypted at rest). Use Settings to open the connection manager.',
                        ],
                    ],
                ],
                [
                    'key' => 'advanced',
                    'label' => 'Advanced / Dangerous Operations',
                    'fields' => [
                        [
                            'key' => 'safety_notice',
                            'label' => 'Production safety',
                            'type' => 'notice',
                            'help' => 'Live booking, ticketing, and cancellation gates remain under existing supplier safety controls and are not changed by the Integrations hub.',
                        ],
                    ],
                ],
            ],
            'values' => $summary,
        ];
    }

    public function saveSettings(User $actor, array $data, ?int $agencyId = null): array
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::MANAGE);
        $connection = $this->connection($agencyId);
        if ($connection === null) {
            throw new RuntimeException('Create a supplier connection before saving settings.');
        }

        Gate::forUser($actor)->authorize('update', $connection);

        if (array_key_exists('is_active', $data)) {
            $wantActive = (bool) $data['is_active'];
            if ($wantActive !== (bool) $connection->is_active) {
                IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
                $this->setActive($connection, $wantActive);
            }
        }

        return $this->getConfigurationSummary($agencyId);
    }

    public function testConnection(User $actor, ?int $agencyId = null): array
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::TEST);
        $this->throttle->assertAllowed($actor, $this->code(), 'connection');

        $connection = $this->connection($agencyId);
        if ($connection === null) {
            $this->healthRecorder->record(
                provider: $this->code(),
                testType: 'connection',
                status: IntegrationHealthStatus::ConfigurationIncomplete,
                actor: $actor,
                errorCode: 'CONFIGURATION_INCOMPLETE',
                message: 'No supplier connection configured.',
                meta: ['creates_pnr' => false, 'commercial_side_effects' => false],
            );

            return [
                'ok' => false,
                'status' => 'CONFIGURATION_INCOMPLETE',
                'message' => 'No supplier connection configured.',
                'http_status' => null,
                'latency_ms' => null,
                'tested_at' => now()->toIso8601String(),
            ];
        }

        Gate::forUser($actor)->authorize('update', $connection);
        $started = microtime(true);
        $result = $this->connections->testConnection($connection, $actor);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $this->throttle->mark($actor, $this->code(), 'connection');

        $ok = ($result['last_test_status'] ?? null) === 'ready_for_review';
        $status = $ok
            ? IntegrationHealthStatus::Healthy
            : (($result['last_test_status'] ?? '') === 'missing_credentials'
                ? IntegrationHealthStatus::ConfigurationIncomplete
                : IntegrationHealthStatus::ProviderError);

        $this->healthRecorder->record(
            provider: $this->code(),
            testType: 'connection',
            status: $status,
            actor: $actor,
            latencyMs: $latency,
            environment: $connection->environment?->value,
            errorCode: $ok ? null : ($result['last_test_status'] ?? 'failed'),
            message: $result['last_error'] ?? ($ok ? 'Credential readiness check passed (read-only).' : 'Readiness check failed.'),
            meta: [
                'creates_pnr' => false,
                'creates_hold' => false,
                'creates_ticket' => false,
                'commercial_side_effects' => false,
                'mode' => 'readiness_only',
            ],
        );

        AuditLog::query()->create([
            'agency_id' => $connection->agency_id,
            'user_id' => $actor->id,
            'action' => 'integration.supplier.connection_tested',
            'auditable_type' => SupplierConnection::class,
            'auditable_id' => $connection->id,
            'properties' => [
                'attributes' => [
                    'provider' => $this->code(),
                    'ok' => $ok,
                    'last_test_status' => $result['last_test_status'] ?? null,
                    'commercial_side_effects' => false,
                ],
            ],
        ]);

        return [
            'ok' => $ok,
            'status' => $ok ? 'CONNECTED' : 'CONFIGURATION_INCOMPLETE',
            'message' => $result['last_error'] ?? ($ok ? 'Credential readiness check passed (read-only).' : 'Readiness check failed.'),
            'http_status' => null,
            'latency_ms' => $latency,
            'tested_at' => now()->toIso8601String(),
            'commercial_side_effects' => false,
        ];
    }

    public function activate(User $actor, ?int $agencyId = null): void
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
        $connection = $this->requireConnection($agencyId);
        Gate::forUser($actor)->authorize('update', $connection);
        $this->setActive($connection, true);
    }

    public function deactivate(User $actor, ?int $agencyId = null): void
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
        $connection = $this->requireConnection($agencyId);
        Gate::forUser($actor)->authorize('update', $connection);
        $this->setActive($connection, false);
    }

    public function getHealth(?int $agencyId = null): array
    {
        $latest = $this->healthRecorder->latest($this->code());
        $history = $this->healthRecorder->history($this->code(), 25);

        return [
            'status' => $latest?->status?->value ?? 'never_tested',
            'latest' => $latest,
            'history' => $history->map(static fn ($row) => [
                'id' => $row->id,
                'test_type' => $row->test_type,
                'status' => $row->status->value,
                'latency_ms' => $row->latency_ms,
                'http_status' => $row->http_status,
                'environment' => $row->environment,
                'tested_at' => $row->tested_at?->toIso8601String(),
                'sanitized_error_code' => $row->sanitized_error_code,
                'sanitized_message' => $row->sanitized_message,
            ])->all(),
        ];
    }

    public function supportsTestTransaction(): bool
    {
        return false;
    }

    public function createTestTransaction(User $actor, array $options = [], ?int $agencyId = null): array
    {
        throw new RuntimeException('Test payments are not supported for flight suppliers.');
    }

    private function connection(?int $agencyId): ?SupplierConnection
    {
        return $this->connectionsForProvider($agencyId)->first();
    }

    /**
     * All agency-scoped connections for this provider (multi-connection aware).
     *
     * @return \Illuminate\Support\Collection<int, SupplierConnection>
     */
    private function connectionsForProvider(?int $agencyId)
    {
        $provider = SupplierProvider::tryFrom($this->code());
        if ($provider === null) {
            return collect();
        }

        $query = SupplierConnection::query()->where('provider', $provider);
        if ($agencyId !== null) {
            $query->where(function ($builder) use ($agencyId): void {
                $builder->where('agency_id', $agencyId)->orWhereNull('agency_id');
            });
        }

        return $query->orderByDesc('is_active')->orderBy('id')->get();
    }

    private function requireConnection(?int $agencyId): SupplierConnection
    {
        $connection = $this->connection($agencyId);
        if ($connection === null) {
            throw new RuntimeException('No supplier connection configured for '.$this->code());
        }

        return $connection;
    }

    private function setActive(SupplierConnection $connection, bool $active): void
    {
        $this->connections->updateConnection($connection, [
            'status' => $active ? SupplierConnectionStatus::Active : SupplierConnectionStatus::Inactive,
            'is_active' => $active,
        ]);
    }
}
