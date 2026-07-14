<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsTicketingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', false);
        Http::fake();
    }

    public function test_readiness_command_reports_blockers(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-ticketing-readiness', ['--booking' => (string) $booking->id, '--json' => true]);
        $output = Artisan::output();
        $this->assertStringContainsString('ticketing_disabled_by_env', $output);
    }

    public function test_issue_ticket_dry_run_does_not_call_supplier(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_issue_ticket_default_without_send_is_dry_run(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('error_code=dry_run', $output);
        Http::assertNothingSent();
    }

    public function test_issue_ticket_confirm_without_send_is_dry_run(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('error_code=dry_run', $output);
        Http::assertNothingSent();
    }

    public function test_issue_ticket_send_without_confirm_is_blocked(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('success=false', $output);
        Http::assertNothingSent();
    }

    public function test_issue_ticket_confirmed_blocks_when_env_disabled(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('success=false', $output);
        Http::assertNothingSent();
    }

    public function test_issue_ticket_send_with_confirm_reaches_service_when_readiness_passes(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
        Cache::flush();

        $conn = SupplierConnection::factory()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api-crt.cert.havail.sabre.test',
        ]);

        $booking = $this->sabreBooking([
            'status' => BookingStatus::Paid,
            'selected_fare_total' => 25000,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'supplier_connection_id' => $conn->id,
                'customer_total' => 25000,
                'pnr_itinerary_sync' => ['status' => 'synced', 'is_ticketed' => false, 'ticket_numbers_present' => false],
                'pnr_itinerary_snapshot' => ['segments' => [['segment_status' => 'HK']]],
            ],
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

        $baseUrl = rtrim((string) $conn->base_url, '/');
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v1.3.0/air/ticket')) {
                return Http::response(['errors' => [['code' => 'ERR', 'detail' => 'Host rejected']]], 400);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        Artisan::call('sabre:gds-issue-ticket', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=true', $output);
        $this->assertStringContainsString('success=false', $output);
        $this->assertStringContainsString('error_code=', $output);
        $this->assertStringContainsString('error_message=', $output);
        $this->assertNotSame('error_code=', trim(explode("\n", str_replace("\r", '', $output))[3] ?? ''));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1.3.0/air/ticket'));
    }

    public function test_service_dry_run_never_calls_http(): void
    {
        $booking = $this->sabreBooking();
        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->firstOrFail();
        $actor = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreGdsTicketingService::class)->issueTickets($booking, $connection, $actor, [
            'dry_run' => true,
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('dry_run', $result->status);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sabreBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'payment_status' => 'paid',
            'pnr' => 'PNR1',
            'supplier_reference' => 'PNR1',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => SupplierConnection::query()
                    ->where('provider', SupplierProvider::Sabre->value)->value('id'),
            ],
        ], $overrides));
    }
}
