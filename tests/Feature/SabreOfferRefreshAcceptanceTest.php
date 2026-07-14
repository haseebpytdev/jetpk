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
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabrePnrCertificationClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SabreOfferRefreshAcceptanceTest extends TestCase
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

    public function test_apply_with_price_change_sets_acceptance_meta(): void
    {
        $booking = $this->connectingSabreBooking(100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 118000.0));

        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id, '--apply' => true]);
        $booking->refresh();

        $this->assertTrue(data_get($booking->meta, SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION));
        $this->assertFalse(data_get($booking->meta, SabreOfferRefreshAcceptance::META_ACCEPTED));
        $this->assertEqualsWithDelta(100000.0, data_get($booking->meta, SabreOfferRefreshAcceptance::META_OLD_SUPPLIER_TOTAL), 0.01);
        $this->assertEqualsWithDelta(118000.0, data_get($booking->meta, SabreOfferRefreshAcceptance::META_NEW_SUPPLIER_TOTAL), 0.01);
        $this->assertEqualsWithDelta(18000.0, data_get($booking->meta, SabreOfferRefreshAcceptance::META_PRICE_DELTA), 0.01);
        $this->assertSame('PKR', data_get($booking->meta, SabreOfferRefreshAcceptance::META_CURRENCY));
    }

    public function test_accept_command_fails_when_confirmation_not_required(): void
    {
        $booking = $this->connectingSabreBooking(100000.0);

        $exit = Artisan::call('sabre:accept-refreshed-offer', ['--booking' => $booking->id]);
        $payload = $this->decodeAcceptOutput();

        $this->assertSame(1, $exit);
        $this->assertSame('confirmation_not_required', $payload['error']);
    }

    public function test_accept_command_marks_accepted(): void
    {
        $booking = $this->connectingSabreBooking(100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 118000.0));
        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id, '--apply' => true]);

        $exit = Artisan::call('sabre:accept-refreshed-offer', ['--booking' => $booking->id]);
        $payload = $this->decodeAcceptOutput();
        $booking->refresh();

        $this->assertSame(0, $exit);
        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['requires_pricing_update']);
        $this->assertTrue(data_get($booking->meta, SabreOfferRefreshAcceptance::META_ACCEPTED));
        $this->assertNotNull(data_get($booking->meta, SabreOfferRefreshAcceptance::META_ACCEPTED_AT));
        $this->assertSame('cli', data_get($booking->meta, SabreOfferRefreshAcceptance::META_ACCEPTED_BY));
    }

    public function test_certify_blocks_when_price_changed_and_not_accepted(): void
    {
        Http::fake();
        $ctx = $this->connectingCertificationContext(118000.0);
        $meta = is_array($ctx['booking']->meta) ? $ctx['booking']->meta : [];
        $meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] = true;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = false;
        $meta[SabreOfferRefreshAcceptance::META_NEW_SUPPLIER_TOTAL] = 118000.0;
        $ctx['booking']->forceFill(['meta' => $meta])->save();

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $ctx['booking']->id, '--mode' => 'send']);
        $payload = $this->decodeCertifyOutput();

        $this->assertSame(1, $exit);
        $this->assertSame(SabrePnrCertificationClassifier::UPDATED_FARE_REQUIRES_ACCEPTANCE, $payload['classification']);
        $this->assertFalse($payload['pnr_created']);
        Http::assertNothingSent();
    }

    public function test_certification_proceeds_after_acceptance_when_full_itinerary_confirms(): void
    {
        $ctx = $this->connectingCertificationContext(100000.0);
        $refreshedOffer = $this->freshConnectingOffer(['O', 'Y'], 118000.0);
        $refreshedOffer['supplier_connection_id'] = $ctx['offer']['supplier_connection_id'];
        $refreshedOffer['search_criteria'] = $ctx['offer']['search_criteria'];
        $meta = is_array($ctx['booking']->meta) ? $ctx['booking']->meta : [];
        SabreOfferRefreshAcceptance::writePriceChangeMeta($meta, 100000.0, 118000.0, 'PKR');
        $meta['flight_offer_snapshot'] = $refreshedOffer;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = true;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_AT] = now()->toIso8601String();
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_BY] = 'cli';
        $ctx['booking']->forceFill(['meta' => $meta])->save();

        $this->mockSegmentSellability(allPass: false, probableIssue: 'booking_class_mismatch');
        $this->mockFullItinerarySearch($refreshedOffer);
        $this->fakePassengerRecordsHttp('P3ACPT');

        $result = app(SabreBookingService::class)->createBooking(
            array_merge($ctx['offer'], $refreshedOffer),
            $ctx['passenger_data'],
            $ctx['booking']->id,
            ['certification_full_itinerary_fallback' => true],
        );

        $guard = is_array($result['fresh_shop_guard_result'] ?? null) ? $result['fresh_shop_guard_result'] : [];
        $this->assertTrue($guard['allowed_by_full_itinerary_confirmation'] ?? false);
        $this->assertSame('pending_payment_or_ticketing', $result['status'] ?? null);
    }

    public function test_apply_validate_uses_refreshed_snapshot_over_stale_normalized_alias(): void
    {
        $booking = $this->connectingSabreBooking(100000.0);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['normalized_offer_snapshot'] = $this->freshConnectingOffer(['O', 'Y'], 100000.0);
        $booking->forceFill(['meta' => $meta])->save();

        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 118000.0));
        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id, '--apply' => true]);
        $booking->refresh();

        $validation = app(SabreBookingOfferRefreshService::class)
            ->validateCurrentSnapshotAgainstFreshItinerary($booking);

        $this->assertFalse($validation['price_changed']);
        $this->assertTrue($validation['can_trust_for_pnr']);
    }

    public function test_accept_output_contains_no_sensitive_fields(): void
    {
        $booking = $this->connectingSabreBooking(100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(['O', 'Y'], 118000.0));
        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id, '--apply' => true]);

        Artisan::call('sabre:accept-refreshed-offer', ['--booking' => $booking->id]);
        $out = Artisan::output();

        $this->assertStringNotContainsString('request_payload', strtolower($out));
        $this->assertStringNotContainsString('passport', strtolower($out));
    }

    /**
     * @return array{booking: Booking, offer: array<string, mixed>, passenger_data: array<string, mixed>}
     */
    protected function connectingCertificationContext(float $supplierTotal): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $sabreConn = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);
        $offer = $this->freshConnectingOffer(['O', 'Y'], $supplierTotal);
        $offer['supplier_connection_id'] = $sabreConn->id;
        $offer['search_criteria'] = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
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
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'p3@test.example',
            'phone' => '+923001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => $supplierTotal * 0.8,
            'taxes' => $supplierTotal * 0.1,
            'fees' => 0,
            'markup' => $supplierTotal * 0.1,
            'discount' => 0,
            'total' => $supplierTotal,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return [
            'booking' => $booking,
            'offer' => $offer,
            'passenger_data' => [
                'contact' => ['email' => 'p3@test.example', 'phone' => '+923001234567'],
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

    protected function connectingSabreBooking(float $existingSupplierTotal): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        return Booking::factory()->for($agency)->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
                'flight_offer_snapshot' => $this->freshConnectingOffer(['O', 'Y'], $existingSupplierTotal),
            ],
        ]);
    }

    /**
     * @param  list<string>  $rbd
     * @return array<string, mixed>
     */
    protected function freshConnectingOffer(array $rbd, float $supplierTotal): array
    {
        $depart = '2026-05-30';

        return [
            'id' => 'p3-fresh-offer',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'total' => $supplierTotal,
            'currency' => 'PKR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'departure_at' => $depart.'T11:00:00',
                    'arrival_at' => $depart.'T12:30:00',
                    'booking_class' => $rbd[0] ?? 'O',
                    'fare_basis_code' => 'YOWPK7',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '2107',
                    'departure_at' => $depart.'T23:55:00',
                    'arrival_at' => '2026-05-31T02:10:00',
                    'booking_class' => $rbd[1] ?? 'Y',
                    'fare_basis_code' => 'YOWPK7',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    protected function mockSearchReturning(array $offer): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => [$offer],
            'meta' => [],
        ]);
        $this->app->instance(FlightSearchService::class, $mock);
    }

    protected function mockSegmentSellability(bool $allPass, string $probableIssue = 'booking_class_mismatch'): void
    {
        $report = [
            'index' => 0,
            'route' => 'LHE-KHI',
            'flight_number' => 'PK303',
            'fresh_flight_found' => true,
            'fresh_same_time_found' => true,
            'fresh_same_rbd_found' => $allPass,
            'probable_issue' => $allPass ? 'ok' : $probableIssue,
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

    protected function mockFullItinerarySearch(array $offer): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => [$offer], 'meta' => []]);
        $this->app->instance(FlightSearchService::class, $mock);
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

            return Http::response(['error' => 'unexpected'], 500);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeAcceptOutput(): array
    {
        return $this->decodeLinePrefix('accept_refreshed_offer_json=');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeCertifyOutput(): array
    {
        return $this->decodeLinePrefix('pnr_certification_json=');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeLinePrefix(string $prefix): array
    {
        foreach (preg_split('/\R/', trim(Artisan::output())) ?: [] as $row) {
            if (str_starts_with($row, $prefix)) {
                $decoded = json_decode(substr($row, strlen($prefix)), true);
                $this->assertIsArray($decoded);

                return $decoded;
            }
        }

        $this->fail('Expected output prefix '.$prefix);

        return [];
    }
}
