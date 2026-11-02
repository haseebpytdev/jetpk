<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Authentication\OneApiAuthService;
use App\Services\Suppliers\OneApi\Pricing\OneApiAirPriceRequestBuilder;
use App\Services\Suppliers\OneApi\Reservation\OneApiReadRequestBuilder;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Transport\OneApiRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneApiRestSoapCredentialSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rest_auth_uses_rest_identity_not_soap_when_distinct(): void
    {
        Cache::flush();
        Http::fake([
            'https://example.test/auth' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
            ),
        ]);

        $connection = $this->connection([
            'rest_username' => 'REST_USER_A',
            'rest_password' => 'REST_PASS_A',
            'soap_username' => 'SOAP_USER_B',
            'soap_password' => 'SOAP_PASS_B',
            'username' => 'LEGACY_USER',
            'password' => 'LEGACY_PASS',
        ]);

        app(OneApiAuthService::class)->getAccessToken($connection);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://example.test/auth'
                && ($data['login'] ?? null) === 'REST_USER_A'
                && ($data['password'] ?? null) === 'REST_PASS_A'
                && ($data['login'] ?? null) !== 'SOAP_USER_B'
                && ($data['password'] ?? null) !== 'SOAP_PASS_B';
        });
    }

    public function test_search_headers_use_rest_userid(): void
    {
        Cache::flush();
        Http::fake([
            'https://example.test/auth' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
            ),
            'https://example.test/search' => Http::response(['searchOnds' => []]),
        ]);

        $connection = $this->connection([
            'rest_username' => 'REST_HDR_USER',
            'rest_password' => 'REST_PASS',
            'soap_username' => 'SOAP_HDR_USER',
            'soap_password' => 'SOAP_PASS',
            'agent_code' => 'AGT99',
            'sales_channel' => 'OTA',
        ]);

        app(OneApiRestClient::class)->postSearch($connection, ['isReturn' => false, 'searchOnds' => []]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/search')) {
                return false;
            }

            return $request->hasHeader('X-AERO-USERID', 'REST_HDR_USER')
                && $request->hasHeader('X-AERO-AGENT-CODE', 'AGT99')
                && $request->hasHeader('X-AERO-SALES-CHANNEL', 'OTA')
                && $request->hasHeader('X-AERO-JOURNEY-TYPE', 'ONEWAY')
                && ! $request->hasHeader('X-AERO-USERID', 'SOAP_HDR_USER');
        });
    }

    public function test_soap_builders_use_soap_identity_not_rest_when_distinct(): void
    {
        $connection = $this->connection([
            'rest_username' => 'REST_USER_A',
            'rest_password' => 'REST_PASS_A',
            'soap_username' => 'SOAP_USER_B',
            'soap_password' => 'SOAP_PASS_B',
        ]);

        $priceXml = app(OneApiAirPriceRequestBuilder::class)->buildInitialPrice(
            $connection,
            [],
            [],
            'OneWay',
        );
        $readXml = app(OneApiReadRequestBuilder::class)->build($connection, 'PNRTEST');

        foreach ([$priceXml, $readXml] as $xml) {
            $this->assertStringContainsString('SOAP_USER_B', $xml);
            $this->assertStringContainsString('SOAP_PASS_B', $xml);
            $this->assertStringNotContainsString('REST_USER_A', $xml);
            $this->assertStringNotContainsString('REST_PASS_A', $xml);
        }

        $this->assertStringContainsString('ID="SOAP_USER_B"', $priceXml);
    }

    public function test_legacy_single_username_password_fallback(): void
    {
        $connection = $this->connection([
            'username' => 'LEGACY_ONLY_USER',
            'password' => 'LEGACY_ONLY_PASS',
        ]);

        $config = app(OneApiConfigResolver::class)->resolve($connection);
        $this->assertSame('LEGACY_ONLY_USER', $config['rest_username']);
        $this->assertSame('LEGACY_ONLY_PASS', $config['rest_password']);
        $this->assertSame('LEGACY_ONLY_USER', $config['soap_username']);
        $this->assertSame('LEGACY_ONLY_PASS', $config['soap_password']);
    }

    public function test_secret_log_leak_is_zero_via_redactor_contract(): void
    {
        $redacted = \App\Support\Security\SensitiveDataRedactor::redact([
            'supplier_connection_id' => 1,
            'password' => 'REST_PASS_SECRET',
            'rest_password' => 'REST_PASS_SECRET',
            'soap_password' => 'SOAP_PASS_SECRET',
            'login' => 'REST_USER_A',
        ]);

        $encoded = strtolower(json_encode($redacted) ?: '');
        $this->assertStringNotContainsString('rest_pass_secret', $encoded);
        $this->assertStringNotContainsString('soap_pass_secret', $encoded);
        // Auth logger only passes connection id / duration / status — never raw password fields.
        $authSafe = \App\Support\Security\SensitiveDataRedactor::redact([
            'supplier' => 'one_api',
            'supplier_connection_id' => 1,
            'duration_ms' => 12,
            'error_code' => null,
            'http_status' => 200,
        ]);
        $this->assertArrayNotHasKey('password', $authSafe);
        $this->assertArrayNotHasKey('rest_password', $authSafe);
        $this->assertArrayNotHasKey('soap_password', $authSafe);
    }

    /**
     * @param  array<string, string>  $credentialOverrides
     */
    private function connection(array $credentialOverrides = []): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => array_merge([
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'sales_channel' => 'OTA',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ], $credentialOverrides),
            'settings' => $this->oneApiConfirmedAccountSettings(),
        ]);
    }
}
