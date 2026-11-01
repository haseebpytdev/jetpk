<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Bookings\AirBlueQaTicketingScope;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirBlueQaTicketingScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config(['ota.qa_staff_airblue.connection_id' => 23]);
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     * @return array{0: Booking, 1: User, 2: SupplierConnection}
     */
    private function fixture(array $metaExtra = [], bool $withSeats = true): array
    {
        $agency = Agency::query()->firstOrFail();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'QA Conn 23 Fixture',
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
        // Pin id to 23 for scope match (sqlite allows explicit id).
        SupplierConnection::query()->whereKey($connection->id)->update(['id' => 23]);
        $connection = SupplierConnection::query()->findOrFail(23);

        $ctx = [
            'protocol_version' => '3.0',
            'api_channel' => 'zapways_ota',
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'supplier_payable_amount' => 23750,
            'payment_currency' => 'PKR',
            'payment_type' => 'Cash',
            'ticketing_mode' => 'ticket',
            'seat_selection_required' => true,
        ];
        if ($withSeats) {
            $ctx['confirmed_seats'] = [['seat' => '12A']];
        }

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'pnr' => 'ABC123',
            'supplier_reference' => 'ABC123',
            'source_channel' => 'qa_staff_airblue',
            'supplier' => 'airblue',
            'meta' => array_merge([
                'qa_fixture' => true,
                'qa_scope_type' => 'airblue_sandbox',
                'supplier_provider' => 'airblue',
                'supplier_connection_id' => 23,
                'airblue_context' => $ctx,
            ], $metaExtra),
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => 23,
            'provider' => 'airblue',
            'status' => 'pending_ticketing',
            'pnr' => 'ABC123',
            'supplier_reference' => 'ABC123',
        ]);

        return [$booking->fresh(['latestSupplierBooking']), $admin, $connection];
    }

    public function test_allows_unpaid_sandbox_qa_booking_when_seats_present(): void
    {
        [$booking, $admin] = $this->fixture();
        $result = app(AirBlueQaTicketingScope::class)->evaluate($booking, $admin);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['payment_bypass_only']);
        $this->assertTrue($result['seat_gate_satisfied']);
        $this->assertSame('ok', $result['reason']);
    }

    public function test_blocks_when_seat_required_and_missing(): void
    {
        [$booking, $admin] = $this->fixture([], withSeats: false);
        $result = app(AirBlueQaTicketingScope::class)->evaluate($booking, $admin);

        $this->assertFalse($result['allowed']);
        $this->assertSame('seat_selection_required', $result['reason']);
        $this->assertFalse($result['seat_gate_satisfied']);
    }

    public function test_blocks_customer_actor(): void
    {
        [$booking] = $this->fixture();
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $result = app(AirBlueQaTicketingScope::class)->evaluate($booking, $customer);

        $this->assertFalse($result['allowed']);
        $this->assertSame('unauthorized_actor', $result['reason']);
    }

    public function test_blocks_missing_qa_marker(): void
    {
        [$booking, $admin] = $this->fixture();
        $booking->forceFill([
            'source_channel' => 'public_guest',
            'meta' => [
                'supplier_provider' => 'airblue',
                'supplier_connection_id' => 23,
                'airblue_context' => data_get($booking->meta, 'airblue_context'),
            ],
        ])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate($booking->fresh(['latestSupplierBooking']), $admin);
        $this->assertFalse($result['allowed']);
        $this->assertSame('qa_fixture_missing', $result['reason']);
    }

    public function test_blocks_when_booking_tickets_exist(): void
    {
        [$booking, $admin] = $this->fixture();
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $booking->latestSupplierBooking->id,
            'ticket_number' => '999',
            'provider' => 'airblue',
            'status' => 'issued',
        ]);

        $result = app(AirBlueQaTicketingScope::class)->evaluate($booking->fresh(['latestSupplierBooking']), $admin);
        $this->assertFalse($result['allowed']);
        $this->assertSame('booking_tickets_present', $result['reason']);
    }


    public function test_blocks_when_ticketing_feature_is_disabled(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $credentials['ticketing_enabled'] = false;
        $connection->forceFill(['credentials' => $credentials])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('ticketing_feature_disabled', $result['reason']);
    }


    public function test_pre_enable_scope_allows_safe_booking_while_ticketing_flag_is_off(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $credentials['ticketing_enabled'] = false;
        $connection->forceFill(['credentials' => $credentials])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluateForTemporaryEnablement(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('ok', $result['reason']);
        $this->assertTrue($result['seat_gate_satisfied']);
    }

    public function test_blocks_when_is_active_false_even_if_status_active(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $connection->forceFill(['is_active' => false, 'status' => 'active'])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('connection_inactive', $result['reason']);
    }

    public function test_blocks_when_status_inactive_even_if_is_active_true(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $connection->forceFill(['is_active' => true, 'status' => 'inactive'])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('connection_inactive', $result['reason']);
    }

    public function test_blocks_wrong_test_host(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $connection->forceFill(['base_url' => 'https://example.invalid/v3.0/OTAAPI.asmx'])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('host_not_approved_test', $result['reason']);
    }

    public function test_blocks_non_v3_protocol(): void
    {
        [$booking, $admin, $connection] = $this->fixture();
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $credentials['protocol_version'] = '2.0';
        $connection->forceFill(['credentials' => $credentials])->save();

        $result = app(AirBlueQaTicketingScope::class)->evaluate(
            $booking->fresh(['latestSupplierBooking']),
            $admin,
            $connection->fresh(),
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('protocol_not_v3', $result['reason']);
    }
}
