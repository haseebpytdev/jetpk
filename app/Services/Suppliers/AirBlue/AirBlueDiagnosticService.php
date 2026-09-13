<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class AirBlueDiagnosticService
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function healthCheck(SupplierConnection $connection): array
    {
        try {
            $config = $this->configResolver->resolve($connection);
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'health',
                status: 'success',
                safeMessage: 'AirBlue credentials and endpoint configured.',
                meta: [
                    'environment' => $config['environment'],
                    'api_channel' => $config['api_channel'] ?? null,
                    'endpoint_configured' => ($config['endpoint_url'] ?? '') !== '',
                ],
            );

            return [
                'healthy' => true,
                'api_channel' => $config['api_channel'] ?? null,
                'environment' => $config['environment'],
                'endpoint_url' => $config['endpoint_url'],
                'credential_fields_present' => [
                    'client_id' => ($config['client_id'] ?? '') !== '',
                    'client_key' => ($config['client_key'] ?? '') !== '',
                    'agent_type' => ($config['agent_type'] ?? '') !== '',
                    'agent_id' => ($config['agent_id'] ?? '') !== '',
                    'agent_password' => ($config['agent_password'] ?? '') !== '',
                ],
            ];
        } catch (AirBlueException $exception) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'health',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: ['error_code' => $exception->normalizedCode],
            );

            return [
                'healthy' => false,
                'error_code' => $exception->normalizedCode,
                'message' => $exception->safeMessage,
            ];
        }
    }
}
