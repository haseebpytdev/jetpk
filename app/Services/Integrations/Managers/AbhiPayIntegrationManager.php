<?php

namespace App\Services\Integrations\Managers;

use App\Contracts\Integrations\IntegrationManager;
use App\Enums\IntegrationHealthStatus;
use App\Enums\IntegrationOperationalStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Integrations\AbhiPayDiagnosticPaymentService;
use App\Services\Integrations\IntegrationHealthRecorder;
use App\Services\Integrations\IntegrationTestThrottle;
use App\Services\Payments\PaymentGatewaySettingsService;
use App\Support\Integrations\IntegrationAuthorization;
use RuntimeException;

/**
 * Admin facade over PaymentGateway / AbhiPay runtime (does not replace AbhiPayGateway).
 */
final class AbhiPayIntegrationManager implements IntegrationManager
{
    public function __construct(
        private readonly PaymentGatewaySettingsService $settings,
        private readonly IntegrationHealthRecorder $healthRecorder,
        private readonly IntegrationTestThrottle $throttle,
        private readonly AbhiPayDiagnosticPaymentService $diagnosticPayments,
    ) {}

    public function code(): string
    {
        return PaymentGateway::CODE_ABHIPAY;
    }

    public function getStatus(?int $agencyId = null): IntegrationOperationalStatus
    {
        $gateway = $this->gateway($agencyId);
        if (! $gateway->exists || ! $gateway->isConfigured()) {
            return IntegrationOperationalStatus::NotConfigured;
        }
        if (! $gateway->is_active) {
            return IntegrationOperationalStatus::Disabled;
        }

        $latest = $this->healthRecorder->latest($this->code(), 'connection');
        if ($latest === null) {
            return IntegrationOperationalStatus::NeverTested;
        }

        return match ($latest->status) {
            IntegrationHealthStatus::Healthy => IntegrationOperationalStatus::Connected,
            IntegrationHealthStatus::AuthFailed => IntegrationOperationalStatus::AuthenticationFailed,
            IntegrationHealthStatus::Degraded,
            IntegrationHealthStatus::ProviderError,
            IntegrationHealthStatus::NetworkFailed => IntegrationOperationalStatus::Degraded,
            IntegrationHealthStatus::ConfigurationIncomplete => IntegrationOperationalStatus::NotConfigured,
            IntegrationHealthStatus::Disabled => IntegrationOperationalStatus::Disabled,
            default => IntegrationOperationalStatus::NeverTested,
        };
    }

    public function getConfigurationSummary(?int $agencyId = null): array
    {
        $gateway = $this->gateway($agencyId);
        $presented = $this->settings->presentAbhiPay($gateway);
        $latest = $this->healthRecorder->latest($this->code(), 'connection');

        return array_merge($presented, [
            'status' => $this->getStatus($agencyId)->value,
            'status_label' => $this->getStatus($agencyId)->label(),
            'last_connection_test_at' => $latest?->tested_at?->toIso8601String(),
            'last_connection_status' => $latest?->status?->value,
            'last_latency_ms' => $latest?->latency_ms,
            'last_error' => $latest?->sanitized_message,
        ]);
    }

