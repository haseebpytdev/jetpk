<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class PiaNdcDiagnosticService
{
    public function __construct(
        private readonly PiaNdcConfigResolver $configResolver,
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
                safeMessage: 'PIA NDC credentials and endpoint configured.',
                meta: [
                    'environment' => $config['environment'],
                    'endpoint_configured' => $config['endpoint_url'] !== '',
                ],
            );

            return [
                'healthy' => true,
                'environment' => $config['environment'],
                'endpoint_url' => $config['endpoint_url'],
                'agency_id' => $config['agency_id'],
                'owner_code' => $config['owner_code'],
                'carrier_code' => $config['carrier_code'],
                'currency' => $config['currency'],
                'username_header' => $config['username_header'],
                'password_header' => $config['password_header'],
                'mco_invoice_configured' => $config['mco_invoice_number'] !== '',
                'payment_type' => $config['payment_type'],
            ];
        } catch (PiaNdcException $exception) {
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
