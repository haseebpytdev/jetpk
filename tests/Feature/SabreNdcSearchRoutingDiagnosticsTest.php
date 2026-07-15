<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SabreNdcSearchRoutingDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.search_enabled', false);
        Http::fake();
    }

    public function test_provider_selection_includes_ndc_excludes_gds_for_ndc_only_connection(): void
    {
        Log::spy();

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => [
                'sabre_gds_enabled' => false,
                'sabre_ndc_enabled' => true,
            ],
            'credentials' => [
                'client_id' => 'routing-client',
                'client_secret' => 'routing-secret',
                'pcc' => 'NDCS',
            ],
        ]);

        app(FlightSearchService::class)->searchWithMeta([
            'search_id' => 'provider-selection-test',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-16',
            'adults' => 1,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
        ]);

        Log::shouldHaveReceived('info')
            ->with('supplier.search.provider_selection', \Mockery::on(function (array $context): bool {
                return ($context['search_id'] ?? '') === 'provider-selection-test'
                    && ($context['sabre_ndc_provider_included'] ?? false) === true
                    && ($context['sabre_gds_provider_included'] ?? false) === false
                    && ($context['sabre_gds_excluded_reason'] ?? '') === 'connection_gds_channel_disabled';
            }))
            ->once();
    }
}
