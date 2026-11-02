<?php

namespace Tests\Support\OneApi;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\OneApiAccountContext;

/**
 * Shared One API supplier connection fixtures for PHPUnit (no live supplier calls).
 */
final class OneApiTestConnectionFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function confirmedAccountSettings(array $overrides = []): array
    {
        return array_replace_recursive([
            'account_context' => [
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
            ],
            'account_context_status' => OneApiAccountContext::STATUS_CONFIRMED,
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $credentialOverrides
     * @return array<string, string>
     */
    public static function credentials(array $credentialOverrides = []): array
    {
        return array_merge([
            'rest_username' => 'ONE_API_TEST_USERNAME',
            'rest_password' => 'ONE_API_TEST_PASSWORD',
            'soap_username' => 'ONE_API_TEST_USERNAME',
            'soap_password' => 'ONE_API_TEST_PASSWORD',
            'agent_code' => 'ONE_API_TEST_AGENT',
            'rest_auth_url' => 'https://example.test/auth',
            'rest_search_url' => 'https://example.test/search',
            'soap_url' => 'https://example.test/soap',
        ], $credentialOverrides);
    }

    /**
     * @param  array<string, string>  $credentialOverrides
     * @param  array<string, mixed>  $settingsOverrides
     */
    public static function createConnection(array $credentialOverrides = [], array $settingsOverrides = []): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => self::credentials($credentialOverrides),
            'settings' => self::confirmedAccountSettings($settingsOverrides),
        ]);
    }
}
