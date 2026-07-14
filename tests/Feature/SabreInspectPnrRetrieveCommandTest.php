<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreInspectPnrRetrieveCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_gate_allows_only_local_and_testing(): void
    {
        $this->assertFalse(SabreInspectGate::allowed('production'));
        $this->assertTrue(SabreInspectGate::allowed('testing'));
    }

    public function test_pnr_retrieve_gate_blocks_production_when_inspect_disabled(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.pnr_retrieve_inspect_enabled', false);

        $this->assertFalse(SabreInspectGate::pnrRetrieveInspectAllowed(true, 'production'));
        $this->assertSame(
            'sabre_pnr_retrieve_inspect_disabled',
            SabreInspectGate::pnrRetrieveInspectBlockReason(true, 'production')
        );
    }

    public function test_pnr_retrieve_gate_allows_production_when_enabled_and_send(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.pnr_retrieve_inspect_enabled', true);

        $this->assertTrue(SabreInspectGate::pnrRetrieveInspectAllowed(true, 'production'));
        $this->assertNull(SabreInspectGate::pnrRetrieveInspectBlockReason(true, 'production'));
    }

    public function test_command_aborts_production_when_inspect_disabled(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.pnr_retrieve_inspect_enabled', false);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => '1',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_pnr_retrieve_inspect_disabled', Artisan::output());
    }

    public function test_command_aborts_production_without_send_even_when_inspect_enabled(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.pnr_retrieve_inspect_enabled', true);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', ['--booking' => '1']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_pnr_retrieve_inspect_requires_send', Artisan::output());
    }

    public function test_pnr_without_send_fails(): void
    {
        Config::set('app.env', 'testing');

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'RWGWZO',
            '--connection' => '2',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--send with --pnr', Artisan::output());
    }

    public function test_pnr_with_booking_fails(): void
    {
        Config::set('app.env', 'testing');

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'RWGWZO',
            '--booking' => '1',
            '--send' => true,
            '--connection' => '2',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not both', Artisan::output());
    }

    public function test_neither_pnr_nor_booking_fails(): void
    {
        Config::set('app.env', 'testing');

        $exit = Artisan::call('sabre:inspect-pnr-retrieve');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--booking={id} or --pnr={locator}', Artisan::output());
    }

    public function test_pnr_without_connection_fails(): void
    {
        Config::set('app.env', 'testing');

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'RWGWZO',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--connection={id} with --pnr', Artisan::output());
    }

    public function test_invalid_pnr_fails(): void
    {
        Config::set('app.env', 'testing');
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'BAD!',
            '--connection' => (string) $conn->id,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --pnr', Artisan::output());
    }

    public function test_direct_pnr_returns_safe_retrieve_summary(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response($this->tripOrdersGetBookingWithAllSegmentsJson(), 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'rwgwzo',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--body-style' => 'trip_orders_get_booking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"probe_mode":"direct_pnr"', $out);
        $this->assertStringContainsString('"pnr":"RWGWZO"', $out);
        $this->assertStringContainsString('"retrieve_summary"', $out);
        $this->assertStringContainsString('"retrieve_success":true', $out);
        $this->assertStringContainsString('"segment_count":1', $out);
        $this->assertStringContainsString('"carrier_chain":"EK"', $out);
        $this->assertStringContainsString('"segment_statuses":["HK"]', $out);
        $this->assertStringContainsString('"passenger_present":true', $out);
        $this->assertStringContainsString('"ticketing_present":false', $out);
        $this->assertStringContainsString('"ticket_numbers_present":false', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
        $this->assertStringNotContainsString('JANESECRET', $out);
        $this->assertStringNotContainsString('secret@example.com', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }

    public function test_retrieve_summary_unticketed_with_fares_and_is_ticketed_false(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'isTicketed' => false,
                'isCancelable' => true,
                'fares' => [['type' => 'ADT', 'total' => 88584]],
                'payments' => [['type' => 'CA']],
                'fareOffers' => [['id' => 'offer-1']],
                'flights' => [
                    [
                        'fromAirportCode' => 'LHE',
                        'toAirportCode' => 'JED',
                        'departureDate' => '2026-09-15',
                        'departureTime' => '08:00',
                        'arrivalDate' => '2026-09-15',
                        'arrivalTime' => '14:00',
                        'airlineCode' => 'QR',
                        'flightNumber' => '601',
                        'bookingClass' => 'O',
                        'flightStatusCode' => 'HK',
                    ],
                ],
                'travelers' => [['givenName' => 'JANESECRET', 'surname' => 'DOESECRET']],
            ], 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--body-style' => 'trip_orders_get_booking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"is_ticketed_value":false', $out);
        $this->assertStringContainsString('"ticketing_present":false', $out);
        $this->assertStringContainsString('"ticket_numbers_present":false', $out);
        $this->assertStringNotContainsString('JANESECRET', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
    }

    public function test_retrieve_summary_ticketed_when_is_ticketed_true(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'isTicketed' => true,
                'isCancelable' => false,
                'fares' => [['type' => 'ADT']],
                'flights' => [
                    [
                        'fromAirportCode' => 'LHE',
                        'toAirportCode' => 'JED',
                        'departureDate' => '2026-09-15',
                        'departureTime' => '08:00',
                        'arrivalDate' => '2026-09-15',
                        'arrivalTime' => '14:00',
                        'airlineCode' => 'QR',
                        'flightNumber' => '601',
                        'bookingClass' => 'O',
                        'flightStatusCode' => 'HK',
                    ],
                ],
            ], 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--body-style' => 'trip_orders_get_booking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"is_ticketed_value":true', $out);
        $this->assertStringContainsString('"ticketing_present":true', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
    }

    public function test_direct_pnr_cert_gate_allows_with_cert_entitlement_flag_on_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.pnr_retrieve_inspect_enabled', false);
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', true);

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);

        $this->assertTrue(SabreInspectGate::pnrRetrieveDirectInspectAllowed(true, $conn, 'production'));
        $this->assertNull(SabreInspectGate::pnrRetrieveDirectInspectBlockReason(true, $conn, 'production'));
    }

    public function test_command_aborts_staging_env(): void
    {
        Config::set('app.env', 'staging');

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => '1',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_pnr_retrieve_inspect_env_blocked', Artisan::output());
    }

    public function test_booking_without_pnr_returns_safe_error(): void
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'supplier_reference' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('pnr_retrieve_probe_json=', $out);
        $this->assertStringContainsString('booking_missing_pnr', $out);
    }

    public function test_send_with_itinerary_like_response_infers_segments_and_datetime_flags(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v2.5.0/passenger/records*' => Http::response($this->itineraryLikeSabreJson(), 200),
            'https://example.sabre.test/v2.4.0/passenger/records*' => Http::response([], 403),
            'https://example.sabre.test/v1/reservations/retrieve' => Http::response([], 404),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([], 422),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'IJYJMV',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('pnr_retrieve_probe_json=', $out);
        $this->assertStringContainsString('"pnr":"IJYJMV"', $out);
        $this->assertStringContainsString('"segment_count_inferred":2', $out);
        $this->assertStringContainsString('"has_departure_datetime":true', $out);
        $this->assertStringContainsString('"has_arrival_datetime":true', $out);
        $this->assertStringContainsString('"has_travel_itinerary":true', $out);
        $this->assertStringContainsString('"has_itinerary_ref":true', $out);
        $this->assertStringContainsString('"best_candidate_endpoint":"/v2.5.0/passenger/records?mode=read"', $out);
        $this->assertStringContainsString('"safe_to_map":false', $out);
        $this->assertStringContainsString('"raw_response_stored":false', $out);
        $this->assertStringNotContainsString('JOHNSECRET', $out);
        $this->assertStringNotContainsString('DOESECRET', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
        $this->assertStringNotContainsString('Authorization', $out);
        $this->assertStringNotContainsString('REALPCC99', $out);
    }

    public function test_send_with_validation_error_returns_safe_codes_only(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/*' => Http::response([
                'message' => 'Bad Request',
                'errors' => [[
                    'code' => '27131',
                    'title' => 'Validation',
                    'detail' => 'Record locator invalid',
                ]],
            ], 400),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => '/v2.5.0/passenger/records?mode=read',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"http_status":400', $out);
        $this->assertStringContainsString('"access_result":"reachable_validation_error"', $out);
        $this->assertStringContainsString('27131', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
        $this->assertStringNotContainsString('JOHNSECRET', $out);
    }

    public function test_shape_tree_requires_send(): void
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--shape-tree' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--shape-tree and --map-preview require --send', Artisan::output());
    }

    public function test_shape_tree_omits_raw_pii_and_includes_safe_paths(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->tripOrdersGetBookingWithAllSegmentsJson(), 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--shape-tree' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"shape_tree"', $out);
        $this->assertStringContainsString('"flights"', $out);
        $this->assertStringContainsString('"departureDate"', $out);
        $this->assertStringContainsString('"_type":"list"', $out);
        $this->assertStringContainsString('"_skipped":"pii_branch"', $out);
        $this->assertStringNotContainsString('JANESECRET', $out);
        $this->assertStringNotContainsString('DOESECRET', $out);
        $this->assertStringNotContainsString('2026-06-01T08:00:00', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
    }

    public function test_map_preview_maps_all_segments_and_safe_to_map_true(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->tripOrdersGetBookingWithAllSegmentsJson(), 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"map_preview"', $out);
        $this->assertStringContainsString('"candidate_source":"flights"', $out);
        $this->assertStringContainsString('"origin":"LHE"', $out);
        $this->assertStringContainsString('"destination":"DXB"', $out);
        $this->assertStringContainsString('"marketing_airline":"EK"', $out);
        $this->assertStringContainsString('"flight_number":"501"', $out);
        $this->assertStringContainsString('"candidate_segment_count":1', $out);
        $this->assertStringContainsString('"mappable_segment_count":1', $out);
        $this->assertStringContainsString('"safe_to_map_preview":true', $out);
        $this->assertStringContainsString('"resource_unavailable_present":false', $out);
        $this->assertStringNotContainsString('JANESECRET', $out);
    }

    public function test_map_preview_false_when_resource_unavailable(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'flights' => [
                    [
                        'fromAirportCode' => 'LHE',
                        'toAirportCode' => 'DXB',
                        'departureDate' => '2026-06-01',
                        'departureTime' => '08:00',
                        'arrivalDate' => '2026-06-01',
                        'arrivalTime' => '14:00',
                        'airlineCode' => 'EK',
                        'flightNumber' => '501',
                    ],
                ],
                'errors' => [
                    ['code' => 'RESOURCE_UNAVAILABLE', 'title' => 'Segment unavailable'],
                ],
            ], 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'IJYJMV',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"resource_unavailable_present":true', $out);
        $this->assertStringContainsString('"safe_to_map_preview":false', $out);
        $this->assertStringContainsString('RESOURCE_UNAVAILABLE', $out);
    }

    public function test_map_preview_get_booking_status_summary_and_cancel_inference(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v2.5.0/passenger/records*' => Http::response([], 403),
            'https://example.sabre.test/v2.4.0/passenger/records*' => Http::response([], 403),
            'https://example.sabre.test/v1/reservations/retrieve' => Http::response([], 403),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'trip-booking-id',
                'bookingSignature' => 'sig-value',
                'isCancelable' => false,
                'isTicketed' => true,
                'contactInfo' => ['email' => 'secret@example.com'],
                'creationDetails' => ['created' => true],
                'travelers' => [['givenName' => 'JANESECRET']],
                'fares' => [['type' => 'ADT']],
                'remarks' => [],
                'request' => ['confirmationId' => 'UNGKWK'],
            ], 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"get_booking_status_summary"', $out);
        $this->assertStringContainsString('"is_cancelable_value":false', $out);
        $this->assertStringContainsString('"is_ticketed_value":true', $out);
        $this->assertStringContainsString('"contact_info_present":true', $out);
        $this->assertStringContainsString('"cancel_verification_status":"likely_cancelled"', $out);
        $this->assertStringContainsString('"access_result":"forbidden"', $out);
        $this->assertStringNotContainsString('JANESECRET', $out);
        $this->assertStringNotContainsString('secret@example.com', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
    }

    public function test_map_preview_false_when_required_segment_fields_missing(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'flights' => [
                    [
                        'fromAirportCode' => 'LHE',
                        'toAirportCode' => 'DXB',
                        'airlineCode' => 'EK',
                    ],
                ],
            ], 200),
        ]);
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNWWPS',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => '/v1/trip/orders/getBooking',
            '--map-preview' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"safe_to_map_preview":false', $out);
        $this->assertStringContainsString('"mappable_segment_count":0', $out);
        $this->assertStringContainsString('departure_at', $out);
        $this->assertStringContainsString('flight_number', $out);
    }

    public function test_inspect_only_without_send_does_not_call_retrieve_urls(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'should-not-be-used', 'expires_in' => 1800], 200),
            'https://example.sabre.test/*' => Http::response($this->itineraryLikeSabreJson(), 200),
        ]);

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'UNWWPS',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $exit = Artisan::call('sabre:inspect-pnr-retrieve', [
            '--booking' => (string) $booking->id,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"live_call_attempted":false', $out);
        $this->assertStringContainsString('"access_result":"inspect_only"', $out);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripOrdersGetBookingWithAllSegmentsJson(): array
    {
        return [
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'DXB',
                    'departureDate' => '2026-06-01',
                    'departureTime' => '08:00',
                    'arrivalDate' => '2026-06-01',
                    'arrivalTime' => '14:00',
                    'airlineCode' => 'EK',
                    'operatingAirlineCode' => 'EK',
                    'flightNumber' => '501',
                    'bookingClass' => 'Y',
                    'flightStatusCode' => 'HK',
                ],
            ],
            'allSegments' => [
                [
                    'startLocationCode' => 'LHE',
                    'endLocationCode' => 'DXB',
                    'startDate' => '2026-06-01',
                    'startTime' => '08:00',
                    'endDate' => '2026-06-01',
                    'endTime' => '14:00',
                    'vendorCode' => 'EK',
                    'text' => 'EK 501',
                    'type' => 'AIR',
                ],
            ],
            'travelers' => [
                ['givenName' => 'JANESECRET', 'surname' => 'DOESECRET', 'email' => 'secret@example.com'],
            ],
            'contactInfo' => ['email' => 'secret@example.com', 'phone' => '1234567890'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itineraryLikeSabreJson(): array
    {
        return [
            'ApplicationResults' => ['status' => 'Complete'],
            'TravelItineraryRead' => [
                'TravelItinerary' => [
                    'ItineraryRef' => ['ID' => 'IJYJMV'],
                    'CustomerInfo' => [
                        'PersonName' => [
                            ['GivenName' => 'JOHNSECRET', 'Surname' => 'DOESECRET'],
                        ],
                    ],
                    'ItineraryInfo' => [
                        'ReservationItems' => [
                            'Item' => [
                                [
                                    'FlightSegment' => [
                                        'DepartureDateTime' => '2026-06-01T10:00:00',
                                        'ArrivalDateTime' => '2026-06-01T14:00:00',
                                        'FlightNumber' => '201',
                                    ],
                                ],
                                [
                                    'FlightSegment' => [
                                        'DepartureDateTime' => '2026-06-02T08:00:00',
                                        'ArrivalDateTime' => '2026-06-02T12:00:00',
                                        'FlightNumber' => '202',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
