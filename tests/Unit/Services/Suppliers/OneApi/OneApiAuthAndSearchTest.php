<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Authentication\OneApiAuthService;
use App\Services\Suppliers\OneApi\Normalization\OneApiOfferTokenSigner;
use App\Services\Suppliers\OneApi\Search\OneApiSearchRequestBuilder;
use App\Services\Suppliers\OneApi\Search\OneApiSearchResponseParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneApiAuthAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_search_uses_actual_return_date(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
        ]);

        $builder = app(OneApiSearchRequestBuilder::class);
        $request = new FlightSearchRequestData(
            origin: 'SHJ',
            destination: 'KHI',
            departure_date: '2026-08-15',
            return_date: '2026-08-22',
            trip_type: 'return',
            adults: 1,
        );

        $payload = $builder->build($request, $connection);
        $this->assertTrue($payload['isReturn']);
        $this->assertSame('2026-08-22', $payload['searchOnds'][1]['searchStartDate']);
        $this->assertSame('2026-08-22', $payload['searchOnds'][1]['preferredDate']);
    }

    public function test_search_parser_filters_not_available(): void
    {
        $json = json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/search_oneway.json')), true);
        $parser = app(OneApiSearchResponseParser::class);
        $options = $parser->parse($json, ['carrier_allowlist' => [], 'allow_interline' => true], 'corr-fixture');
        $this->assertCount(1, $options);
    }

    public function test_offer_token_tamper_rejected(): void
    {
        $signer = app(OneApiOfferTokenSigner::class);
        $token = $signer->sign(['supplier' => 'one_api', 'expires_at' => time() + 3600, 'segments' => []]);
        $parts = explode('|', $token, 2);
        $tampered = $parts[0].'|'.str_repeat('a', 64);
        $this->expectException(\InvalidArgumentException::class);
        $signer->verify($tampered);
    }

    public function test_auth_caches_token_per_connection(): void
    {
        Cache::flush();
        Http::fake([
            'https://example.test/auth' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
        ]);

        $auth = app(OneApiAuthService::class);
        $first = $auth->getAccessToken($connection);
        $second = $auth->getAccessToken($connection);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }
}
