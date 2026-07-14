<?php

namespace Tests\Unit\GroupTicketing;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use App\Services\TravelData\AirlineBrandingService;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupInventoryCardPresenterTest extends TestCase
{
    use RefreshDatabase;

    private GroupInventoryCardPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $restriction = $this->createMock(GroupBookingRestrictionService::class);
        $restriction->method('isBlocked')->willReturn(false);
        $this->presenter = new GroupInventoryCardPresenter(new AirlineBrandingService, $restriction);
    }

    public function test_presents_readable_route_from_airport_lookup(): void
    {
        Airport::query()->create([
            'iata_code' => 'SKT',
            'name' => 'Sialkot International Airport',
            'city' => 'Sialkot',
            'country' => 'Pakistan',
            'is_active' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'SHJ',
            'name' => 'Sharjah International Airport',
            'city' => 'Sharjah',
            'country' => 'United Arab Emirates',
            'is_active' => true,
        ]);

        $inventory = $this->makeInventory([
            'sector' => 'SKT-SHJ',
            'baggage' => '20+10',
            'airline_name' => 'AIR ARABIA',
            'departure_date' => '2026-06-21',
            'total_seats' => 5,
            'held_seats' => 3,
        ]);

        $card = $this->presenter->present($inventory);

        $this->assertSame('Sialkot (SKT) → Sharjah (SHJ)', $card['route_line']);
        $this->assertSame('SKT-SHJ', $card['sector_raw']);
        $this->assertSame('G9', $card['airline_code']);
        $this->assertSame('Checked: 20kg · Cabin: 10kg', $card['baggage']['display']);
        $this->assertSame('Baggage: Checked 20kg · Cabin 10kg', $card['baggage_line']);
        $this->assertSame('Sector: SKT-SHJ', $card['sector_line']);
        $this->assertSame('21 Jun 2026', $card['departure_date_short']);
        $this->assertSame('2 seats left', $card['seat_label']);
        $this->assertSame('PKR', $card['currency']);
        $this->assertSame('99,000', $card['price_formatted']);
        $this->assertSame('unspecified', $card['meal_status']);
    }

    public function test_presents_meal_and_flight_times_from_snapshot(): void
    {
        $inventory = $this->makeInventory([
            'supplier_package_id' => 'meal-time-1',
            'departure_date' => '2026-06-21',
            'snapshot' => [
                'meal' => 'Included',
                'legs' => [[
                    'type' => 'outbound',
                    'departure_time' => '14:30',
                    'arrival_time' => '18:05',
                ]],
            ],
        ]);

        $card = $this->presenter->present($inventory);

        $this->assertSame('included', $card['meal_status']);
        $this->assertSame('Meal included', $card['meal_label']);
        $this->assertSame('21 Jun 2026 · 14:30', $card['departure_datetime_display']);
        $this->assertSame('18:05', $card['arrival_time_display']);
    }

    public function test_meal_not_included_and_no_times_when_snapshot_empty(): void
    {
        $inventory = $this->makeInventory([
            'supplier_package_id' => 'meal-time-2',
            'snapshot' => ['meal' => 'Not included'],
        ]);

        $card = $this->presenter->present($inventory);

        $this->assertSame('excluded', $card['meal_status']);
        $this->assertSame('21 Jun 2026', $card['departure_datetime_display']);
        $this->assertNull($card['arrival_time_display']);
    }

    public function test_falls_back_to_iata_codes_when_airports_missing(): void
    {
        $inventory = $this->makeInventory(['sector' => 'ABC-XYZ']);

        $card = $this->presenter->present($inventory);

        $this->assertSame('ABC → XYZ', $card['route_line']);
    }

    public function test_resolves_airline_code_from_airline_id(): void
    {
        $airline = Airline::query()->create([
            'iata_code' => 'XY',
            'name' => 'Flynas',
            'is_active' => true,
        ]);

        $inventory = $this->makeInventory([
            'airline_id' => $airline->id,
            'airline_name' => 'Flynas',
        ]);

        $card = $this->presenter->present($inventory);

        $this->assertSame('XY', $card['airline_code']);
    }

    public function test_build_checkout_summary_normalizes_sidebar_fields(): void
    {
        $inventory = $this->makeInventory([
            'sector' => 'SKT-SHJ',
            'baggage' => '20+10',
            'price' => 99000,
        ]);

        $card = $this->presenter->present($inventory);
        $summary = $this->presenter->buildCheckoutSummary($card, 2, 198000.0);

        $this->assertSame('Group Ticketing', $summary['product_type']);
        $this->assertSame(2, $summary['seat_count']);
        $this->assertSame(2, $summary['seats_selected']);
        $this->assertSame(10, $summary['available_seats']);
        $this->assertSame('198,000', $summary['total_formatted']);
        $this->assertSame('99,000', $summary['price_per_adult_formatted']);
        $this->assertSame('Checked: 20kg · Cabin: 10kg', $summary['baggage_display']);
    }

    public function test_format_seat_label_variants(): void
    {
        $one = $this->presenter->present($this->makeInventory(['supplier_package_id' => 'badge-1', 'total_seats' => 1]));
        $this->assertSame('1 seat left', $one['seat_label']);

        $three = $this->presenter->present($this->makeInventory(['supplier_package_id' => 'badge-3', 'total_seats' => 3, 'held_seats' => 0]));
        $this->assertSame('3 seats left', $three['seat_label']);

        $five = $this->presenter->present($this->makeInventory(['supplier_package_id' => 'badge-5', 'total_seats' => 5]));
        $this->assertSame('5 seats left', $five['seat_label']);
    }

    public function test_guest_cta_points_to_login_with_group_passengers_redirect(): void
    {
        $inventory = $this->makeInventory();

        $card = $this->presenter->present($inventory);

        $this->assertSame('Book now', $card['cta_label']);
        $this->assertStringContainsString('/login', $card['cta_url']);
        $this->assertStringContainsString(urlencode('/groups/'.$inventory->id.'/passengers'), $card['cta_url']);
    }

    public function test_isb_label_prefers_override_over_attock_db_city(): void
    {
        Airport::query()->create([
            'iata_code' => 'ISB',
            'name' => 'Islamabad International Airport',
            'city' => 'Attock',
            'country' => 'Pakistan',
            'is_active' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'DMM',
            'name' => 'King Fahd International Airport',
            'city' => 'Dammam',
            'country' => 'Saudi Arabia',
            'is_active' => true,
        ]);

        $card = $this->presenter->present($this->makeInventory([
            'supplier_package_id' => 'isb-dmm',
            'sector' => 'ISB-DMM',
        ]));

        $this->assertSame('Islamabad (ISB)', $card['origin_label']);
        $this->assertSame('Dammam (DMM)', $card['dest_label']);
        $this->assertSame('Islamabad (ISB) → Dammam (DMM)', $card['route_line']);
    }

    public function test_active_group_sector_labels_use_verified_airport_mapping(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'is_active' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'DMM',
            'name' => 'King Fahd International Airport',
            'city' => 'Dammam',
            'country' => 'Saudi Arabia',
            'is_active' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'PEW',
            'name' => 'Bacha Khan International Airport',
            'city' => 'Peshawar',
            'country' => 'Pakistan',
            'is_active' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'MCT',
            'name' => 'Muscat International Airport',
            'city' => 'Muscat',
            'country' => 'Oman',
            'is_active' => true,
        ]);

        $lheDmm = $this->presenter->present($this->makeInventory([
            'supplier_package_id' => 'lhe-dmm',
            'sector' => 'LHE-DMM',
        ]));
        $this->assertSame('Lahore (LHE)', $lheDmm['origin_label']);
        $this->assertSame('Dammam (DMM)', $lheDmm['dest_label']);

        $pewMct = $this->presenter->present($this->makeInventory([
            'supplier_package_id' => 'pew-mct',
            'sector' => 'PEW-MCT',
        ]));
        $this->assertSame('Peshawar (PEW)', $pewMct['origin_label']);
        $this->assertSame('Muscat (MCT)', $pewMct['dest_label']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInventory(array $overrides = []): GroupInventory
    {
        return GroupInventory::query()->create(array_merge([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'presenter-1',
            'public_id' => 'ALH-PRES-1',
            'title' => 'UAE — SKT-SHJ',
            'sector' => 'LHE-MCT',
            'airline_name' => 'Test Air',
            'departure_date' => '2026-06-21',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
        ], $overrides));
    }
}
