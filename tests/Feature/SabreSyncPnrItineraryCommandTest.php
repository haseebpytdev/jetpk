<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreSyncPnrItineraryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_dry_run_does_not_write_meta(): void
    {
        $booking = $this->bookingWithSabrePnr('UNGKWK');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->cleanFlightsJson(), 200),
        ]);
        Cache::flush();

        $exit = Artisan::call('sabre:sync-pnr-itinerary', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"dry_run":true', $out);
        $this->assertStringContainsString('"would_write_pnr_itinerary_snapshot":true', $out);
        $booking->refresh();
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_sync'));
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_sync_writes_snapshot_and_sync_sidecar_for_hk_segment(): void
    {
        $booking = $this->bookingWithSabrePnr('UNGKWK');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->cleanFlightsJson(), 200),
        ]);
        Cache::flush();

        $exit = Artisan::call('sabre:sync-pnr-itinerary', [
            '--booking' => (string) $booking->id,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"synced":true', $out);
        $booking->refresh();
        $snap = data_get($booking->meta, 'pnr_itinerary_snapshot');
        $this->assertIsArray($snap);
        $this->assertSame('sabre_trip_orders_get_booking', $snap['source']);
        $this->assertSame('LHE', $snap['origin']);
        $this->assertSame('KHI', $snap['destination']);
        $this->assertSame('PK', $snap['segments'][0]['airline_code']);
        $this->assertSame('303', $snap['segments'][0]['flight_number']);
        $this->assertSame('HK', $snap['segments'][0]['segment_status']);
        $this->assertSame('synced', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $syncSidecar = data_get($booking->meta, 'pnr_itinerary_sync');
        $this->assertIsArray($syncSidecar);
        $this->assertTrue($syncSidecar['is_cancelable']);
        $this->assertFalse($syncSidecar['is_ticketed']);
        $this->assertFalse($syncSidecar['ticket_numbers_present']);
        $this->assertTrue($syncSidecar['booking_id_present']);
        $this->assertArrayHasKey('airline_locator_present', $syncSidecar);
        $this->assertFalse($syncSidecar['airline_locator_present']);
        $this->assertArrayNotHasKey('travelers', $snap);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame('pnr_retrieve', $attempt->action);
        $this->assertSame('success', $attempt->status);
        $this->assertNull($attempt->request_payload);
        $this->assertNull($attempt->response_payload);
    }

    public function test_resource_unavailable_with_mappable_segment_persists_partial_sidecar(): void
    {
        $booking = $this->bookingWithSabrePnr('IJYJMV');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response(array_merge($this->cleanFlightsJson(), [
                'errors' => [['code' => 'RESOURCE_UNAVAILABLE', 'title' => 'Unavailable']],
            ]), 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $syncSidecar = data_get($booking->meta, 'pnr_itinerary_sync');
        $this->assertIsArray($syncSidecar);
        $this->assertSame('partial_resource_unavailable', $syncSidecar['status']);
        $this->assertSame('partial_resource_unavailable', $syncSidecar['reason_code']);
        $this->assertTrue($syncSidecar['resource_unavailable_present']);
        $this->assertSame(1, $syncSidecar['segment_count']);
        $this->assertSame(1, $syncSidecar['mappable_segment_count']);
        $this->assertTrue($syncSidecar['is_cancelable']);
        $this->assertFalse($syncSidecar['is_ticketed']);
    }

    public function test_resource_unavailable_with_airline_locator_persists_partial_sidecar(): void
    {
        $booking = $this->bookingWithSabrePnr('PPNYYM');
        $json = array_merge($this->cleanFlightsJson(), [
            'errors' => [['code' => 'RESOURCE_UNAVAILABLE', 'title' => 'Unavailable']],
        ]);
        $json['flights'][0]['confirmationId'] = 'RQATZN';
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($json, 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $syncSidecar = data_get($booking->meta, 'pnr_itinerary_sync');
        $this->assertIsArray($syncSidecar);
        $this->assertSame('partial_resource_unavailable', $syncSidecar['status']);
        $this->assertTrue($syncSidecar['airline_locator_present']);
        $this->assertSame('flights.0.confirmationId', $syncSidecar['airline_locator_path']);
        $this->assertSame('RQATZN', $syncSidecar['airline_locator_value']);
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
    }

    public function test_resource_unavailable_without_locator_or_segment_stays_blocked(): void
    {
        $booking = $this->bookingWithSabrePnr('NOSEG1');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'blocked-only-booking-id',
                'errors' => [['code' => 'RESOURCE_UNAVAILABLE', 'title' => 'Unavailable']],
            ], 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame('blocked_resource_unavailable', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertFalse((bool) data_get($booking->meta, 'pnr_itinerary_sync.airline_locator_present'));
        $this->assertSame(0, data_get($booking->meta, 'pnr_itinerary_sync.segment_count'));
    }

    public function test_hx_segment_status_does_not_write_snapshot(): void
    {
        $booking = $this->bookingWithSabrePnr('IJYJMV');
        $json = $this->cleanFlightsJson();
        $json['flights'][0]['flightStatusCode'] = 'HX';
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($json, 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame('blocked_segment_status', data_get($booking->meta, 'pnr_itinerary_sync.status'));
    }

    public function test_failed_sync_does_not_overwrite_existing_good_snapshot(): void
    {
        $booking = $this->bookingWithSabrePnr('UNGKWK');
        $existing = [
            'source' => 'sabre_trip_orders_get_booking',
            'origin' => 'LHE',
            'destination' => 'KHI',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-06-01T08:00:00',
                    'arrival_at' => '2026-06-01T10:00:00',
                    'airline_code' => 'PK',
                    'flight_number' => '100',
                ],
            ],
        ];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_snapshot'] = $existing;
        $booking->meta = $meta;
        $booking->save();

        $json = $this->cleanFlightsJson();
        $json['flights'][0]['flightStatusCode'] = 'HX';
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($json, 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertSame('PK', data_get($booking->meta, 'pnr_itinerary_snapshot.segments.0.airline_code'));
        $this->assertSame('100', data_get($booking->meta, 'pnr_itinerary_snapshot.segments.0.flight_number'));
        $this->assertSame('blocked_segment_status', data_get($booking->meta, 'pnr_itinerary_sync.status'));
    }

    public function test_presenter_uses_synced_snapshot_after_sync(): void
    {
        $booking = $this->bookingWithSabrePnr('UNWWPS');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->khiJedFlightsJson(), 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($booking->meta, true);
        $this->assertNotNull($out);
        $this->assertSame(BookingItineraryOverviewPresenter::ITINERARY_SOURCE_PNR_SYNCED, $out['itinerary_source']);
        $this->assertSame('PNR/airline itinerary', $out['itinerary_source_label']);
        $this->assertFalse($out['show_snapshot_itinerary_warning']);
    }

    public function test_sync_stores_safe_cancel_flags_from_get_booking(): void
    {
        $booking = $this->bookingWithSabrePnr('FLAG01');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response(array_merge($this->cleanFlightsJson(), [
                'bookingId' => 'supplier-booking-ref-only',
                'isCancelable' => true,
                'isTicketed' => false,
            ]), 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $sidecar = data_get($booking->meta, 'pnr_itinerary_sync');
        $this->assertIsArray($sidecar);
        $this->assertTrue($sidecar['is_cancelable']);
        $this->assertFalse($sidecar['is_ticketed']);
        $this->assertFalse($sidecar['ticket_numbers_present']);
        $this->assertTrue($sidecar['booking_id_present']);

        $encoded = json_encode($booking->meta);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('supplier-booking-ref-only', $encoded);
        $this->assertStringNotContainsString('fake-token', $encoded);
    }

    public function test_retrieve_auth_failure_stores_sidecar_and_attempt_without_snapshot(): void
    {
        $booking = $this->bookingWithSabrePnr('FAIL01');
        Http::fake([
            '*/v2/auth/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);
        Cache::flush();

        $exit = Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertNotSame(0, $exit);
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame('retrieve_failed', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertSame('sabre_auth_failed', data_get($booking->meta, 'pnr_itinerary_sync.reason_code'));
        $this->assertNotNull(data_get($booking->meta, 'pnr_itinerary_sync.attempted_at'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame('pnr_retrieve', $attempt->action);
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('sabre_auth_failed', $attempt->error_code);
        $this->assertNull($attempt->request_payload);
        $this->assertNull($attempt->response_payload);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['retrieve_failed'] ?? false);
        $this->assertSame('sabre_auth_failed', $summary['reason_code'] ?? null);
        $this->assertStringNotContainsString('fake-token', json_encode($summary));
    }

    public function test_empty_get_booking_response_stores_retrieve_failed_sidecar(): void
    {
        $booking = $this->bookingWithSabrePnr('EMPT01');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([], 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame('retrieve_failed', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertSame('get_booking_empty', data_get($booking->meta, 'pnr_itinerary_sync.reason_code'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
    }

    public function test_sync_persists_airline_locator_observability_when_present_in_get_booking(): void
    {
        $booking = $this->bookingWithSabrePnr('QPXBOE');
        $json = $this->cleanFlightsJson();
        $json['recordLocator'] = 'QPXBOE';
        $json['flights'][0]['airlinePnr'] = 'AIR99A';
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($json, 200),
        ]);
        Cache::flush();

        Artisan::call('sabre:sync-pnr-itinerary', ['--booking' => (string) $booking->id]);
        $booking->refresh();

        $syncSidecar = data_get($booking->meta, 'pnr_itinerary_sync');
        $this->assertIsArray($syncSidecar);
        $this->assertTrue($syncSidecar['airline_locator_present']);
        $this->assertSame('flights.0.airlinePnr', $syncSidecar['airline_locator_path']);
        $this->assertSame('AIR99A', $syncSidecar['airline_locator_value']);
        $this->assertTrue($syncSidecar['sabre_record_locator_present']);
        $this->assertSame('QPXBOE', $syncSidecar['sabre_record_locator_value']);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->first();
        $this->assertTrue((bool) data_get($attempt?->safe_summary, 'airline_locator_present'));
    }

    public function test_gate_allows_only_local_and_testing(): void
    {
        $this->assertFalse(SabreInspectGate::allowed('production'));
        $this->assertTrue(SabreInspectGate::allowed('testing'));
    }

    protected function bookingWithSabrePnr(string $pnr): Booking
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        return Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'flight_offer_snapshot' => [
                    'origin' => 'XXX',
                    'destination' => 'YYY',
                    'segments' => [],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cleanFlightsJson(): array
    {
        return [
            'bookingId' => 'sync-test-booking-id',
            'isCancelable' => true,
            'isTicketed' => false,
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'KHI',
                    'departureDate' => '2026-06-06',
                    'departureTime' => '11:00',
                    'arrivalDate' => '2026-06-06',
                    'arrivalTime' => '12:45',
                    'airlineCode' => 'PK',
                    'operatingAirlineCode' => 'PK',
                    'flightNumber' => '303',
                    'bookingClass' => 'V',
                    'flightStatusCode' => 'HK',
                ],
            ],
            'travelers' => [['givenName' => 'JANESECRET', 'surname' => 'DOESECRET']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function khiJedFlightsJson(): array
    {
        return [
            'flights' => [
                [
                    'fromAirportCode' => 'KHI',
                    'toAirportCode' => 'JED',
                    'departureDate' => '2026-05-30',
                    'departureTime' => '18:05',
                    'arrivalDate' => '2026-05-30',
                    'arrivalTime' => '20:50',
                    'airlineCode' => 'PK',
                    'operatingAirlineCode' => 'PK',
                    'flightNumber' => '831',
                    'bookingClass' => 'U',
                    'flightStatusCode' => 'HK',
                ],
            ],
        ];
    }
}
