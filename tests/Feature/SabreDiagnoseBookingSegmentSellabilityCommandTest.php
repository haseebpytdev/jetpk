<?php

namespace Tests\Feature;

use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SabreDiagnoseBookingSegmentSellabilityCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function activateSeededSabreConnection(SupplierConnection $conn): void
    {
        $conn->update([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'diag-test', 'client_secret' => 'diag-secret'],
        ]);
        $conn->refresh();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Http::fake();
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_b76_gate_blocks_non_local_environment(): void
    {
        Config::set('app.env', 'staging');

        $exit = Artisan::call('sabre:diagnose-booking-segment-sellability', ['booking_id' => 1]);

        $this->assertSame(1, $exit);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertSame('environment_not_allowed', $decoded['error'] ?? null);
    }

    public function test_b76_exact_match_against_fixture_shop(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $this->activateSeededSabreConnection($conn);

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-test', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'currency' => 'PKR',
            'meta' => [
                'search_criteria' => ['adults' => 1, 'cabin' => 'economy', 'currency' => 'PKR'],
                'validated_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-10T08:30:00',
                        'airline_code' => 'PK',
                        'flight_number' => '203',
                    ]],
                ],
            ],
        ]);

        $exit = Artisan::call('sabre:diagnose-booking-segment-sellability', [
            'booking_id' => $booking->id,
        ]);

        $this->assertSame(0, $exit);
        $out = json_decode(Artisan::output(), true);
        $this->assertSame($booking->id, $out['booking_id']);
        $this->assertSame(1, $out['segment_count']);
        $seg = $out['segments'][0];
        $this->assertTrue($seg['fresh_flight_found']);
        $this->assertTrue($seg['fresh_same_time_found']);
        $this->assertSame('ok', $seg['probable_issue']);
    }

    public function test_b76_flight_not_found_when_marketing_fn_missing(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $this->activateSeededSabreConnection($conn);

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-test', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'validated_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-10T08:30:00',
                        'airline_code' => 'PK',
                        'flight_number' => '999',
                    ]],
                ],
            ],
        ]);

        Artisan::call('sabre:diagnose-booking-segment-sellability', ['booking_id' => $booking->id]);

        $seg = json_decode(Artisan::output(), true)['segments'][0];
        $this->assertFalse($seg['fresh_flight_found']);
        $this->assertFalse($seg['fresh_same_time_found']);
        $this->assertSame('flight_not_in_shop_inventory', $seg['probable_issue']);
    }

    public function test_b76_booking_class_mismatch_when_flight_matches(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $this->activateSeededSabreConnection($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-test', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response([], 200),
        ]);

        $offer = NormalizedFlightOfferData::fromArray([
            'offer_id' => 'sellability-test',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'airline_code' => 'PK',
            'airline_name' => 'PK',
            'flight_number' => '203',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:30:00',
            'arrival_at' => '2026-06-10T11:15:00',
            'duration_minutes' => 165,
            'stops' => 0,
            'cabin' => 'economy',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-10T08:30:00',
                'arrival_at' => '2026-06-10T11:15:00',
                'airline_code' => 'PK',
                'flight_number' => '203',
                'booking_class' => 'U',
            ]],
            'fare_breakdown' => ['supplier_total' => 1, 'currency' => 'PKR', 'base_fare' => 1, 'taxes' => 0],
            'baggage' => ['summary' => 'As per fare rule'],
        ]);

        $normMock = Mockery::mock(SabreFlightSearchNormalizer::class);
        $normMock->shouldReceive('normalize')->once()->andReturn([$offer]);
        $this->swap(SabreFlightSearchNormalizer::class, $normMock);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'validated_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-10T08:30:00',
                        'airline_code' => 'PK',
                        'flight_number' => '203',
                        'booking_class' => 'V',
                    ]],
                ],
            ],
        ]);

        Artisan::call('sabre:diagnose-booking-segment-sellability', ['booking_id' => $booking->id]);

        $seg = json_decode(Artisan::output(), true)['segments'][0];
        $this->assertTrue($seg['fresh_flight_found']);
        $this->assertTrue($seg['fresh_same_time_found']);
        $this->assertFalse($seg['fresh_same_rbd_found']);
        $this->assertSame('booking_class_mismatch', $seg['probable_issue']);
        $this->assertContains('U', $seg['fresh_candidate_rbd_values_sanitized']);
    }

    public function test_b76_output_has_no_tokens_or_raw_authorization_patterns(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $this->activateSeededSabreConnection($conn);

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-test', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'customer_snapshot_email' => 'leak-check-secret-token@invalid.example',
                'validated_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-10T08:30:00',
                        'airline_code' => 'PK',
                        'flight_number' => '203',
                    ]],
                ],
            ],
        ]);

        Artisan::call('sabre:diagnose-booking-segment-sellability', ['booking_id' => $booking->id]);

        $blob = Artisan::output();
        $this->assertStringNotContainsString('leak-check-secret-token', $blob);
        $this->assertStringNotContainsString('Bearer ', $blob);
        $this->assertStringNotContainsString('Authorization', $blob);
        $this->assertStringNotContainsString('PseudoCityCode', $blob);
    }
}
