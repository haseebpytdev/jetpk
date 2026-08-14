<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminBookingSyncPnrItineraryTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_show_displays_sync_pnr_itinerary_button_for_sabre_pnr(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Retrieve/sync PNR itinerary', $html);
    }

    public function test_admin_show_renders_pnr_retrieve_safety_card_without_sensitive_leaks(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK', [
            'pnr_itinerary_sync' => [
                'status' => 'synced',
                'is_cancelable' => true,
                'is_ticketed' => false,
                'ticket_numbers_present' => false,
                'booking_id_present' => true,
                'bookingId' => 'FAKE-RAW-BOOKING-ID-99999',
            ],
            'pnr_itinerary_snapshot' => [
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'KHI',
                        'airline_code' => 'PK',
                        'flight_number' => '303',
                        'segment_status' => 'HK',
                        'passenger_name' => 'LEAKY/PASSENGER',
                    ],
                ],
            ],
        ]);
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);

        $this->assertStringContainsString('Sabre capability posture', $html);
        $this->assertStringContainsString('GDS cancel (architecture)', $html);
        $this->assertStringContainsString('GDS ticketing (architecture)', $html);
        $this->assertStringContainsString('NDC (architecture)', $html);
        $this->assertStringContainsString('Diagnostics (architecture)', $html);
        $this->assertStringContainsString('Enabled', $html);
        $this->assertStringContainsString('PNR retrieve &amp; airline status', $html);
        $this->assertStringContainsString('Retrieve result', $html);
        $this->assertStringContainsString('Cancel eligible', $html);
        $this->assertStringContainsString('Live supplier cancel (env gate)', $html);
        $this->assertStringContainsString('Safe summary only', $html);
        $this->assertStringContainsString('no raw Sabre response is shown', $html);
        $this->assertStringNotContainsString('automated supplier cancel is not wired', $html);
        $this->assertStringNotContainsString('Automated void is not wired', $html);
        $this->assertStringContainsString('LHE', $html);
        $this->assertStringContainsString('PK303', $html);
        $this->assertStringNotContainsString('FAKE-RAW-BOOKING-ID-99999', $html);
        $this->assertStringNotContainsString('LEAKY/PASSENGER', $html);
        $this->assertStringNotContainsString('fake-bearer-token-abc', $html);
        $this->assertStringNotContainsString('PASSPORT123456', $html);
        $this->assertStringNotContainsString('client_secret', $html);
        $this->assertStringNotContainsString('access_token', $html);

        $this->assertSame(1, substr_count($html, 'GDS cancel (architecture)'));
        $this->assertSame(1, substr_count($html, 'GDS ticketing (architecture)'));
        $this->assertSame(1, substr_count($html, 'NDC (architecture)'));
    }

    public function test_admin_show_displays_resync_label_when_snapshot_exists(): void
    {
        $booking = $this->sabreBookingWithPnr('UNGKWK', [
            'pnr_itinerary_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-06T11:00:00', 'arrival_at' => '2026-06-06T12:45:00', 'airline_code' => 'PK', 'flight_number' => '303'],
                ],
            ],
        ]);
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Re-sync PNR itinerary (retrieve/sync)', $html);
    }

    public function test_booking_without_pnr_hides_sync_button(): void
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringNotContainsString('Retrieve/sync PNR itinerary', $html);
    }

    public function test_post_sync_without_pnr_returns_safe_error_not_500(): void
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'supplier_reference' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHasErrors('pnr_itinerary_sync');

        $this->assertNull(data_get($booking->refresh()->meta, 'pnr_itinerary_snapshot'));
    }

    public function test_post_sync_success_writes_snapshot_and_flash(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->cleanFlightsJson(), 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHas('status', 'PNR itinerary retrieve/sync completed successfully.');

        $booking->refresh();
        $this->assertSame('LHE', data_get($booking->meta, 'pnr_itinerary_snapshot.origin'));
        $this->assertSame('synced', data_get($booking->meta, 'pnr_itinerary_sync.status'));
    }

    public function test_post_sync_partial_resource_unavailable_preserves_snapshot_and_status_flash(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response(array_merge($this->cleanFlightsJson(), [
                'errors' => [['code' => 'RESOURCE_UNAVAILABLE']],
                'flights' => [[
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'KHI',
                    'departureDate' => '2026-06-06',
                    'departureTime' => '11:00',
                    'arrivalDate' => '2026-06-06',
                    'arrivalTime' => '12:45',
                    'airlineCode' => 'PK',
                    'flightNumber' => '303',
                    'bookingClass' => 'V',
                    'flightStatusCode' => 'HK',
                    'confirmationId' => 'RQATZN',
                ]],
            ]), 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('PPNYYM', [
            'pnr_itinerary_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-01T08:00:00', 'arrival_at' => '2026-06-01T10:00:00', 'airline_code' => 'PK', 'flight_number' => '100'],
                ],
            ],
        ]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame('100', data_get($booking->meta, 'pnr_itinerary_snapshot.segments.0.flight_number'));
        $this->assertSame('partial_resource_unavailable', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertSame('RQATZN', data_get($booking->meta, 'pnr_itinerary_sync.airline_locator_value'));
    }

    public function test_post_sync_blocked_resource_unavailable_preserves_snapshot(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'blocked-only-booking-id',
                'errors' => [['code' => 'RESOURCE_UNAVAILABLE']],
            ], 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('IJYJMV', [
            'pnr_itinerary_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-01T08:00:00', 'arrival_at' => '2026-06-01T10:00:00', 'airline_code' => 'PK', 'flight_number' => '100'],
                ],
            ],
        ]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHasErrors('pnr_itinerary_sync');

        $booking->refresh();
        $this->assertSame('100', data_get($booking->meta, 'pnr_itinerary_snapshot.segments.0.flight_number'));
        $this->assertSame('blocked_resource_unavailable', data_get($booking->meta, 'pnr_itinerary_sync.status'));
    }

    public function test_post_sync_hx_status_does_not_write_snapshot(): void
    {
        $json = $this->cleanFlightsJson();
        $json['flights'][0]['flightStatusCode'] = 'HX';
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($json, 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('IJYJMV');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHasErrors('pnr_itinerary_sync');

        $this->assertNull(data_get($booking->refresh()->meta, 'pnr_itinerary_snapshot'));
    }

    public function test_staff_can_post_sync(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->cleanFlightsJson(), 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('UNWWPS');
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('staff.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHas('status', 'PNR itinerary retrieve/sync completed successfully.');
    }

    public function test_presenter_uses_synced_snapshot_after_admin_sync(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response($this->cleanFlightsJson(), 200),
        ]);
        Cache::flush();

        $booking = $this->sabreBookingWithPnr('UNGKWK');
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.sync-pnr-itinerary', $booking));
        $booking->refresh();

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($booking->meta, true);
        $this->assertSame(BookingItineraryOverviewPresenter::ITINERARY_SOURCE_PNR_SYNCED, $out['itinerary_source']);
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function sabreBookingWithPnr(string $pnr, array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        return Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ], $metaExtra),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cleanFlightsJson(): array
    {
        return [
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'KHI',
                    'departureDate' => '2026-06-06',
                    'departureTime' => '11:00',
                    'arrivalDate' => '2026-06-06',
                    'arrivalTime' => '12:45',
                    'airlineCode' => 'PK',
                    'flightNumber' => '303',
                    'bookingClass' => 'V',
                    'flightStatusCode' => 'HK',
                ],
            ],
        ];
    }
}
