<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferShopRequestBuilder;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcSearchMarketMatrixService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreNdcSearchMarketMatrixCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.search_enabled', true);
        Config::set('suppliers.sabre.ndc.search_market_matrix_sleep_ms', 0);
    }

    public function test_matrix_send_requires_confirmation_phrase(): void
    {
        Http::fake();
        $connection = $this->ndcConnection();

        $exit = Artisan::call('sabre:ndc-search-market-matrix', [
            '--connection' => (string) $connection->id,
            '--routes' => 'LHE-DXB',
            '--start-date' => '2026-07-16',
            '--days' => '1',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        Http::assertNothingSent();
    }

    public function test_matrix_send_calls_only_v5_offers_shop(): void
    {
        $connection = $this->ndcConnection();
        $fixture = [
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'SERVER',
                    'code' => 'GCA14-ISELL-TN-00-2025-03-00-MXK4',
                    'text' => '27131',
                ]],
            ],
        ];

        Http::fake(function (Request $httpRequest) use ($fixture) {
            $url = $httpRequest->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'matrix-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v5/offers/shop')) {
                return Http::response($fixture, 200);
            }
            if (str_contains($url, '/v4/offers/shop')) {
                return Http::response(['error' => 'gds_must_not_run'], 500);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $exit = Artisan::call('sabre:ndc-search-market-matrix', [
            '--connection' => (string) $connection->id,
            '--routes' => 'LHE-DXB,KHI-DXB',
            '--start-date' => '2026-07-16',
            '--days' => '1',
            '--variant' => SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
            '--send' => true,
            '--confirm' => SabreNdcSearchMarketMatrixService::CONFIRM_PHRASE,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        Http::assertSent(fn (Request $req): bool => str_contains($req->url(), '/v5/offers/shop'));
        Http::assertNotSent(fn (Request $req): bool => str_contains($req->url(), '/v4/offers/shop'));
        $this->assertStringContainsString('LHE-DXB', $output);
        $this->assertStringContainsString('KHI-DXB', $output);
        $this->assertStringContainsString('ndc_zero_offers', $output);
        $this->assertStringContainsString('variant=ndc_v5_pos_pcc_source', $output);
        $this->assertStringContainsString('message_code=27131', $output);
        $this->assertStringContainsString('transaction_id=GCA14-ISELL-TN-00-2025-03-00-MXK4', $output);
        $this->assertStringNotContainsString('message_text=27131', $output);
    }

    public function test_matrix_marketing_carrier_filter_applies_vendor_pref(): void
    {
        $connection = $this->ndcConnection();
        Http::fake(function (Request $httpRequest) {
            $url = $httpRequest->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'matrix-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v5/offers/shop')) {
                $body = json_decode($httpRequest->body(), true);
                $vendor = data_get($body, 'OTA_AirLowFareSearchRQ.TravelPreferences.VendorPref.0.Code');

                return Http::response([
                    'groupedItineraryResponse' => ['itineraryGroups' => [], 'messages' => []],
                    'carrier_sent' => $vendor,
                ], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        Artisan::call('sabre:ndc-search-market-matrix', [
            '--connection' => (string) $connection->id,
            '--routes' => 'LHE-DXB',
            '--start-date' => '2026-07-16',
            '--days' => '1',
            '--carriers' => 'EK',
            '--carrier-mode' => 'marketing',
            '--variant' => SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
            '--send' => true,
            '--confirm' => SabreNdcSearchMarketMatrixService::CONFIRM_PHRASE,
        ]);

        Http::assertSent(function (Request $req): bool {
            if (! str_contains($req->url(), '/v5/offers/shop')) {
                return false;
            }
            $body = json_decode($req->body(), true);

            return data_get($body, 'OTA_AirLowFareSearchRQ.TravelPreferences.VendorPref.0.Code') === 'EK';
        });
    }

    private function ndcConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => [
                'sabre_gds_enabled' => false,
                'sabre_ndc_enabled' => true,
            ],
            'credentials' => [
                'client_id' => 'matrix-client',
                'client_secret' => 'matrix-secret',
                'pcc' => 'NDCS',
            ],
        ]);
    }
}
