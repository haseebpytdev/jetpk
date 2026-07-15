<?php

namespace App\Services\Suppliers\Iati;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class IatiBalanceService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    /**
     * @return array{supported: bool, balance: ?float, currency: ?string, message: string}
     */
    public function checkBalance(SupplierConnection $connection): array
    {
        try {
            $response = $this->client->get($connection, '/balance', [
                'request_context' => 'balance',
            ]);
            $data = $response;
            if (isset($response['result']) && is_array($response['result'])) {
                $data = $response['result'];
            }

            $balance = isset($data['balance']) ? (float) $data['balance'] : (isset($data['credit']) ? (float) $data['credit'] : null);
            $currency = isset($data['currency']) ? strtoupper((string) $data['currency']) : null;

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'balance_check',
                status: 'success',
                safeMessage: 'IATI balance retrieved.',
            );

            return [
                'supported' => true,
                'balance' => $balance,
                'currency' => $currency,
                'message' => 'Balance endpoint responded.',
            ];
        } catch (IatiException $exception) {
            if (in_array($exception->httpStatus, [404, 405], true) || $exception->normalizedCode === 'supplier_provider_error') {
                return [
                    'supported' => false,
                    'balance' => null,
                    'currency' => null,
                    'message' => 'IATI balance endpoint not available or not supported.',
                ];
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'balance_check',
                status: 'failed',
                safeMessage: $exception->safeMessage,
            );

            return [
                'supported' => false,
                'balance' => null,
                'currency' => null,
                'message' => $exception->safeMessage,
            ];
        }
    }
}
