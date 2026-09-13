<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

/**
 * Resolves AirBlue endpoint, channel, protocol version, and credentials from SupplierConnection.
 */
class AirBlueConfigResolver
{
    public function apiChannel(SupplierConnection $connection): AirBlueApiChannel
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return AirBlueApiChannel::fromCredentials($credentials);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(SupplierConnection $connection): array
    {
        if ($this->apiChannel($connection)->isDeprecated()) {
            throw new AirBlueValidationException(
                'deprecated_channel',
                422,
                'AirBlue Crane NDC is no longer supported. Use PIA NDC (pia_ndc) for Hitit Crane NDC 20.1 or configure AirBlue Zapways OTA credentials.',
            );
        }

        return $this->resolveOta($connection);
    }

    /**
     * @deprecated AirBlue Crane NDC is retired; detection only via {@see resolve()}.
     *
     * @return array<string, mixed>
     */
    public function resolveNdc(SupplierConnection $connection): array
    {
        throw new AirBlueValidationException(
            'deprecated_channel',
            422,
            'AirBlue Crane NDC is no longer supported. Use PIA NDC (pia_ndc) for Hitit Crane NDC 20.1.',
        );
    }

    /**
     * @return array{
     *     api_channel: string,
     *     protocol_version: string,
     *     namespace: string,
     *     environment: string,
     *     is_test: bool,
     *     endpoint_url: string,
     *     client_id: string,
     *     client_key: string,
     *     agent_type: string,
     *     agent_id: string,
     *     agent_password: string,
     *     service_target: string,
     *     service_version: string,
     *     tls_cert_path: string,
     *     tls_key_path: string,
     *     carrier_code: string,
     *     currency: string
     * }
     */
    public function resolveOta(SupplierConnection $connection): array
    {
        if ($this->apiChannel($connection)->isDeprecated()) {
            throw new AirBlueValidationException(
                'deprecated_channel',
                422,
                'AirBlue Crane NDC is no longer supported. Use PIA NDC (pia_ndc) for Hitit Crane NDC 20.1 or configure AirBlue Zapways OTA credentials.',
            );
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials($credentials);
        $protocolConfig = $this->protocolConfig($protocol);
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientKey = trim((string) ($credentials['client_key'] ?? ''));
        $agentType = trim((string) ($credentials['agent_type'] ?? ''));
        $agentId = trim((string) ($credentials['agent_id'] ?? ''));
        $agentPassword = trim((string) ($credentials['agent_password'] ?? ''));
        $endpoint = trim((string) ($connection->base_url ?? ''));
        $isTest = $this->isTestEnvironment($connection);

        if ($clientId === '' || $clientKey === '' || $agentType === '' || $agentId === '' || $agentPassword === '') {
            throw new AirBlueValidationException(
                'missing_ota_credentials',
                422,
                'AirBlue Zapways OTA client ID, client key, agent type, agent ID, and agent password are required.',
            );
        }

        if ($endpoint === '') {
            $endpoint = $isTest
                ? (string) ($protocolConfig['default_qa_base_url'] ?? '')
                : (string) ($protocolConfig['default_base_url'] ?? '');
        }

        if ($endpoint === '') {
            throw new AirBlueValidationException(
                'missing_endpoint',
                422,
                'AirBlue Zapways OTA base URL is required. Configure the endpoint in API settings.',
            );
        }

        if ($protocol->isV3() && ! $isTest && ! (bool) ($protocolConfig['live_enabled'] ?? false)) {
            throw new AirBlueValidationException(
                'v3_live_not_enabled',
                422,
                'AirBlue Zapways OTA v3.0 LIVE is not enabled for this deployment. Use TEST/sandbox until supplier certification completes.',
            );
        }

        return [
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'protocol_version' => $protocol->value,
            'namespace' => (string) ($protocolConfig['namespace'] ?? $protocol->namespaceUri()),
            'environment' => $connection->environment?->value ?? 'sandbox',
            'is_test' => $isTest,
            'endpoint_url' => rtrim($endpoint, '/'),
            'client_id' => $clientId,
            'client_key' => $clientKey,
            'agent_type' => $agentType,
            'agent_id' => $agentId,
            'agent_password' => $agentPassword,
            'service_target' => $this->resolveServiceTarget($connection, $credentials),
            'service_version' => trim((string) ($credentials['service_version'] ?? '')) ?: '1.04',
            'tls_cert_path' => trim((string) ($credentials['tls_cert_path'] ?? '')),
            'tls_key_path' => trim((string) ($credentials['tls_key_path'] ?? '')),
            'carrier_code' => 'PA',
            'currency' => strtoupper(trim((string) ($credentials['currency'] ?? '')) ?: 'PKR'),
        ];
    }

    public function defaultOtaBaseUrl(bool $isTest, ?AirBlueZapwaysProtocolVersion $protocol = null): string
    {
        $protocol ??= AirBlueZapwaysProtocolVersion::V2;
        $protocolConfig = $this->protocolConfig($protocol);

        return $isTest
            ? (string) ($protocolConfig['default_qa_base_url'] ?? '')
            : (string) ($protocolConfig['default_base_url'] ?? '');
    }

    public function isTestEnvironment(SupplierConnection $connection): bool
    {
        $env = $connection->environment;

        return in_array($env, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function resolveServiceTarget(SupplierConnection $connection, array $credentials): string
    {
        $explicit = trim((string) ($credentials['service_target'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return $this->isTestEnvironment($connection) ? 'Test' : 'Production';
    }

    /**
     * @return array<string, mixed>
     */
    public function protocolOperationsConfig(AirBlueZapwaysProtocolVersion $protocol): array
    {
        $protocolConfig = $this->protocolConfig($protocol);

        return is_array($protocolConfig['ota_operations'] ?? null) ? $protocolConfig['ota_operations'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function protocolConfig(AirBlueZapwaysProtocolVersion $protocol): array
    {
        $versions = (array) config('suppliers.airblue.protocol_versions', []);
        $config = is_array($versions[$protocol->value] ?? null) ? $versions[$protocol->value] : [];

        if ($config !== []) {
            return $config;
        }

        return [
            'namespace' => $protocol->namespaceUri(),
            'default_base_url' => (string) config('suppliers.airblue.default_ota_base_url', ''),
            'default_qa_base_url' => (string) config('suppliers.airblue.default_ota_qa_base_url', ''),
            'ota_operations' => (array) config('suppliers.airblue.ota_operations', []),
        ];
    }
}
