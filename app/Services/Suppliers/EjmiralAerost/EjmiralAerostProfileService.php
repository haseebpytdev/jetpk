<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;

class EjmiralAerostProfileService
{
    public function __construct(
        private readonly EjmiralAerostClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function myProfile(SupplierConnection $connection): array
    {
        $response = $this->client->post($connection, 'my-profile', [], ['operation' => 'my_profile']);
        $body = $response['body'];

        return [
            'office' => (string) ($body['office'] ?? $body['office_name'] ?? ''),
            'currency' => strtoupper((string) ($body['currency'] ?? '')),
            'account_type' => (string) ($body['account_type'] ?? $body['type'] ?? ''),
            'available_limit_present' => array_key_exists('available_limit', $body)
                || array_key_exists('balance', $body)
                || array_key_exists('available_balance', $body),
            // Never expose raw financial identifiers in public evidence consumers.
            'balance_ready' => ((float) ($body['available_limit'] ?? $body['balance'] ?? $body['available_balance'] ?? 0)) > 0,
        ];
    }
}
