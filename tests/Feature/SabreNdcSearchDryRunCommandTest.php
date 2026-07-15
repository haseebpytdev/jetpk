<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcSearchDryRunService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreNdcSearchDryRunCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.search_enabled', false);
    }

    public function test_dry_run_validates_gates_without_http(): void
    {
        Http::fake();
        $connection = $this->ndcConnection();

        Artisan::call('sabre:ndc-search-dry-run', [
            '--connection' => (string) $connection->id,
            '--origin' => 'LHE',
            '--destination' => 'DXB',
            '--date' => '2026-07-16',
            '--adults' => '1',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('dry_run=true', $output);
        $this->assertStringContainsString('request_shape_summary=', $output);
        $this->assertStringContainsString('selected_variant=', $output);
        $this->assertStringContainsString('pcc_present=true', $output);
        $this->assertStringContainsString('search_disabled_by_env', $output);
        $this->assertStringContainsString('no_offer_reason=ndc_live_search_disabled', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_send_requires_confirmation_phrase(): void
    {
        Http::fake();
        $connection = $this->ndcConnection();
        Config::set('suppliers.sabre.ndc.search_enabled', true);

        $exit = Artisan::call('sabre:ndc-search-dry-run', [
            '--connection' => (string) $connection->id,
            '--origin' => 'LHE',
            '--destination' => 'DXB',
            '--date' => '2026-07-16',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        Http::assertNothingSent();
    }

    public function test_send_calls_only_v5_offers_shop(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);
        Cache::flush();
        $connection = $this->ndcConnection();
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')), true);

        Http::fake(function (Request $httpRequest) use ($fixture) {
            $url = $httpRequest->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'dry-run-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v5/offers/shop')) {
                return Http::response(is_array($fixture) ? $fixture : [], 200);
            }
            if (str_contains($url, '/v4/offers/shop')) {
                return Http::response(['error' => 'gds_must_not_run'], 500);
            }

            return Http::response(['error' => 'unexpected url'], 500);
        });

        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-07-16',
            adults: 1,
            search_id: 'dry-run-send-test',
        );

        $serviceResult = app(SabreNdcSearchDryRunService::class)->run($connection, $request, true);
        $this->assertSame([], $serviceResult['blockers'] ?? ['blocked'], json_encode($serviceResult));
        $this->assertTrue($serviceResult['live_supplier_call_attempted'] ?? false, json_encode($serviceResult));
        $this->assertSame(200, $serviceResult['http_status'] ?? 0, json_encode($serviceResult));

        $exit = Artisan::call('sabre:ndc-search-dry-run', [
            '--connection' => (string) $connection->id,
            '--origin' => 'LHE',
            '--destination' => 'DXB',
            '--date' => '2026-07-16',
            '--send' => true,
            '--confirm' => 'SEND-SABRE-NDC-SEARCH',
        ]);

        $this->assertSame(0, $exit);
        Http::assertSent(fn (Request $req): bool => str_contains($req->url(), '/v5/offers/shop'));
        Http::assertNotSent(fn (Request $req): bool => str_contains($req->url(), '/v4/offers/shop'));
        $this->assertStringContainsString('mutation_attempted=false', Artisan::output());
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
                'client_id' => 'dry-run-client',
                'client_secret' => 'dry-run-secret',
                'pcc' => 'NDCS',
            ],
        ]);
    }
}