    public function getSettingsDefinition(): array
    {
        $gateway = $this->gateway(null);

        return [
            'sections' => [
                [
                    'key' => 'general',
                    'label' => 'General',
                    'fields' => [
                        ['key' => 'provider', 'label' => 'Provider', 'type' => 'text', 'readonly' => true],
                        ['key' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => [
                            ['value' => 'test', 'label' => 'Test'],
                            ['value' => 'live', 'label' => 'Live'],
                        ]],
                        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
                        ['key' => 'api_version', 'label' => 'API', 'type' => 'text', 'readonly' => true],
                        ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url'],
                    ],
                ],
                [
                    'key' => 'credentials',
                    'label' => 'Credentials',
                    'fields' => [
                        ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'secret' => false],
                        ['key' => 'merchant_secret_key', 'label' => 'Merchant Secret Key', 'type' => 'password', 'secret' => true, 'replaceable' => true],
                    ],
                ],
                [
                    'key' => 'urls',
                    'label' => 'URLs',
                    'fields' => [
                        ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'url', 'readonly' => true],
                        ['key' => 'success_url', 'label' => 'Success URL', 'type' => 'url'],
                        ['key' => 'cancel_url', 'label' => 'Cancel URL', 'type' => 'url'],
                        ['key' => 'decline_url', 'label' => 'Decline URL', 'type' => 'url'],
                    ],
                ],
            ],
            'values' => $this->settings->presentAbhiPay($gateway),
        ];
    }

    public function saveSettings(User $actor, array $data, ?int $agencyId = null): array
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::MANAGE);
        $agency = $this->resolveAgency($actor, $agencyId);
        $gateway = $this->settings->findOrNewAbhiPay($agency->id);

        if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $gateway->is_active) {
            IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
        }

        $gateway = $this->settings->saveAbhiPay($agency, $actor, $data);

        return $this->settings->presentAbhiPay($gateway);
    }

    public function testConnection(User $actor, ?int $agencyId = null): array
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::TEST);
        $this->throttle->assertAllowed($actor, $this->code(), 'connection');

        $gateway = $this->gateway($agencyId);
        $result = $this->settings->testConnection($gateway);
        $this->throttle->mark($actor, $this->code(), 'connection');

        $status = match ($result['status'] ?? '') {
            'CONNECTED' => IntegrationHealthStatus::Healthy,
            'AUTHENTICATION_FAILED' => IntegrationHealthStatus::AuthFailed,
            'NETWORK_ERROR' => IntegrationHealthStatus::NetworkFailed,
            'CONFIGURATION_INCOMPLETE' => IntegrationHealthStatus::ConfigurationIncomplete,
            default => IntegrationHealthStatus::ProviderError,
        };

        $this->healthRecorder->record(
            provider: $this->code(),
            testType: 'connection',
            status: $status,
            actor: $actor,
            latencyMs: $result['latency_ms'] ?? null,
            httpStatus: $result['http_status'] ?? null,
            environment: $gateway->environment,
            errorCode: ($result['ok'] ?? false) ? null : ($result['status'] ?? 'error'),
            message: $result['message'] ?? null,
            meta: ['creates_order' => false],
        );

        AuditLog::query()->create([
            'agency_id' => $gateway->agency_id,
            'user_id' => $actor->id,
            'action' => 'integration.abhipay.connection_tested',
            'auditable_type' => PaymentGateway::class,
            'auditable_id' => $gateway->id ?: null,
            'properties' => [
                'attributes' => [
                    'status' => $result['status'] ?? null,
                    'ok' => (bool) ($result['ok'] ?? false),
                    'http_status' => $result['http_status'] ?? null,
                    'latency_ms' => $result['latency_ms'] ?? null,
                    'creates_order' => false,
                ],
            ],
        ]);

        return $result;
    }

    public function activate(User $actor, ?int $agencyId = null): void
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
        $agency = $this->resolveAgency($actor, $agencyId);
        $gateway = $this->settings->findOrNewAbhiPay($agency->id);
        if (! $gateway->isConfigured()) {
            throw new RuntimeException('Configure AbhiPay credentials before activating.');
        }

        $this->settings->saveAbhiPay($agency, $actor, [
            'is_active' => true,
            'environment' => $gateway->environment ?: 'test',
            'merchant_id' => null,
            'merchant_secret_key' => null,
            'base_url' => $gateway->base_url,
        ]);

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'integration.abhipay.enabled',
            'auditable_type' => PaymentGateway::class,
            'auditable_id' => $gateway->id,
            'properties' => ['attributes' => ['is_active' => true, 'environment' => $gateway->environment]],
        ]);
    }

    public function deactivate(User $actor, ?int $agencyId = null): void
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::ACTIVATE);
        $agency = $this->resolveAgency($actor, $agencyId);
        $gateway = $this->settings->findOrNewAbhiPay($agency->id);

        $this->settings->saveAbhiPay($agency, $actor, [
            'is_active' => false,
            'environment' => $gateway->environment ?: 'test',
            'merchant_id' => null,
            'merchant_secret_key' => null,
            'base_url' => $gateway->base_url,
        ]);

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'integration.abhipay.disabled',
            'auditable_type' => PaymentGateway::class,
            'auditable_id' => $gateway->id,
            'properties' => ['attributes' => ['is_active' => false]],
        ]);
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
        return true;
    }

    public function createTestTransaction(User $actor, array $options = [], ?int $agencyId = null): array
    {
        IntegrationAuthorization::assert($actor, IntegrationAuthorization::TEST_PAYMENT);

        return $this->diagnosticPayments->create($actor, $options, $agencyId);
    }

    private function gateway(?int $agencyId): PaymentGateway
    {
        $agency = $agencyId !== null
            ? Agency::query()->findOrFail($agencyId)
            : Agency::query()->orderBy('id')->first();

        if ($agency === null) {
            return new PaymentGateway([
                'code' => PaymentGateway::CODE_ABHIPAY,
                'name' => 'AbhiPay',
                'environment' => 'test',
                'base_url' => PaymentGateway::DEFAULT_BASE_URL,
            ]);
        }

        return $this->settings->findOrNewAbhiPay($agency->id);
    }

    private function resolveAgency(User $actor, ?int $agencyId): Agency
    {
        if ($agencyId !== null) {
            return Agency::query()->findOrFail($agencyId);
        }

        if ($actor->current_agency_id) {
            return Agency::query()->findOrFail($actor->current_agency_id);
        }

        $agency = Agency::query()->orderBy('id')->first();
        abort_if($agency === null, 422, 'No agency available for payment gateway configuration.');

        return $agency;
    }
}
