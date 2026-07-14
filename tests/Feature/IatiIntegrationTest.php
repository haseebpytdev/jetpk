<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Adapters\IatiFlightSupplierAdapter;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_iati_search_pipeline_returns_normalized_offers_with_http_fake(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['auth_code' => 'test-code', 'secret' => 'test-secret'],
        ]);

        $searchBody = file_get_contents(base_path('tests/Fixtures/iati/search_response_oneway.json'));

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/search' => Http::response($searchBody, 200, ['Content-Type' => 'application/json']),
        ]);

        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'adults' => 1,
        ]);

        $result = app(IatiFlightSupplierAdapter::class)->search($request, $connection);

        $this->assertSame(SupplierProvider::Iati, $result->supplier_provider);
        $this->assertCount(1, $result->offers);
        $this->assertSame('LHE', $result->offers[0]->origin);
    }

    #[Test]
    public function test_validate_offer_returns_customer_safe_message_on_unavailable(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['auth_code' => 'test-code', 'secret' => 'test-secret'],
        ]);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/search' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/search_response_oneway.json')),
                200,
            ),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'message' => 'offer expired',
                'error' => 'VA009',
            ], 409),
        ]);

        $search = app(IatiFlightSupplierAdapter::class)->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-07-01']),
            $connection,
        );

        $validation = app(IatiFlightSupplierAdapter::class)->validateOffer(
            $search->offers[0],
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-07-01']),
            $connection,
        );

        $this->assertFalse($validation->is_valid);
        $this->assertStringNotContainsString('token', strtolower(implode(' ', $validation->warnings)));
    }

    #[Test]
    public function test_platform_module_key_resolves_iati_supplier(): void
    {
        $this->assertSame('iati_supplier', app(PlatformModuleEnforcer::class)->providerModuleKey('iati'));
    }
}
