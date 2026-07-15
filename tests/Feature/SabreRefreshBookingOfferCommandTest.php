<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SabreRefreshBookingOfferCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dry_run_finds_fresh_offer_with_changed_rbd(): void
    {
        $booking = $this->connectingSabreBooking();
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 118000.0));

        $exit = Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertTrue($payload['match_found']);
        $this->assertSame('high', $payload['match_confidence']);
        $this->assertSame(['O', 'Y'], $payload['existing_rbd_list']);
        $this->assertSame(['V', 'Y'], $payload['fresh_rbd_list']);
        $this->assertTrue($payload['can_apply']);
        $booking->refresh();
        $this->assertSame('O', data_get($booking->meta, 'flight_offer_snapshot.segments.0.booking_class'));
    }

    public function test_dry_run_reports_price_delta(): void
    {
        $booking = $this->connectingSabreBooking(existingSupplierTotal: 100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 118000.0));

        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertTrue($payload['price_changed']);
        $this->assertEqualsWithDelta(18000.0, $payload['price_delta'], 0.001);
        $this->assertSame('PKR', $payload['currency']);
    }

    public function test_dry_run_does_not_write_booking_meta(): void
    {
        $booking = $this->connectingSabreBooking();
        $originalMeta = $booking->meta;
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 118000.0));

        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id]);
        $booking->refresh();

        $this->assertSame($originalMeta, $booking->meta);
        $this->assertNull(data_get($booking->meta, 'flight_offer_snapshot_refreshed_at'));
    }

    public function test_apply_updates_snapshot_rbd_and_summary(): void
    {
        $booking = $this->connectingSabreBooking();
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 100000.0));

        $exit = Artisan::call('sabre:refresh-booking-offer', [
            '--booking' => $booking->id,
            '--apply' => true,
        ]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertTrue($payload['applied']);
        $booking->refresh();
        $this->assertSame('V', data_get($booking->meta, 'flight_offer_snapshot.segments.0.booking_class'));
        $this->assertSame('Y', data_get($booking->meta, 'flight_offer_snapshot.segments.1.booking_class'));
        $this->assertSame('refreshed', data_get($booking->meta, 'offer_refresh_status'));
        $this->assertSame(['O', 'Y'], data_get($booking->meta, 'previous_offer_snapshot_summary.rbd_list'));
        $this->assertNotNull(data_get($booking->meta, 'flight_offer_snapshot_refreshed_at'));
    }

    public function test_apply_flags_price_confirmation_when_total_changes(): void
    {
        $booking = $this->connectingSabreBooking(existingSupplierTotal: 100000.0);
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 118000.0));

        Artisan::call('sabre:refresh-booking-offer', [
            '--booking' => $booking->id,
            '--apply' => true,
        ]);
        $booking->refresh();

        $this->assertTrue(data_get($booking->meta, 'offer_refresh_price_changed'));
        $this->assertTrue(data_get($booking->meta, 'offer_refresh_requires_customer_confirmation'));
        $this->assertFalse(data_get($booking->meta, 'offer_refresh_accepted'));
        $this->assertEqualsWithDelta(100000.0, data_get($booking->meta, 'offer_refresh_old_supplier_total'), 0.01);
        $this->assertEqualsWithDelta(118000.0, data_get($booking->meta, 'offer_refresh_new_supplier_total'), 0.01);
        $this->assertSame('rbd_or_price_changed', data_get($booking->meta, 'offer_refresh_reason'));
    }

    public function test_no_match_does_not_write(): void
    {
        $booking = $this->connectingSabreBooking();
        $this->mockSearchReturning([
            'id' => 'other-offer',
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
            'fare_breakdown' => ['supplier_total' => 90000, 'currency' => 'PKR'],
        ]);

        Artisan::call('sabre:refresh-booking-offer', [
            '--booking' => $booking->id,
            '--apply' => true,
        ]);
        $booking->refresh();

        $this->assertFalse($this->decodeOutput()['match_found']);
        $this->assertSame('O', data_get($booking->meta, 'flight_offer_snapshot.segments.0.booking_class'));
        $this->assertNull(data_get($booking->meta, 'offer_refresh_status'));
    }

    public function test_output_contains_no_sensitive_fields(): void
    {
        $booking = $this->connectingSabreBooking();
        $this->mockSearchReturning($this->freshConnectingOffer(rbd: ['V', 'Y'], supplierTotal: 100000.0));

        Artisan::call('sabre:refresh-booking-offer', ['--booking' => $booking->id]);
        $line = trim(Artisan::output());
        $json = substr($line, strlen('refresh_offer_json='));

        $this->assertStringNotContainsString('passport', strtolower($json));
        $this->assertStringNotContainsString('client_secret', strtolower($json));
        $this->assertStringNotContainsString('AB9999999', $json);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeOutput(): array
    {
        $line = trim(Artisan::output());
        $this->assertStringStartsWith('refresh_offer_json=', $line);
        $json = substr($line, strlen('refresh_offer_json='));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    protected function connectingSabreBooking(?float $existingSupplierTotal = null): Booking
    {
        $agency = Agency::factory()->create();
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
        $total = $existingSupplierTotal ?? 100000.0;

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'currency' => 'PKR',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
                'flight_offer_snapshot' => [
                    'id' => 'booking-21-style',
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'EK',
                    'segments' => $segments,
                    'fare_breakdown' => [
                        'supplier_total' => $total,
                        'currency' => 'PKR',
                    ],
                ],
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
     * @param  array<string, mixed>  $offer
     */
    protected function mockSearchReturning(array $offer): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->once()
            ->andReturn(['offers' => [$offer], 'warnings' => []]);
        $this->app->instance(FlightSearchService::class, $mock);
    }
}
