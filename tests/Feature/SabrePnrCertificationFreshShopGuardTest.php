<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreSegmentFreshShopSellabilityService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SabrePnrCertificationFreshShopGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
        Config::set([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_per_segment_guard_pass_skips_full_itinerary_fallback(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: true);
        $this->fakePassengerRecordsHttp('C4PASS');

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $guard = is_array($result['fresh_shop_guard_result'] ?? null) ? $result['fresh_shop_guard_result'] : [];
        $this->assertTrue($guard['per_segment_guard_passed'] ?? false);
        $this->assertFalse($guard['full_itinerary_guard_attempted'] ?? true);
        $this->assertNotSame('sabre_passenger_records_stale_shop_segment', $result['error_code'] ?? null);
    }

    public function test_certification_full_itinerary_confirms_same_rbd_allows_passenger_records(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['O', 'Y'], 100000.0));
        $this->fakePassengerRecordsHttp('C4PNROK');

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $guard = is_array($result['fresh_shop_guard_result'] ?? null) ? $result['fresh_shop_guard_result'] : [];
        $this->assertFalse($guard['per_segment_guard_passed'] ?? true);
        $this->assertTrue($guard['full_itinerary_guard_attempted'] ?? false);
        $this->assertTrue($guard['allowed_by_full_itinerary_confirmation'] ?? false);
        $this->assertSame('pending_payment_or_ticketing', $result['status'] ?? null);
        $this->assertSame('C4PNROK', $result['pnr'] ?? null);
        Http::assertSent(fn (Request $req): bool => str_contains((string) $req->url(), 'passenger/records'));
    }

    public function test_certification_blocked_when_full_itinerary_price_changed(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['O', 'Y'], 118000.0));
        Http::fake();

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $this->assertSame('sabre_passenger_records_stale_shop_segment', $result['error_code'] ?? null);
        $guard = is_array($result['fresh_shop_guard_result'] ?? null) ? $result['fresh_shop_guard_result'] : [];
        $this->assertTrue($guard['full_itinerary_guard_attempted'] ?? false);
        $this->assertFalse($guard['allowed_by_full_itinerary_confirmation'] ?? true);
        Http::assertNotSent(fn (Request $req): bool => str_contains((string) $req->url(), 'passenger/records'));
    }

    public function test_certification_blocked_when_full_itinerary_rbd_changed(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['V', 'Y'], 100000.0));
        Http::fake();

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $this->assertSame('sabre_passenger_records_stale_shop_segment', $result['error_code'] ?? null);
        $guard = is_array($result['fresh_shop_guard_result'] ?? null) ? $result['fresh_shop_guard_result'] : [];
        $this->assertStringContainsString('rbd', strtolower((string) ($guard['full_itinerary_guard_reason'] ?? '')));
    }

    public function test_certification_blocked_when_no_full_itinerary_match(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch([
            'id' => 'other',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '999',
                'departure_at' => '2026-05-30T08:00:00',
                'booking_class' => 'Y',
            ]],
            'fare_breakdown' => ['supplier_total' => 100000, 'currency' => 'PKR'],
        ]);
        Http::fake();

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $this->assertSame('sabre_passenger_records_stale_shop_segment', $result['error_code'] ?? null);
    }

    public function test_public_create_booking_without_certification_flag_stays_blocked(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['O', 'Y'], 100000.0));
        Http::fake();

        $result = app(SabreBookingService::class)->createBooking(
            $ctx['offer'],
            $ctx['passenger_data'],
            $ctx['booking']->id,
        );

        $this->assertSame('sabre_certified_route_not_certified', $result['error_code'] ?? null);
        Http::assertNotSent(fn (Request $req): bool => str_contains((string) $req->url(), 'passenger/records'));
    }

    public function test_validate_current_snapshot_trust_requires_same_rbd_and_price(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['V', 'Y'], 100000.0));

        $validation = app(SabreBookingOfferRefreshService::class)
            ->validateCurrentSnapshotAgainstFreshItinerary($ctx['booking']);

        $this->assertTrue($validation['full_itinerary_match']);
        $this->assertFalse($validation['same_rbd']);
        $this->assertFalse($validation['can_trust_for_pnr']);
    }

    public function test_certify_pnr_send_json_includes_fresh_shop_guard_result(): void
    {
        $ctx = $this->connectingCertificationContext();
        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($this->freshConnectingOffer(['O', 'Y'], 100000.0));
        $this->fakePassengerRecordsHttp('C4CLI');

        Artisan::call('sabre:certify-pnr', [
            '--booking' => $ctx['booking']->id,
            '--mode' => 'send',
        ]);
        $payload = $this->decodeCertificationOutput();

        $guard = is_array($payload['fresh_shop_guard_result'] ?? null) ? $payload['fresh_shop_guard_result'] : [];
        $this->assertTrue($guard['allowed_by_full_itinerary_confirmation'] ?? false);
        $this->assertTrue($payload['pnr_created'] ?? false);
    }

    protected function mockSegmentSellability(bool $allPass, string $probableIssue = 'booking_class_mismatch'): void
    {
        $report = [
            'index' => 0,
            'route' => 'LHE-KHI',
            'flight_number' => 'PK303',
            'fresh_flight_found' => true,
            'fresh_same_time_found' => true,
            'fresh_same_rbd_found' => false,
            'probable_issue' => $probableIssue,
        ];

        $partial = Mockery::mock(SabreSegmentFreshShopSellabilityService::class, [
            app(SabreFlightSearchRequestBuilder::class),
            app(SabreClient::class),
            app(SabreFlightSearchNormalizer::class),
        ])->makePartial();
        $partial->shouldReceive('segmentReportsForOffer')->andReturn([$report]);
        $partial->shouldReceive('segmentPassesPnrFreshShopGuard')->andReturn($allPass);
        $this->app->instance(SabreSegmentFreshShopSellabilityService::class, $partial);
    }

    /**
     * @return array{booking: Booking, offer: array<string, mixed>, passenger_data: array<string, mixed>}
     */
    protected function connectingCertificationContext(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = '2026-05-30';
        $segments = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'carrier' => 'PK',
                'flight_number' => '303',
                'departure_at' => $depart.'T11:00:00',
                'arrival_at' => $depart.'T12:30:00',
                'booking_class' => 'O',
                'fare_basis_code' => 'YOWPK7',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '2107',
                'departure_at' => $depart.'T23:55:00',
                'arrival_at' => '2026-05-31T02:10:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YOWPK7',
            ],
        ];
        $offer = [
            'id' => 'c4-connecting-offer',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'validating_carrier' => 'EK',
            'airline_code' => 'PK',
            'segments' => $segments,
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'search_criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'currency' => 'PKR',
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'currency' => 'PKR',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'flight_offer_snapshot' => $offer,
                'search_criteria' => $offer['search_criteria'],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'c4-guard@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return [
            'booking' => $booking,
            'offer' => $offer,
            'passenger_data' => [
                'contact' => ['email' => 'c4-guard@example.com', 'phone' => '+923001234567'],
                'passengers' => [[
                    'passenger_type' => 'adult',
                    'first_name' => 'Test',
                    'last_name' => 'Traveler',
                    'passport_number' => 'AB9999999',
                    'passport_issuing_country' => 'PK',
                    'passport_expiry_date' => '2035-12-31',
                    'nationality' => 'PK',
                ]],
            ],
        ];
    }

    protected function fakePassengerRecordsHttp(string $pnr): void
    {
        Http::fake(function (Request $request) use ($pnr) {
            $url = (string) $request->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => $pnr],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected_test_url'], 500);
        });
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function mockFullItinerarySearch(array $offer): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->andReturn(['offers' => [$offer], 'warnings' => []]);
        $this->app->instance(FlightSearchService::class, $mock);
    }

    /**
     * @param  list<string>  $rbd
     * @return array<string, mixed>
     */
    protected function freshConnectingOffer(array $rbd, float $supplierTotal): array
    {
        $depart = '2026-05-30';

        return [
            'id' => 'fresh-offer',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'departure_at' => $depart.'T11:00:00',
                    'arrival_at' => $depart.'T12:30:00',
                    'booking_class' => $rbd[0],
                    'fare_basis_code' => 'YOWPK7',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '2107',
                    'departure_at' => $depart.'T23:55:00',
                    'arrival_at' => '2026-05-31T02:10:00',
                    'booking_class' => $rbd[1],
                    'fare_basis_code' => 'YOWPK7',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeCertificationOutput(): array
    {
        $output = trim(Artisan::output());
        foreach (preg_split('/\R/', $output) ?: [] as $row) {
            if (str_starts_with($row, 'pnr_certification_json=')) {
                $decoded = json_decode(substr($row, strlen('pnr_certification_json=')), true);
                $this->assertIsArray($decoded);

                return $decoded;
            }
        }

        $this->fail('Missing pnr_certification_json output line');

        return [];
    }
}
