<?php

namespace Tests\Feature;

use App\Enums\MarkupRuleStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Airport;
use App\Models\Booking;
use App\Models\MarkupRule;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Pricing\PricingRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase21HLegacyDataAndDuffelPrepTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_command_maps_fixed_pkr_safely(): void
    {
        $rule = MarkupRule::factory()->create([
            'rule_type' => 'global',
            'value_type' => 'fixed',
        ]);
        DB::table('markup_rules')->where('id', $rule->id)->update([
            'rule_type' => 'fixed_pkr',
            'value_type' => 'fixed',
        ]);

        $this->artisan('ota:repair-legacy-data')->assertExitCode(0);

        $this->assertDatabaseHas('markup_rules', [
            'id' => $rule->id,
            'rule_type' => 'global',
            'value_type' => 'fixed',
        ]);
    }

    public function test_pricing_rule_service_skips_invalid_legacy_rule_instead_of_crashing(): void
    {
        $agency = Agency::factory()->create();
        $rule = MarkupRule::factory()->create([
            'agency_id' => $agency->id,
            'rule_type' => 'global',
            'status' => MarkupRuleStatus::Active,
            'value_type' => 'fixed',
            'value' => 1000,
        ]);
        DB::table('markup_rules')->where('id', $rule->id)->update(['rule_type' => 'fixed_pkr']);

        $service = app(PricingRuleService::class);
        $result = $service->calculateMarkup($agency, [
            'base_fare' => 10000,
            'taxes' => 2000,
            'currency' => 'PKR',
        ], [
            'route' => 'LHE-DXB',
            'airline' => 'ek',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('final_total', $result);
    }

    public function test_operational_reset_command_clears_bookings_but_keeps_airports(): void
    {
        Booking::factory()->create();
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'priority_score' => 250,
            'is_active' => true,
        ]);

        $this->artisan('ota:reset-operational-data', ['--force' => true])->assertExitCode(0);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseHas('airports', ['iata_code' => 'LHE']);
    }

    public function test_operational_reset_keeps_agencies_and_users(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        Booking::factory()->create(['agency_id' => $agency->id, 'customer_id' => $user->id]);

        $this->artisan('ota:reset-operational-data', ['--force' => true])->assertExitCode(0);

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_prepare_duffel_test_command_completes_for_agency(): void
    {
        $agency = Agency::factory()->create(['slug' => 'asif-travels', 'name' => 'Asif Travels']);
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);

        $this->artisan('ota:prepare-duffel-test', ['--agency' => 'asif-travels'])->assertExitCode(0);
    }

    public function test_flights_results_does_not_500_with_legacy_markup_data(): void
    {
        $agency = Agency::factory()->create(['slug' => (string) config('ota.default_agency_slug', 'asif-travels')]);
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);
        $rule = MarkupRule::factory()->create([
            'agency_id' => $agency->id,
            'rule_type' => 'global',
            'status' => MarkupRuleStatus::Active,
        ]);
        DB::table('markup_rules')->where('id', $rule->id)->update(['rule_type' => 'fixed_pkr']);

        $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
    }

    public function test_flights_results_shows_clean_no_fares_message_when_no_active_supplier_returns_offers(): void
    {
        Agency::factory()->create(['slug' => (string) config('ota.default_agency_slug', 'asif-travels')]);

        $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('No fares found for this route/date. Try a different date or contact support.');
    }
}
