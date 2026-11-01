<?php

namespace Tests\Unit\Console;

use App\Data\TicketingResultData;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\TicketingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirBlueQaTestTicketingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config(['ota.qa_staff_airblue.connection_id' => 23]);
    }

    /**
     * @return array{0: Booking, 1: User, 2: SupplierConnection}
     */
    private function fixture(bool $ticketingKeyPresent = true): array
    {
        $agency = Agency::query()->firstOrFail();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'username' => 'qa-ticket-admin',
        ]);

        $credentials = [
            'protocol_version' => '3.0',
            'service_target' => 'Test',
            'client_id' => 'x',
            'client_key' => 'x',
            'agent_type' => '5',
            'agent_id' => 'x',
            'agent_password' => 'x',
        ];
        if ($ticketingKeyPresent) {
            $credentials['ticketing_enabled'] = false;
        }

        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'QA Ticketing Finally Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'status' => 'active',
            'base_url' => 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx',
            'credentials' => $credentials,
        ]);
        SupplierConnection::query()->whereKey($connection->id)->update(['id' => 23]);
        $connection = SupplierConnection::query()->findOrFail(23);

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
                'airblue_context' => [
                    'protocol_version' => '3.0',
                    'pnr' => 'QA57PN',
                    'instance' => 'INST57',
                    'supplier_payable_amount' => 23750,
                    'payment_currency' => 'PKR',
                    'payment_type' => 'Cash',
                    'ticketing_mode' => 'ticket',
                    'seat_selection_required' => true,
                    'confirmed_seats' => [['seat' => '27A']],
                ],
            ],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => 23,
            'provider' => 'airblue',
            'status' => 'pending_ticketing',
            'pnr' => 'QA57PN',
            'supplier_reference' => 'QA57PN',
        ]);

        return [$booking->fresh(['latestSupplierBooking', 'passengers']), $admin, $connection];
    }

    public function test_temporary_enable_restores_existing_false_value_after_failed_ticket_result(): void
    {
        [$booking] = $this->fixture(ticketingKeyPresent: true);

        $this->mock(TicketingService::class, function ($mock): void {
            $mock->shouldReceive('isBookingEligibleForTicketing')->andReturn(false);
            $mock->shouldReceive('issueTickets')->once()->andReturn(new TicketingResultData(
                success: false,
                status: 'failed',
                provider: 'airblue',
                error_code: 'qa_expected_failure',
                error_message: 'QA expected failure.',
            ));
        });

        $this->artisan('airblue:qa-test-ticketing', [
            'booking' => $booking->id,
            '--actor' => 'qa-ticket-admin',
            '--temporary-enable-ticketing' => true,
            '--allow-live' => true,
        ])
            ->expectsOutputToContain('temporary_ticketing_enabled=true')
            ->expectsOutputToContain('ticketing_flag_restored=true')
            ->assertExitCode(1);

        $credentials = SupplierConnection::query()->findOrFail(23)->credentials;
        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('ticketing_enabled', $credentials);
        $this->assertFalse((bool) $credentials['ticketing_enabled']);
    }

    public function test_temporary_enable_removes_key_when_original_key_was_absent(): void
    {
        [$booking] = $this->fixture(ticketingKeyPresent: false);

        $this->mock(TicketingService::class, function ($mock): void {
            $mock->shouldReceive('isBookingEligibleForTicketing')->andReturn(false);
            $mock->shouldReceive('issueTickets')->once()->andReturn(new TicketingResultData(
                success: false,
                status: 'failed',
                provider: 'airblue',
                error_code: 'qa_expected_failure',
                error_message: 'QA expected failure.',
            ));
        });

        $this->artisan('airblue:qa-test-ticketing', [
            'booking' => $booking->id,
            '--actor' => 'qa-ticket-admin',
            '--temporary-enable-ticketing' => true,
            '--allow-live' => true,
        ])
            ->expectsOutputToContain('ticketing_flag_restored=true')
            ->assertExitCode(1);

        $credentials = SupplierConnection::query()->findOrFail(23)->credentials;
        $this->assertIsArray($credentials);
        $this->assertArrayNotHasKey('ticketing_enabled', $credentials);
    }
}
