<?php

namespace Tests;

use App\Support\Suppliers\OneApiAccountContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function oneApiConfirmedAccountSettings(array $overrides = []): array
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
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function oneApiTestCredentials(array $overrides = []): array
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
        ], $overrides);
    }
}
