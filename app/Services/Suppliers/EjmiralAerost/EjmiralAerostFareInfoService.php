<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

class EjmiralAerostFareInfoService
{
    public function __construct(
        private readonly EjmiralAerostClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    public function fareInfo(SupplierConnection $connection, array $selection): array
    {
        $nld = (string) ($selection['nld'] ?? $selection['NLD'] ?? $selection['carsf'] ?? '');
        $hash = (string) ($selection['acceptance'] ?? $selection['hash'] ?? $selection['flight_hash'] ?? '');
        if ($nld === '' || $hash === '') {
            throw new EjmiralAerostValidationException('validation_error', 422, 'fare_info requires NLD/carsf and flight hash.');
        }

        $response = $this->client->post($connection, 'res/fare_info', [
            'NLD' => $nld,
            'acceptance' => $hash,
        ], ['operation' => 'fare_info']);

        $body = $response['body'];

        return [
            'base_fare' => (float) ($body['base_fare'] ?? $body['base'] ?? 0),
            'yq' => (float) ($body['YQ'] ?? $body['yq'] ?? 0),
            'yr' => (float) ($body['YR'] ?? $body['yr'] ?? 0),
            'tax' => (float) ($body['tax'] ?? $body['taxes'] ?? 0),
            'total' => (float) ($body['total'] ?? $body['amount'] ?? 0),
            'currency' => strtoupper((string) ($body['currency'] ?? 'PKR')),
            'passenger_breakdown' => is_array($body['passenger_breakdown'] ?? null) ? $body['passenger_breakdown'] : [],
            'raw_keys' => array_keys($body),
        ];
    }
}
