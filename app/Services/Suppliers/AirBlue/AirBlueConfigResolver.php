<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

/**
 * Resolves AirBlue endpoint, channel, and credentials from SupplierConnection.
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
        return $this->apiChannel($connection) === AirBlueApiChannel::ZapwaysOta
            ? $this->resolveOta($connection)
            : $this->resolveNdc($connection);
    }

    /**
     * @return array{
     *     api_channel: string,
     *     environment: string,
     *     is_test: bool,
     *     endpoint_url: string,
     *     username: string,
     *     password: string,
     *     agency_id: string,
     *     agency_name: string,
     *     owner_code: string,
     *     carrier_code: string,
     *     currency: string,
     *     language_code: string,
     *     username_header: string,
     *     password_header: string
     * }
     */
    public function resolveNdc(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        $agencyId = trim((string) ($credentials['agency_id'] ?? ''));
        $agencyName = trim((string) ($credentials['agency_name'] ?? ''));
        $ownerCode = trim((string) ($credentials['owner_code'] ?? ''));
        $endpoint = trim((string) ($connection->base_url ?? ''));

        if ($username === '' || $password === '') {
            throw new AirBlueValidationException(
                'missing_credentials',
                422,
                'AirBlue Crane NDC username and password are required.',
            );
        }

        if ($agencyId === '' || $agencyName === '' || $ownerCode === '') {
            throw new AirBlueValidationException(
                'missing_agency_fields',
                422,
                'AirBlue Crane NDC agency ID, agency name, and owner code are required.',
            );
        }

        if ($endpoint === '') {
            $endpoint = (string) config('suppliers.airblue.default_ndc_base_url', '');
        }

        if ($endpoint === '') {
            throw new AirBlueValidationException(
                'missing_endpoint',
                422,
                'AirBlue Crane NDC base URL is required. Configure the endpoint in API settings.',
            );
        }

        return [
            'api_channel' => AirBlueApiChannel::CraneNdc->value,
            'environment' => $connection->environment?->value ?? 'sandbox',
            'is_test' => $this->isTestEnvironment($connection),
            'endpoint_url' => rtrim($endpoint, '/'),
            'username' => $username,
            'password' => $password,
            'agency_id' => $agencyId,
            'agency_name' => $agencyName,
            'owner_code' => $ownerCode,
            'carrier_code' => trim((string) ($credentials['carrier_code'] ?? '')) ?: 'PA',
            'currency' => strtoupper(trim((string) ($credentials['currency'] ?? '')) ?: 'PKR'),
            'language_code' => strtoupper(trim((string) ($credentials['language_code'] ?? '')) ?: 'EN'),
            'username_header' => (string) config('suppliers.airblue.username_header', 'username'),
            'password_header' => (string) config('suppliers.airblue.password_header', 'password'),
        ];
    }

    /**
     * @return array{
     *     api_channel: string,
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
     *     carrier_code: string,
     *     currency: string
     * }
     */
    public function resolveOta(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientKey = trim((string) ($credentials['client_key'] ?? ''));
        $agentType = trim((string) ($credentials['agent_type'] ?? ''));
        $agentId = trim((string) ($credentials['agent_id'] ?? ''));
        $agentPassword = trim((string) ($credentials['agent_password'] ?? ''));
        $endpoint = trim((string) ($connection->base_url ?? ''));

        if ($clientId === '' || $clientKey === '' || $agentType === '' || $agentId === '' || $agentPassword === '') {
            throw new AirBlueValidationException(
                'missing_ota_credentials',
                422,
                'AirBlue Zapways OTA client ID, client key, agent type, agent ID, and agent password are required.',
            );
        }

        if ($endpoint === '') {
            $endpoint = $this->isTestEnvironment($connection)
                ? (string) config('suppliers.airblue.default_ota_qa_base_url', '')
                : (string) config('suppliers.airblue.default_ota_base_url', '');
        }

        if ($endpoint === '') {
            throw new AirBlueValidationException(
                'missing_endpoint',
                422,
                'AirBlue Zapways OTA base URL is required. Configure the endpoint in API settings.',
            );
        }

        return [
            'api_channel' => AirBlueApiChannel::ZapwaysOta->value,
            'environment' => $connection->environment?->value ?? 'sandbox',
            'is_test' => $this->isTestEnvironment($connection),
            'endpoint_url' => rtrim($endpoint, '/'),
            'client_id' => $clientId,
            'client_key' => $clientKey,
            'agent_type' => $agentType,
            'agent_id' => $agentId,
            'agent_password' => $agentPassword,
            'service_target' => trim((string) ($credentials['service_target'] ?? '')) ?: 'Production',
            'service_version' => trim((string) ($credentials['service_version'] ?? '')) ?: '1.04',
            'tls_cert_path' => trim((string) ($credentials['tls_cert_path'] ?? '')),
            'carrier_code' => 'PA',
            'currency' => strtoupper(trim((string) ($credentials['currency'] ?? '')) ?: 'PKR'),
        ];
    }

    public function defaultNdcBaseUrl(): string
    {
        return (string) config('suppliers.airblue.default_ndc_base_url', '');
    }

    public function defaultOtaBaseUrl(bool $isTest): string
    {
        return $isTest
            ? (string) config('suppliers.airblue.default_ota_qa_base_url', '')
            : (string) config('suppliers.airblue.default_ota_base_url', '');
    }

    public function isTestEnvironment(SupplierConnection $connection): bool
    {
        $env = $connection->environment;

        return in_array($env, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true);
    }
}
