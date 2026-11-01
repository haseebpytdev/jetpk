<?php

namespace Tests\Unit\Services\Suppliers;

use App\Data\TicketingResultData;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Services\Suppliers\TicketingAdapters\AirBlueSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirBlueQaTicketingCorridorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config(['ota.qa_staff_airblue.connection_id' => 23]);
        Http::fake();
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function unpaidQaBooking(bool $withSeats = true): array
    {
        $agency = Agency::query()->firstOrFail();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'QA Ticketing Conn',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'status' => 'active',
            'base_url' => 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx',
            'credentials' => [
                'protocol_version' => '3.0',
                'service_target' => 'Test',
                'ticketing_enabled' => true,
                'client_id' => 'x',
                'client_key' => 'x',
                'agent_type' => '5',
                'agent_id' => 'x',
                'agent_password' => 'x',
            ],
        ]);
        SupplierConnection::query()->whereKey($connection->id)->update(['id' => 23]);

        $ctx = [
            'protocol_version' => '3.0',
            'pnr' => 'QA57PN',
            'instance' => 'INST57',
            'supplier_payable_amount' => 23750,
            'payment_currency' => 'PKR',
            'payment_type' => 'Cash',
            'ticketing_mode' => 'ticket',
            'seat_selection_required' => true,
        ];
        if ($withSeats) {
            $ctx['confirmed_seats'] = [['seat' => '1A']];
        }

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'pnr' => 'QA57PN',
            'supplier_reference' => 'QA57PN',
            'source_channel' => 'qa_staff_airblue',
            'supplier' => 'airblue',
            'meta' => [
                'qa_fixture' => true,
                'qa_scope_type' => 'airblue_sandbox',
                'supplier_provider' => 'airblue',
                'supplier_connection_id' => 23,
                'airblue_context' => $ctx,
            ],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => 23,
            'provider' => 'airblue',
            'status' => 'pending_ticketing',
            'pnr' => 'QA57PN',
        ]);

        return [$booking->fresh(['latestSupplierBooking', 'passengers']), $admin];
    }

    public function test_ordinary_unpaid_airblue_remains_ineligible(): void
    {
        [$booking] = $this->unpaidQaBooking();
        $this->assertFalse(app(TicketingService::class)->isBookingEligibleForTicketing($booking));
    }

    public function test_unpaid_without_qa_flag_blocked_even_for_admin(): void
    {
        [$booking, $admin] = $this->unpaidQaBooking();
        $this->mock(AirBlueSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->never();
        });

        $result = app(TicketingService::class)->issueTickets($booking, $admin, false, false, false);
        $this->assertFalse($result->success);
        $this->assertSame('booking_not_eligible', $result->error_code);
        $this->assertSame(0, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_qa_flag_with_scope_and_seats_reaches_adapter_and_persists_ticket_dry(): void
    {
        [$booking, $admin] = $this->unpaidQaBooking(withSeats: true);

        $this->mock(AirBlueSupplierTicketingAdapter::class, function ($mock) use ($booking): void {
            $mock->shouldReceive('issueTickets')->once()->andReturn(new TicketingResultData(
                success: true,
                status: 'ticketed',
                provider: 'airblue',
                tickets: [[
                    'ticket_number' => 'TKT-QA-001',
                    'pnr' => $booking->pnr,
                ]],
            ));
        });
        $this->mock(BookingCommunicationService::class, function ($mock): void {
            $mock->shouldReceive('sendTicketIssued')->never();
            $mock->shouldReceive('notifyTicketingFailure')->never();
        });
        $this->mock(LedgerEventRecorder::class, function ($mock): void {
            $mock->shouldReceive('recordMarkupRevenueForBooking')->never();
        });

        $result = app(TicketingService::class)->issueTickets($booking, $admin, false, false, true);

        $this->assertTrue($result->success);
        $this->assertSame(1, TicketingAttempt::query()->where('booking_id', $booking->id)->where('status', 'success')->count());
        $this->assertSame(1, BookingTicket::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(BookingStatus::Ticketed, $booking->fresh()->status);
        $this->assertSame('ticketed', $booking->fresh()->ticketing_status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertSame('0.00', (string) $booking->fresh()->amount_paid);
        Http::assertNothingSent();
    }

    public function test_controlled_qa_failure_does_not_send_ticketing_failure_notifications(): void
    {
        [$booking, $admin] = $this->unpaidQaBooking(withSeats: true);

        $this->mock(AirBlueSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->once()->andReturn(new TicketingResultData(
                success: false,
                status: 'failed',
                provider: 'airblue',
                error_code: 'qa_test_failure',
                error_message: 'QA supplier failure.',
            ));
        });
        $this->mock(BookingCommunicationService::class, function ($mock): void {
            $mock->shouldReceive('notifyTicketingFailure')->never();
            $mock->shouldReceive('sendTicketIssued')->never();
        });

        $result = app(TicketingService::class)->issueTickets($booking, $admin, false, false, true);

        $this->assertFalse($result->success);
        $this->assertSame(1, TicketingAttempt::query()->where('booking_id', $booking->id)->where('status', 'failed')->count());
        $this->assertSame(0, BookingTicket::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_qa_flag_blocked_when_seats_missing(): void
    {
        [$booking, $admin] = $this->unpaidQaBooking(withSeats: false);
        $this->mock(AirBlueSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->never();
        });

        $result = app(TicketingService::class)->issueTickets($booking, $admin, false, false, true);
        $this->assertFalse($result->success);
        $this->assertSame('booking_not_eligible', $result->error_code);
        $this->assertSame(0, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }
}
