<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Duffel\DuffelOfferNormalizer;
use App\Services\Suppliers\Duffel\DuffelOfferRequestBuilder;
use App\Services\Suppliers\Duffel\DuffelOrderRequestBuilder;
use App\Services\Suppliers\SupplierBookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuffelIntegrationPhase21Test extends TestCase
{
    use RefreshDatabase;

    public function test_duffel_readiness_check_accepts_access_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $admin->current_agency_id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'credentials' => ['access_token' => 'duffel_test_readiness_token'],
        ]);

        $this->actingAs($admin)->patch('/admin/api-settings/'.$connection->id.'/test')->assertRedirect();

        $connection->refresh();
        $this->assertSame('ready_for_review', $connection->last_test_status);
    }

    public function test_duffel_offer_request_builder_supports_one_way_and_round_trip(): void
    {
        $builder = app(DuffelOfferRequestBuilder::class);
        $oneWay = $builder->build(new FlightSearchRequestData(
            origin: ' lhe ',
            destination: ' dxb ',
            departure_date: '2026-06-15',
            adults: 1,
            children: 1,
            infants: 2
        ));
        $roundTrip = $builder->build(new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-06-15',
            return_date: '2026-06-25',
            trip_type: 'round_trip'
        ));

        $this->assertSame('LHE', data_get($oneWay, 'data.slices.0.origin'));
        $this->assertCount(1, (array) data_get($oneWay, 'data.slices'));
        $this->assertCount(1, array_filter((array) data_get($oneWay, 'data.passengers'), fn (array $row): bool => ($row['age'] ?? null) === 1));
        $this->assertCount(2, (array) data_get($roundTrip, 'data.slices'));
    }

    public function test_duffel_normalizer_maps_offer_payload(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_fixture'],
        ]);
        $payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_offer_request_response.json')), true);

        $offers = app(DuffelOfferNormalizer::class)->normalizeMany($payload, $connection);
        $this->assertNotEmpty($offers);
        $offer = $offers[0]->toArray();
        $this->assertSame('duffel', $offer['supplier_provider']);
        $this->assertSame('off_0001', $offer['raw_reference']);
        $this->assertSame('USD', $offer['fare_breakdown']['currency']);
        $this->assertSame(200.0, (float) $offer['fare_breakdown']['base_fare']);
        $this->assertSame(40.0, (float) $offer['fare_breakdown']['taxes']);
        $this->assertSame(245.0, (float) $offer['fare_breakdown']['supplier_total']);
        $this->assertTrue((bool) ($offer['fare_breakdown']['passenger_pricing_available'] ?? false));
        $this->assertSame(4, (int) data_get($offer, 'fare_breakdown.passenger_counts.total', 0));
        $this->assertSame(false, (bool) data_get($offer, 'raw_payload.payment_requirements.requires_instant_payment'));
        $this->assertNotNull(data_get($offer, 'raw_payload.payment_requirements.payment_required_by'));
    }

    public function test_duffel_normalizer_maps_passenger_baggage_into_checked_and_cabin_fields(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_fixture'],
        ]);

        $payload = [
            'data' => [
                'offers' => [[
                    'id' => 'off_bag_1',
                    'base_amount' => '100.00',
                    'tax_amount' => '20.00',
                    'total_amount' => '120.00',
                    'total_currency' => 'USD',
                    'owner' => ['iata_code' => 'PK', 'name' => 'PIA'],
                    'conditions' => ['refund_before_departure' => ['allowed' => true]],
                    'slices' => [[
                        'segments' => [[
                            'origin' => ['iata_code' => 'KHI'],
                            'destination' => ['iata_code' => 'DXB'],
                            'departing_at' => '2026-06-15T06:00:00Z',
                            'arriving_at' => '2026-06-15T09:00:00Z',
                            'marketing_carrier' => ['iata_code' => 'PK', 'name' => 'PIA'],
                            'marketing_carrier_flight_number' => 'PK123',
                            'operating_carrier' => ['iata_code' => 'PK', 'name' => 'PIA'],
                            'cabin_class' => 'economy',
                            'passengers' => [[
                                'baggages' => [
                                    ['quantity' => 1, 'type' => 'checked'],
                                    ['quantity' => 1, 'type' => 'carry_on'],
                                ],
                            ]],
                        ]],
                    ]],
                ]],
            ],
        ];

        $offers = app(DuffelOfferNormalizer::class)->normalizeMany($payload, $connection);
        $this->assertNotEmpty($offers);
        $bag = $offers[0]->toArray()['baggage'] ?? [];
        $this->assertSame('1 bag', (string) ($bag['checked'] ?? ''));
        $this->assertSame('1 bag', (string) ($bag['cabin'] ?? ''));
        $this->assertStringContainsString('Checked baggage: 1 bag', (string) ($bag['summary'] ?? ''));
        $this->assertStringContainsString('Cabin baggage: 1 bag', (string) ($bag['summary'] ?? ''));
    }

    public function test_duffel_adapter_handles_missing_token_and_timeout_safely(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $adapter = app(DuffelFlightSupplierAdapter::class);

        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => [],
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
        ]);
        $missingResult = $adapter->search($searchRequest, $connection);
        $this->assertSame(['Provider search is temporarily unavailable.'], $missingResult->warnings);

        $connection->fresh()->forceFill([
            'credentials' => ['access_token' => 'duffel_test_timeout'],
        ])->save();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $timeoutResult = $adapter->search($searchRequest, $connection->fresh());
        $this->assertNotEmpty($timeoutResult->warnings);
    }

    public function test_duffel_headers_are_sent_and_search_service_includes_duffel(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'name' => 'Duffel Sandbox',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_header_abc', 'api_version' => 'v2'],
        ]);
        Http::fake([
            'https://api.duffel.com/air/offer_requests*' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_offer_request_response.json')), true),
                200
            ),
        ]);

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(5)->toDateString(),
        ], $agency, 'public_guest');

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization')
                && $request->hasHeader('Duffel-Version', 'v2')
                && $request->hasHeader('X-Correlation-ID');
        });
        $this->assertDatabaseHas('supplier_diagnostic_logs', [
            'supplier_connection_id' => $connection->id,
            'action' => 'search',
            'status' => 'success',
        ]);
        $this->assertTrue(collect($offers)->contains(fn (array $offer): bool => ($offer['supplier_provider'] ?? null) === SupplierProvider::Duffel->value));
        $this->assertSame($connection->id, collect($offers)->firstWhere('supplier_provider', 'duffel')['supplier_connection_id'] ?? null);
    }

    public function test_duffel_validate_offer_returns_valid_and_price_changed(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_validate'],
        ]);
        $adapter = app(DuffelFlightSupplierAdapter::class);
        $request = FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(3)->toDateString()]);
        $original = json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_offer_request_response.json')), true);
        $originalOffer = app(DuffelOfferNormalizer::class)->normalizeMany($original, $connection)[0];

        Http::fake([
            'https://api.duffel.com/air/offers/off_0001' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_single_offer_response.json')), true),
                200
            ),
        ]);
        $changed = $adapter->validateOffer($originalOffer, $request, $connection);
        $this->assertSame('price_changed', $changed->status);

        $validPayload = $original['data']['offers'][0];
        $validPayload['type'] = 'offer';
        Http::fake([
            'https://api.duffel.com/air/offers/off_0001' => Http::response([
                'data' => $validPayload,
            ], 200),
        ]);
        $valid = $adapter->validateOffer($changed->validated_offer ?? $originalOffer, $request, $connection);
        $this->assertSame('valid', $valid->status);
    }

    public function test_duffel_order_builder_and_supplier_booking_workflow(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_order_token', 'api_version' => 'v2'],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => [
                    'offer_id' => 'off_0001',
                    'raw_reference' => 'off_0001',
                ],
            ],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 1,
            'title' => 'Mr',
            'first_name' => 'Duffel',
            'last_name' => 'Traveler',
            'date_of_birth' => '1990-01-01',
            'meta' => ['traveler_type' => 'adult'],
        ]);
        $booking->contact()->create([
            'email' => 'duffel@example.com',
            'phone' => '+923001234567',
        ]);

        $payload = app(DuffelOrderRequestBuilder::class)->build($booking);
        $this->assertSame('off_0001', data_get($payload, 'data.selected_offers.0'));
        $this->assertSame('Duffel', data_get($payload, 'data.passengers.0.given_name'));

        Http::fake([
            'https://api.duffel.com/air/orders' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_order_response.json')), true),
                201
            ),
        ]);

        $result = app(SupplierBookingService::class)->createSupplierBooking($booking, $admin);
        $this->assertTrue($result->success);
        $this->assertSame('ord_0001', $result->supplier_reference);

        $this->assertDatabaseHas('supplier_bookings', [
            'booking_id' => $booking->id,
            'provider' => 'duffel',
            'supplier_reference' => 'ord_0001',
        ]);
        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'status' => 'success',
        ]);
        $this->assertNotSame(BookingStatus::Ticketed, $booking->fresh()->status);
    }

    public function test_duffel_token_is_not_persisted_to_logs_or_snapshots(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $token = 'duffel_test_never_leak_me';
        $agencyId = (int) Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id;
        $this->configureDuffelConnection($agencyId, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => $token],
        ]);

        $allAudit = json_encode(AuditLog::query()->pluck('properties')->all());
        $allAttempts = json_encode(SupplierBookingAttempt::query()->pluck('request_payload')->all());
        $allBookings = json_encode(SupplierBooking::query()->pluck('raw_summary')->all());
        $diagnosticMeta = json_encode(SupplierDiagnosticLog::query()->pluck('meta')->all());
        $rawConnection = (string) DB::table('supplier_connections')->where('provider', 'duffel')->value('credentials');

        $this->assertIsString($rawConnection);
        $this->assertStringNotContainsString($token, $rawConnection);
        $this->assertStringNotContainsString($token, (string) $allAudit);
        $this->assertStringNotContainsString($token, (string) $allAttempts);
        $this->assertStringNotContainsString($token, (string) $allBookings);
        $this->assertStringNotContainsString($token, (string) $diagnosticMeta);
    }

    public function test_duffel_failed_search_creates_safe_diagnostic_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', '<>', SupplierProvider::Duffel->value)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_diag_logs'],
        ]);

        $adapter = app(DuffelFlightSupplierAdapter::class);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(4)->toDateString(),
        ]);

        Http::fake(['https://api.duffel.com/air/offer_requests*' => Http::response([], 401)]);
        $adapter->search($request, $connection);
        $this->assertDatabaseHas('supplier_diagnostic_logs', [
            'supplier_connection_id' => $connection->id,
            'action' => 'search',
            'status' => 'failed',
        ]);

    }

    public function test_duffel_order_create_creates_diagnostic_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_create_order_log'],
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => [
                    'offer_id' => 'off_0001',
                    'raw_reference' => 'off_0001',
                ],
            ],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 1,
            'title' => 'Mr',
            'first_name' => 'Duffel',
            'last_name' => 'Traveler',
            'date_of_birth' => '1990-01-01',
            'meta' => ['traveler_type' => 'adult'],
        ]);
        $booking->contact()->create([
            'email' => 'duffel@example.com',
            'phone' => '+923001234567',
        ]);
        Http::fake([
            'https://api.duffel.com/air/orders' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_order_response.json')), true),
                201
            ),
        ]);

        app(SupplierBookingService::class)->createSupplierBooking($booking, $admin);
        $this->assertDatabaseHas('supplier_diagnostic_logs', [
            'supplier_connection_id' => $connection->id,
            'action' => 'create_order',
            'status' => 'success',
        ]);
    }

    public function test_duffel_cli_test_command_hides_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_cli_hidden_token'],
        ]);
        Http::fake([
            'https://api.duffel.com/air/offer_requests*' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/duffel_offer_request_response.json')), true),
                200
            ),
        ]);

        $this->artisan('ota:test-duffel-search', [
            'supplierConnectionId' => (string) $connection->id,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'date' => now()->addDays(8)->toDateString(),
        ])
            ->expectsOutputToContain('provider=duffel')
            ->expectsOutputToContain('offers_count=')
            ->expectsOutputToContain('duration_ms=')
            ->doesntExpectOutputToContain('duffel_test_cli_hidden_token')
            ->assertExitCode(0);
    }

    public function test_results_page_shows_safe_warning_when_duffel_search_fails(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', '<>', SupplierProvider::Duffel->value)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_test_public_warning'],
        ]);
        Http::fake(['https://api.duffel.com/air/offer_requests*' => Http::response([], 500)]);

        $this->get('/flights/results?from=LHE&to=DXB&depart='.now()->addDays(10)->toDateString().'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('Provider search is temporarily unavailable.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function configureDuffelConnection(int $agencyId, array $attributes): SupplierConnection
    {
        $connection = SupplierConnection::query()
            ->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill($attributes)->save();

        return $connection->fresh();
    }
}
