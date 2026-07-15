<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\TicketingReadinessPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsTicketingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', false);
    }

    public function test_already_ticketed_booking_blocks_supplier_call(): void
    {
        Http::fake();
        $booking = $this->gdsBooking(['ticketing_status' => 'ticketed']);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '0012345678901',
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'issued',
        ]);

        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreGdsTicketingService::class)->issueTickets($booking, $connection, $admin, [
            'confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('already_ticketed', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_cancelled_booking_blocks_ticketing(): void
    {
        Http::fake();
        $booking = $this->gdsBooking(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreGdsTicketingService::class)->issueTickets($booking, $connection, $admin, [
            'confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertSame('booking_cancelled', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_non_sabre_provider_blocked_by_readiness(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'duffel',
            'meta' => ['supplier_provider' => 'duffel', 'distribution_channel' => 'gds'],
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);
        $this->assertContains('supplier_not_sabre', $result['blockers']);
    }

    public function test_in_progress_blocks_second_supplier_call(): void
    {
        Http::fake();
        $booking = $this->gdsBooking([
            'meta' => [
                SabreGdsTicketingReadiness::META_KEY => ['status' => 'in_progress'],
            ],
        ]);
        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreGdsTicketingService::class)->issueTickets($booking, $connection, $admin, [
            'confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertSame('ticketing_in_progress', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_successful_fake_response_persists_ticket_numbers_via_ticketing_service(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
        Cache::flush();

        $conn = $this->certConnection();
        $baseUrl = rtrim((string) $conn->base_url, '/');
        $booking = $this->readyGdsBooking($conn);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v1.3.0/air/ticket')) {
                return Http::response([
                    'AirTicketRS' => [
                        'ApplicationResults' => ['status' => 'COMPLETE'],
                        'Ticket' => [
                            'Summary' => [
                                ['DocumentNumber' => '0012345678901', 'FirstName' => 'JOHN', 'LastName' => 'DOE'],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, '/v1/trip/orders/getBooking')) {
                return Http::response([
                    'isTicketed' => true,
                    'ticketNumbers' => ['0012345678901'],
                ], 200);
            }

            return Http::response(['error' => 'unexpected url: '.$url], 500);
        });

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        request()->merge(['ticketing_confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id]);

        $result = app(TicketingService::class)->issueTickets($booking, $admin);

        $this->assertTrue($result->success, ($result->error_code ?? 'unknown').': '.($result->error_message ?? ''));
        $booking->refresh();
        $this->assertSame('ticketed', (string) $booking->ticketing_status);
        $this->assertNotNull($booking->ticketed_at);
        $this->assertSame('ticketed', (string) $booking->supplier_booking_status);
        $this->assertTrue($booking->tickets()->where('ticket_number', '0012345678901')->exists());
        $this->assertSame('ticketed', $booking->meta[SabreGdsTicketingReadiness::META_KEY]['status'] ?? null);
        $this->assertTrue(
            AuditLog::query()->where('auditable_id', $booking->id)->where('action', 'booking.sabre_gds_ticket_issued')->exists()
        );
    }

    public function test_failed_supplier_ticketing_response_persists_issue_ticket_attempt(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
        Cache::flush();

        $conn = $this->certConnection();
        $baseUrl = rtrim((string) $conn->base_url, '/');
        $booking = $this->readyGdsBooking($conn);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v1.3.0/air/ticket')) {
                return Http::response(['errors' => [['code' => 'ERR', 'detail' => 'Host rejected']]], 400);
            }

            return Http::response(['error' => 'unexpected url: '.$url], 500);
        });

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreGdsTicketingService::class)->issueTickets($booking, $conn, $admin, [
            'confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertNotEmpty($result->error_code);
        $this->assertNotEmpty($result->error_message);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'issue_ticket')
            ->latest('id')
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('ticketing_ERR', $attempt->error_code);
        $this->assertTrue((bool) ($attempt->safe_summary['live_supplier_call_attempted'] ?? false));
    }

    public function test_admin_supplier_actions_enable_issue_ticket_when_gds_readiness_passes(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
        Config::set('suppliers.sabre.public_ticketing_enabled', false);
        Config::set('suppliers.sabre.checkout_auto_ticketing_enabled', false);

        $conn = $this->certConnection();
        $booking = $this->readyGdsBooking($conn);

        $state = app(AdminBookingSupplierActions::class)->build($booking, false, false);

        $this->assertTrue($state['sabre_gds_issue_ready']);
        $this->assertTrue($state['can_issue_ticket_action']);
        $this->assertTrue($state['can_issue_ticket_live']);
        $this->assertStringContainsString(
            'Unticketed Sabre GDS PNR is ready for Enhanced Air Ticket issuance.',
            (string) $state['ticketing_status_message'],
        );
    }

    public function test_ticketing_readiness_presenter_does_not_show_legacy_sabre_not_implemented_for_gds_ready_booking(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');

        $conn = $this->certConnection();
        $booking = $this->readyGdsBooking($conn);

        $readiness = TicketingReadinessPresenter::forBooking($booking);
        $supplierItem = collect($readiness['items'])->firstWhere('key', 'supplier_ticketing');

        $this->assertIsArray($supplierItem);
        $this->assertSame('pass', $supplierItem['status']);
        $this->assertStringContainsString(
            'Unticketed Sabre GDS PNR is ready for Enhanced Air Ticket issuance.',
            (string) $supplierItem['message'],
        );
        $this->assertStringNotContainsString('not implemented', strtolower((string) $supplierItem['message']));
    }

    public function test_public_checkout_auto_ticketing_flags_do_not_block_gds_readiness(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
        Config::set('suppliers.sabre.public_ticketing_enabled', false);
        Config::set('suppliers.sabre.checkout_auto_ticketing_enabled', false);

        $conn = $this->certConnection();
        $booking = $this->readyGdsBooking($conn);

        $readiness = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertSame([], $readiness['blockers']);
        $this->assertTrue($readiness['can_execute']);
        $this->assertSame(SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET, $readiness['action_state']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gdsBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->firstOrFail();
        $meta = array_merge($this->defaultMeta($conn), (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        return Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'payment_status' => 'paid',
            'pnr' => 'ABC123',
            'supplier_reference' => 'ABC123',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ], $overrides));
    }

    protected function readyGdsBooking(SupplierConnection $conn): Booking
    {
        $booking = $this->gdsBooking([
            'status' => BookingStatus::Paid,
            'selected_fare_total' => 25000,
            'meta' => array_merge($this->defaultMeta($conn), [
                'customer_total' => 25000,
                'pnr_itinerary_sync' => ['status' => 'synced', 'is_ticketed' => false, 'ticket_numbers_present' => false],
                'pnr_itinerary_snapshot' => ['segments' => [['segment_status' => 'HK']]],
            ]),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);

        $booking->supplierBookings()->create([
            'agency_id' => $booking->agency_id,
            'supplier_connection_id' => $conn->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'created',
            'supplier_reference' => $booking->pnr,
        ]);

        return $booking->fresh(['passengers', 'contact', 'latestSupplierBooking']);
    }

    protected function certConnection(): SupplierConnection
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api-crt.cert.havail.sabre.test',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultMeta(SupplierConnection $conn): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
            'supplier_connection_id' => $conn->id,
            'pnr_itinerary_sync' => ['status' => 'synced', 'is_ticketed' => false, 'ticket_numbers_present' => false],
            'pnr_itinerary_snapshot' => ['segments' => [['segment_status' => 'HK']]],
        ];
    }
}
