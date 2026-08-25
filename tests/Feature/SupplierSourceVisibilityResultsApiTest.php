<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use App\Support\Suppliers\SupplierSourceVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSourceVisibilityResultsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_results_omit_supplier_source_label(): void
    {
        $searchId = $this->storeOfferSearch();

        $offer = $this->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertIsArray($offer);
        $this->assertArrayNotHasKey('supplier_source_label', $offer);
        $this->assertSame('sabre', $offer['supplier_provider'] ?? $offer['provider'] ?? null);
    }

    public function test_customer_results_omit_supplier_source_label(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
        ]);
        $searchId = $this->storeOfferSearch();

        $offer = $this->actingAs($customer)
            ->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertArrayNotHasKey('supplier_source_label', $offer);
    }

    public function test_agent_results_include_safe_supplier_source_label(): void
    {
        $agent = User::factory()->create([
            'account_type' => AccountType::Agent,
        ]);
        $searchId = $this->storeOfferSearch();

        $offer = $this->actingAs($agent)
            ->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertNotEmpty($offer['supplier_source_label'] ?? null);
        $this->assertArrayNotHasKey('supplier_connection_id', $offer);
    }

    public function test_admin_results_include_safe_supplier_source_label(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);
        $searchId = $this->storeOfferSearch();

        $offer = $this->actingAs($admin)
            ->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertNotEmpty($offer['supplier_source_label'] ?? null);
    }

    public function test_sanitize_helper_strips_label_for_guests_and_keeps_provider(): void
    {
        $this->assertFalse(SupplierSourceVisibility::canCurrentUser());

        $row = PublicFlightSearchSecurity::applySupplierSourceVisibility([
            'supplier_provider' => 'sabre',
            'supplier_source_label' => 'Sabre',
            'supplier_connection_id' => 99,
        ]);

        $this->assertSame('sabre', $row['supplier_provider']);
        $this->assertArrayNotHasKey('supplier_source_label', $row);
        $this->assertSame(99, $row['supplier_connection_id']);
    }

    private function storeOfferSearch(): string
    {
        $departDay = now()->addDays(21)->format('Y-m-d');

        return app(FlightSearchResultStore::class)->store(
            [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_date' => $departDay,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => 'economy',
            ],
            [[
                'id' => 'offer-sabre-1',
                'offer_id' => 'offer-sabre-1',
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 42,
                'airline_code' => 'PK',
                'airline_name' => 'Pakistan International',
                'final_customer_price' => 85000,
                'base_fare' => 70000,
                'taxes' => 15000,
                'markup' => 0,
                'service_fee' => 0,
                'currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'supplier_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'refundable' => false,
                'stops' => 0,
                'cabin' => 'economy',
                'depart_at' => $departDay.'T08:00:00Z',
                'arrive_at' => $departDay.'T11:00:00Z',
                'duration_h' => 3,
                'duration_m' => 0,
                'segments' => [[
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $departDay.'T08:00:00Z',
                    'arrival_at' => $departDay.'T11:00:00Z',
                    'airline_code' => 'PK',
                    'flight_number' => '301',
                ]],
            ]],
            []
        );
    }
}
