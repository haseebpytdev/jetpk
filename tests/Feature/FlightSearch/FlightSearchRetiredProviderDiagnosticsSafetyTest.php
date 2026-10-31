<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * JP-FLIGHT-SEARCH-LIVE-INCIDENT-01
 *
 * Production had an active retired `smtp` supplier_connections row. SupplierProviderCast
 * hydrates that provider as a plain string. Diagnostics previously called `$provider->value`
 * and 500'd the entire public `/flights/results/search` path before fan-out.
 */
class FlightSearchRetiredProviderDiagnosticsSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_with_meta_does_not_fatal_when_active_smtp_connection_present(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => 'smtp',
            'name' => 'SMTP JetPakistan LIVE',
            'display_name' => 'SMTP',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => null,
            'credentials' => null,
            'settings' => [],
        ]);

        // Sanity: cast must hydrate retired provider as string (the live failure mode).
        $smtp = SupplierConnection::query()->where('provider', 'smtp')->firstOrFail();
        $this->assertIsString($smtp->provider);
        $this->assertSame('smtp', $smtp->provider);

        Log::spy();

        $result = app(FlightSearchService::class)->searchWithMeta([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'KHI',
            'from' => 'LHE',
            'to' => 'KHI',
            'depart_date' => now()->addDays(20)->toDateString(),
            'depart' => now()->addDays(20)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
        ], $agency, 'public_guest');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('offers', $result);
        $this->assertIsArray($result['offers']);
    }

    public function test_results_search_http_survives_active_smtp_alongside_flight_provider(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => 'smtp',
            'name' => 'SMTP JetPakistan LIVE',
            'display_name' => 'SMTP',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => null,
            'credentials' => null,
            'settings' => [],
        ]);

        // Inactive flight provider is enough to exercise connection mapping; search may return empty.
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel inactive',
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        $depart = now()->addDays(20)->format('Y-m-d');
        $query = http_build_query([
            'trip_type' => 'one_way',
            'from' => 'LHE',
            'to' => 'KHI',
            'depart' => $depart,
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $this->getJson('/flights/results/search?'.$query)
            ->assertOk()
            ->assertJsonStructure(['search_id', 'results_page_url', 'initial_results_url']);
    }
}
